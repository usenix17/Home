"""Tests for the role's own files: rules, templates and task order."""
import os
import pathlib
import subprocess
import time

import jinja2
import pytest
import yaml

ROLE = pathlib.Path(__file__).parents[1]
DEFAULTS = yaml.safe_load((ROLE / "defaults/main.yml").read_text())
TASKS = yaml.safe_load((ROLE / "tasks/main.yml").read_text())
LISTERS = ["ps", "procstat", "pgrep", "pkill", "top", "w", "pwdx", "fuser",
           "fstat", "sockstat", "systat", "truss", "ktrace", "kdump",
           "gcore", "lldb", "dtrace", "lastcomm", "sysctl", "env",
           "printenv", "kenv", "set", "export", "declare", "typeset"]


def index(name):
    """Returns a task's position."""
    return [t["name"] for t in TASKS].index(name)


@pytest.mark.parametrize("name", LISTERS)
def test_env_and_process_listers_are_denied(name):
    """Bare and with arguments, so the OAuth token can't be listed."""
    deny = DEFAULTS["triage_claude_deny"]
    assert f"Bash({name})" in deny and f"Bash({name} *)" in deny


def test_secrets_are_checked_before_anything_is_touched():
    """A run without its secrets stops before the first change."""
    assert index("Check the run-time secrets were passed") == 0


@pytest.mark.parametrize("problems, good", [
    ([], True), (["token near expiry"], True),
    (["token file missing"], False), (["state not writable"], False),
    (["token near expiry", "claude not executable"], False)])
def test_final_health_check_allows_the_warning_window(problems, good):
    """Only a token near expiry is tolerated after a deploy."""
    task = TASKS[-1]
    uri = task["ansible.builtin.uri"]
    assert uri["status_code"] == [200, 503] and task["retries"] >= 5
    assert "/health" in uri["url"]
    env = jinja2.Environment()
    expr = env.compile_expression(task["until"])
    assert bool(expr(triage_health={
        "status": 503 if problems else 200,
        "json": {"problems": problems}})) is good


def test_final_health_check_fails_without_an_answer():
    """No connection or a body without JSON is retried, then fails."""
    expr = jinja2.Environment().compile_expression(TASKS[-1]["until"])
    for got in ({"status": -1}, {"status": 503}, {}):
        assert not expr(triage_health=got)


def cron_lines():
    """Returns the rendered cron jobs as (user, command)."""
    text = jinja2.Environment(trim_blocks=True).from_string(
        (ROLE / "templates/cron.j2").read_text()).render(**DEFAULTS)
    return [(line.split("\t")[5], line.split("\t", 6)[6])
            for line in text.splitlines() if line[:1].isdigit()]


def test_transcripts_are_pruned_by_their_owner(tmp_path):
    """The agent's cron job drops transcripts older than 30 days only."""
    (command,) = [c for user, c in cron_lines() if user == "triage-agent"]
    assert "%" not in command
    projects = tmp_path / "projects"
    sub = projects / "-work" / "sid"
    sub.mkdir(parents=True)
    old = [projects / "-work" / "old.jsonl", sub / "old.txt"]
    new = projects / "-work" / "new.jsonl"
    outside = tmp_path / "outside.jsonl"
    for path in [*old, new, outside]:
        path.write_text("x")
    (projects / "-work" / "link.jsonl").symlink_to(outside)
    past = time.time() - 40 * 86400
    for path in [*old, outside]:
        os.utime(path, (past, past))
    home = DEFAULTS["triage_agent_home"] + "/.claude/projects"
    assert command.count(home) >= 1
    subprocess.run(["sh", "-c", command.replace(home, str(projects))],
                   check=True)
    assert not any(path.exists() for path in old)
    assert new.exists() and outside.exists()


def test_transcript_path_is_the_agents():
    """The job names the agent's own projects dir."""
    text = (ROLE / "templates/cron.j2").read_text()
    assert "{{ triage_agent_home }}/.claude/projects" in text


def test_dead_binaries_are_gone():
    """omnictl and the python3 link are neither installed nor kept."""
    assert not any("omnictl" in key for key in DEFAULTS)
    gone = TASKS[index("Remove retired files")]
    assert gone["ansible.builtin.file"]["state"] == "absent"
    assert gone["loop"] == ["{{ triage_lib_dir }}/linux",
                            "/usr/local/bin/python3"]
    for task in TASKS:
        if task["name"] != "Remove retired files":
            assert "omnictl" not in str(task) and "/linux\"" not in str(task)
            assert not str(task).endswith("bin/python3")


def test_defaults_name_the_owner_by_role():
    """The public defaults hold no personal name."""
    text = (ROLE / "defaults/main.yml").read_text()
    assert "R15 (owner, 2026-11-23)" in text
    assert "updated_time" in text and "created_time" not in text
