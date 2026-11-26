"""Tests for the watcher role's hand-off to the triage receiver."""
import pathlib
import re

import pytest
import yaml

REPO = pathlib.Path(__file__).parents[3]


def token_pattern():
    """Returns the charset the watcher role asserts for the push token."""
    tasks = yaml.safe_load(
        (REPO / "roles/bastille_watcher/tasks/main.yml").read_text())
    rules = [rule for rule in tasks[0]["ansible.builtin.assert"]["that"]
             if rule.startswith("watcher_triage_token is match(")]
    return re.search(r"match\('(.*)'\)", rules[0]).group(1)


@pytest.mark.parametrize("token, good", [
    ("aZ0_-" * 8, True), ("a" * 31, False), ("a" * 40 + '"', False),
    ("a" * 40 + "\n", False), ("a" * 40 + "${X}", False),
    ("a" * 40 + " b", False), ("CHANGE_ME", False), ("a" * 40 + "=", False)])
def test_push_token_charset(token, good):
    """Only characters that are safe inside the rendered YAML pass."""
    assert bool(re.match(token_pattern(), token)) is good


def test_playbook_names_every_secret():
    """The watcher playbook lists the triage push token."""
    text = (REPO / "playbooks/watcher.yml").read_text()
    assert "vault_triage_push_token" in text
    assert "triagePushToken" in text
