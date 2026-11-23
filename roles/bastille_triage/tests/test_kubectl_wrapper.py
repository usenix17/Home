"""Tests for the triage-kubectl allowlist wrapper."""
import contextlib
import io
from unittest import mock

import pytest

from test_wrappers import load

KC = load("triage-kubectl")
FIXED = f"--kubeconfig={KC.KUBECONFIG}"


def refused(args):
    """Returns True if the wrapper refuses args."""
    with pytest.raises(KC.Refused):
        KC.build_argv(args)
    return True


@pytest.mark.parametrize("args, tail", [
    (["get", "nodes"], ["get", "nodes"]),
    (["get", "pods", "-A", "-o", "wide"],
     ["get", "pods", "--all-namespaces", "--output=wide"]),
    (["get", "pods", "-owide", "-nkube-system"],
     ["get", "pods", "--output=wide", "--namespace=kube-system"]),
    (["get", "pods", "--namespace=x", "-l", "app=a", "--field-selector",
      "spec.nodeName=n"],
     ["get", "pods", "--namespace=x", "--selector=app=a",
      "--field-selector=spec.nodeName=n"]),
    (["logs", "-n", "x", "pod/p", "-c", "c", "--tail", "100", "--since=1h",
      "--previous"],
     ["logs", "--namespace=x", "pod/p", "--container=c", "--tail=100",
      "--since=1h", "--previous"]),
    (["describe", "node", "talos-p93-8cq"],
     ["describe", "node", "talos-p93-8cq"]),
    (["top", "nodes"], ["top", "nodes"]),
    (["events", "-A", "--sort-by", ".lastTimestamp"],
     ["events", "--all-namespaces", "--sort-by=.lastTimestamp"]),
    (["get", "externalsecrets", "-A"],
     ["get", "externalsecrets", "--all-namespaces"]),
])
def test_allowed(args, tail):
    """Allowed commands are rewritten with the fixed kubeconfig."""
    assert KC.build_argv(args) == [KC.KUBECTL, FIXED, *tail]


@pytest.mark.parametrize("verb", [
    "exec", "delete", "apply", "edit", "patch", "port-forward", "proxy",
    "config", "auth", "cp", "run", "create", "", "--server=x"])
def test_verbs_refused(verb):
    """Only get, describe, logs, top and events are accepted."""
    assert refused([verb, "pods"] if verb else [])


@pytest.mark.parametrize("flag", [
    # files and templates
    "-f", "--filename", "-k", "--kustomize", "--template",
    # server, credentials, client config
    "-s", "--server", "--kubeconfig", "--token", "--username", "--password",
    "--client-certificate", "--client-key", "--certificate-authority",
    "--insecure-skip-tls-verify", "--tls-server-name", "--context",
    "--cluster", "--user", "--cache-dir", "--raw",
    # impersonation
    "--as", "--as-group", "--as-uid",
    # misc
    "-w", "--watch", "--", "-R"])
@pytest.mark.parametrize("form", ["sep", "eq", "joined"])
def test_flag_classes_refused(flag, form):
    """Every rejected flag is refused in separate, = and joined forms."""
    if form == "sep":
        args = ["get", "pods", flag, "/usr/local/etc/triage/claude.token"]
    elif form == "eq":
        args = ["get", "pods", f"{flag}=https://example.com"]
    else:
        args = ["get", "pods", f"{flag}value"]
    assert refused(args)


@pytest.mark.parametrize("arg", [
    "-ogo-template-file=/usr/local/etc/triage/claude.token",
    "--output=go-template-file=/x", "-o=jsonpath-file=/x",
    "--output=jsonpath={.items}", "-o", "-ocustom-columns-file=/x",
    "--output=template"])
def test_output_formats_refused(arg):
    """Only wide, json, yaml and name output formats pass."""
    args = ["get", "pods", arg]
    if arg == "-o":
        args.append("go-template={{.}}")
    assert refused(args)


@pytest.mark.parametrize("res", [
    "secret", "secrets", "Secret", "SECRETS", "secret/x", "secrets.v1",
    "pods,secrets", "Secrets.v1./name", "sEcReT"])
@pytest.mark.parametrize("verb", ["get", "describe"])
def test_secrets_refused(verb, res):
    """The Secrets resource is refused in any case or form."""
    assert refused([verb, res, "-A"])


def test_boolean_with_value_and_missing_value():
    """Combined short flags and dangling values are refused."""
    assert refused(["get", "pods", "-Ao", "wide"])
    assert refused(["get", "pods", "--previous=true"])
    assert refused(["get", "pods", "-n"])


def test_value_cannot_smuggle_flag():
    """A value that looks like a flag is passed joined, never as a flag."""
    out = KC.build_argv(["get", "pods", "-n", "--server=https://x"])
    assert out[-1] == "--namespace=--server=https://x"


def test_main_refuses_and_execs():
    """main exits 2 on refusal and execs kubectl with a clean env."""
    err = io.StringIO()
    with contextlib.redirect_stderr(err):
        assert KC.main(["get", "secrets"]) == 2
    assert "secrets" in err.getvalue()
    with mock.patch("os.execve") as execve:
        KC.main(["get", "nodes"])
    path, argv, env = execve.call_args[0]
    assert path == KC.KUBECTL and argv[1] == FIXED
    assert set(env) == {"PATH", "HOME", "LANG"}
