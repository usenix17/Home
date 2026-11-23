"""Tests for the claude -p runner."""
import json
import os
import stat
import time

import pytest

from triage.runner import RunResult, child_env, run_claude

OK_JSON = json.dumps({
    "is_error": False,
    "result": "done",
    "structured_output": {"confidence": "high", "summary": "fine"},
})

_SCRIPT = """#!/bin/sh
printf '%s\\n' "$@" > "{dir}/argv"
env > "{dir}/env"
if [ -n "{child}" ]; then
  sleep 300 &
  echo $! > "{dir}/child.pid"
fi
sleep {sleep}
printf '%s' '{stdout}'
exit {rc}
"""


@pytest.fixture(name="fake_claude")
def fixture_fake_claude(tmp_path):
    """Returns a factory that builds a cfg pointing at a fake claude."""

    def make(stdout="", rc=0, sleep=0, timeout_s=30, child=False):
        script = tmp_path / "claude"
        script.write_text(_SCRIPT.format(
            dir=tmp_path, sleep=sleep, rc=rc, child="1" if child else "",
            stdout=stdout.replace("'", "'\\''")))
        script.chmod(script.stat().st_mode | stat.S_IXUSR)
        token = tmp_path / "token"
        token.write_text("tok-123\n")
        schema = tmp_path / "schema.json"
        schema.write_text('{"type": "object"}')
        workdir = tmp_path / "work"
        workdir.mkdir(exist_ok=True)
        return {
            "claude_bin": str(script), "workdir": str(workdir),
            "schema_path": str(schema), "token_path": str(token),
            "model": None, "max_turns": 25, "timeout_s": timeout_s,
            "dir": tmp_path,
        }

    return make


def read_argv(cfg):
    """Returns the argv lines the fake recorded."""
    return (cfg["dir"] / "argv").read_text().splitlines()


def test_child_env_drops_api_key():
    """Checks child env drops api key."""
    env = child_env({"ANTHROPIC_API_KEY": "sk", "ANTHROPIC_AUTH_TOKEN": "a",
                     "PATH": "/x", "HOME": "/h"}, "tok")
    assert "ANTHROPIC_API_KEY" not in env
    assert "ANTHROPIC_AUTH_TOKEN" not in env
    assert env["CLAUDE_CODE_OAUTH_TOKEN"] == "tok"
    assert env["DISABLE_AUTOUPDATER"] == "1"
    assert env["PATH"] == "/x"


def test_run_ok_parses_structured_output(fake_claude):
    """Checks run ok parses structured output."""
    res = run_claude("p", fake_claude(stdout=OK_JSON))
    assert isinstance(res, RunResult)
    assert res.kind == "ok"
    assert res.result["confidence"] in ("low", "medium", "high")


def test_run_passes_required_flags(fake_claude):
    """Checks run passes required flags."""
    cfg = fake_claude(stdout=OK_JSON)
    run_claude("p", cfg)
    argv = read_argv(cfg)
    for flag in ("-p", "--permission-mode", "dontAsk", "--max-turns", "25",
                 "--output-format", "json", "--json-schema"):
        assert flag in argv
    assert "--bare" not in argv
    assert "--model" not in argv
    assert '{"type": "object"}' in argv


def test_run_passes_model(fake_claude):
    """Checks run passes model."""
    cfg = fake_claude(stdout=OK_JSON)
    cfg["model"] = "opus"
    run_claude("p", cfg)
    argv = read_argv(cfg)
    assert argv[argv.index("--model") + 1] == "opus"


def test_run_classifies_failures(fake_claude):
    """Checks run classifies failures."""
    assert run_claude("p", fake_claude(
        stdout="You've hit your weekly limit", rc=1)).kind == "usage_limit"
    assert run_claude("p", fake_claude(
        stdout="OAuth token revoked", rc=1)).kind == "auth"
    assert run_claude("p", fake_claude(
        stdout="not json", rc=0)).kind == "error"
    assert run_claude("p", fake_claude(stdout="", rc=0)).kind == "error"
    assert run_claude("p", fake_claude(
        sleep=5, timeout_s=1)).kind == "timeout"


def test_run_classifies_json_is_error(fake_claude):
    """Checks run classifies json is error."""
    body = json.dumps({"is_error": True,
                       "result": "You've hit your session limit"})
    assert run_claude("p", fake_claude(stdout=body)).kind == "usage_limit"
    body = json.dumps({"is_error": True, "result": "Login expired"})
    assert run_claude("p", fake_claude(stdout=body)).kind == "auth"
    body = json.dumps({"is_error": True, "result": "boom"})
    assert run_claude("p", fake_claude(stdout=body)).kind == "error"
    body = json.dumps({"is_error": False, "result": "x"})
    assert run_claude("p", fake_claude(stdout=body)).kind == "error"


def test_missing_binary_is_error(fake_claude):
    """Checks missing binary is error."""
    cfg = fake_claude(stdout=OK_JSON)
    cfg["claude_bin"] = "/nonexistent/claude"
    res = run_claude("p", cfg)
    assert res.kind == "error" and res.raw


def test_unreadable_token_is_error(fake_claude):
    """Checks unreadable token is error."""
    cfg = fake_claude(stdout=OK_JSON)
    cfg["token_path"] = "/nonexistent/token"
    assert run_claude("p", cfg).kind == "error"


def test_child_does_not_see_api_key(fake_claude, monkeypatch):
    """Checks child does not see api key."""
    monkeypatch.setenv("ANTHROPIC_API_KEY", "sk-secret")
    cfg = fake_claude(stdout=OK_JSON)
    run_claude("p", cfg)
    env = (cfg["dir"] / "env").read_text()
    assert "ANTHROPIC_API_KEY" not in env
    assert "CLAUDE_CODE_OAUTH_TOKEN=tok-123" in env


def test_timeout_kills_group(fake_claude):
    """Checks timeout kills group."""
    cfg = fake_claude(sleep=30, timeout_s=1, child=True)
    res = run_claude("p", cfg)
    assert res.kind == "timeout"
    pid = int((cfg["dir"] / "child.pid").read_text())
    for _ in range(50):
        try:
            os.kill(pid, 0)
        except ProcessLookupError:
            return
        time.sleep(0.1)
    pytest.fail("background child survived the timeout")
