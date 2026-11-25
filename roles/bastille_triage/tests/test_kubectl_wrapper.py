"""Tests for the triage-kubectl allowlist wrapper."""
import contextlib
import io
import pathlib
import socket
import time
from unittest import mock

import pytest
import yaml

import stubs
from test_wrappers import SECRET, leaks, load

KC = load("triage-kubectl")
SERVER = "https://192.168.7.202:6443"
FIXED = [KC.KUBECTL, f"--kubeconfig={KC.KUBECONFIG}", f"--server={SERVER}",
         "--request-timeout=20s"]


def refused(args):
    """Returns True if the wrapper refuses args."""
    with pytest.raises(KC.Refused):
        KC.build_argv(args, SERVER)
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
     ["logs", "pod/p", "--previous", "--namespace=x", "--container=c",
      "--tail=200", "--since=1h"]),
    (["logs", "pod/p"], ["logs", "pod/p", "--tail=600"]),
    (["logs", "pod/p", "--since=1h"],
     ["logs", "pod/p", "--since=1h", "--tail=600"]),
    (["logs", "-l", "app=a"], ["logs", "--selector=app=a"]),
    (["logs", "-l", "app=a", "--tail=20"],
     ["logs", "--selector=app=a", "--tail=20"]),
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
    assert KC.build_argv(args, SERVER) == [*FIXED, *tail]


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
    out = KC.build_argv(["get", "pods", "-n", "--server=https://x"],
                        SERVER)
    assert out[-1] == "--namespace=--server=https://x"


@pytest.mark.parametrize("value", ["0", "-1", "5001", "x", "", "1e2", "+5",
                                   "5 ", "05"])
def test_tail_bounds(value):
    """--tail takes 1 to 5000 lines."""
    assert refused(["logs", "p", f"--tail={value}"])


def test_request_timeout_is_not_the_agents():
    """The agent can't pass the flags the wrapper adds."""
    for flag in ("--request-timeout", "--server", "--kubeconfig"):
        assert refused(["get", "pods", flag, "0"])
        assert refused(["get", "pods", f"{flag}=0"])


@pytest.fixture(name="servers")
def servers_fixture():
    """Points the probe at loopback; listen(i) opens the i-th server."""
    hosts = ("127.0.0.2", "127.0.0.3", "127.0.0.4")
    with socket.create_server((hosts[0], 0)) as sock:
        port = sock.getsockname()[1]
    opened = []

    def listen(index):
        opened.append(socket.create_server((hosts[index], port)))

    with mock.patch.multiple(KC, SERVERS=hosts, PORT=port, PROBE_S=1):
        listen.url = lambda index: f"https://{hosts[index]}:{port}"
        yield listen
    for sock in opened:
        sock.close()


def test_exact_servers():
    """The API servers are fixed, in this order."""
    assert KC.SERVERS == ("192.168.7.202", "192.168.7.203", "192.168.7.204")
    assert KC.PORT == 6443 and KC.PROBE_S <= 3


def test_servers_match_the_egress_list():
    """pf lets the agent reach every server the wrapper may pick."""
    role = pathlib.Path(__file__).parents[1]
    rules = yaml.safe_load((role / "defaults/main.yml").read_text())[
        "triage_agent_egress"]
    assert {r["to"] for r in rules if r["port"] == 6443} == set(KC.SERVERS)


@pytest.mark.parametrize("up, want", [
    ([0, 1, 2], 0), ([1, 2], 1), ([2], 2), ([0], 0), ([], None)])
def test_first_reachable_server_wins(servers, up, want):
    """The probe falls back in order and may find none."""
    for index in up:
        servers(index)
    assert KC.pick_server() == (None if want is None else servers.url(want))


def test_probe_is_short():
    """A server that drops packets costs the probe timeout only."""
    with mock.patch.object(KC.socket, "create_connection",
                           side_effect=TimeoutError) as connect:
        assert KC.pick_server() is None
    assert [c.args[0] for c in connect.call_args_list] == [
        (h, 6443) for h in KC.SERVERS]
    assert all(c.args[1] == KC.PROBE_S for c in connect.call_args_list)


def test_ca_verification_stays_on():
    """Neither the wrapper nor the kubeconfig turns off TLS checks."""
    role = pathlib.Path(__file__).parents[1]
    text = (role / "templates/kubeconfig.j2").read_text()
    assert "certificate-authority-data" in text
    for source in (text, (role / "files/bin/triage-kubectl").read_text()):
        assert "insecure" not in source and "tls-server-name" not in source


def run_main(args, base, *streams, code=0, server=SERVER, **consts):
    """Runs main against a real stub kubectl; returns (rc, out, err, log).

    streams is the stub's stdout and stderr; the log is its argv and
    environment, if it was started.
    """
    stub = base / "kubectl"
    stubs.python_stub(stub, (*streams, *("ok\n", "")[len(streams):]), code)
    out, err = io.StringIO(), io.StringIO()
    with mock.patch.multiple(KC, KUBECTL=str(stub), **consts), \
            mock.patch.object(KC, "pick_server", return_value=server), \
            contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
        status = KC.main(args)
    return status, out.getvalue(), err.getvalue(), stubs.started(base)


def test_main_env_and_argv(tmp_path):
    """kubectl runs with fixed flags and a clean environment."""
    with mock.patch.dict("os.environ", {
            "KUBECONFIG": "/x", "KUBERC": "/x", "HTTPS_PROXY": "http://x",
            "HOME": "/h"}):
        code, out, _, (argv, env) = run_main(["get", "nodes"], tmp_path)
    assert code == 0 and out == "ok\n"
    assert argv[1:] == [*FIXED[1:], "get", "nodes"]
    env.pop("LC_CTYPE", None)  # set by the stub's Python itself
    assert set(env) == {"PATH", "HOME", "LANG", "KUBERC"}
    assert env["KUBERC"] == "off"


def test_main_passes_the_picked_server(tmp_path):
    """The server the probe found is the one kubectl is given."""
    _, _, _, started = run_main(["get", "nodes"], tmp_path,
                                server="https://192.168.7.204:6443")
    assert "--server=https://192.168.7.204:6443" in started[0]


@pytest.mark.parametrize("args", [
    ["get", "secrets"], ["delete", "pod", "x"], ["apply", "-f", "/x"],
    ["exec", "p"], ["get", "pods", "--server=https://x"], []])
def test_main_refuses_before_any_network(tmp_path, args):
    """A refusal exits 2 without a probe and without kubectl."""
    with mock.patch.object(KC.socket, "create_connection") as connect:
        code, out, err, started = run_main(args, tmp_path)
    assert code == 2 and not out and err.startswith("error: ")
    assert started is None and not connect.called


def test_main_no_server(tmp_path):
    """With no API server reachable, kubectl never starts."""
    code, out, err, started = run_main(["get", "nodes"], tmp_path,
                                       server=None)
    assert code == 1 and not out and "no Kubernetes API" in err
    assert started is None


@pytest.mark.parametrize("cmd", [
    ["get", "pods", "-o", "yaml"], ["describe", "pod", "x"],
    ["logs", "p"], ["logs", "-l", "a=b"], ["events"], ["top", "nodes"]])
def test_main_masks_stdout(tmp_path, cmd):
    """Every output path is masked."""
    text = f"ok a\nx password={SECRET} b\ntoken:\n  {SECRET}\nok c\n"
    _, out, _, _ = run_main(cmd, tmp_path, text)
    assert not leaks(out) and "ok a" in out and "ok c" in out


def test_main_masks_stderr_and_refusals(tmp_path):
    """Error text and echoed arguments are masked before any cut."""
    for pad in range(0, 1100, 7):
        text = "e " * pad + f"jointoken={SECRET} end"
        code, _, err, _ = run_main(
            ["get", "nodes"], tmp_path, "", text, code=1)
        assert code == 1 and not leaks(err)
    _, _, err, _ = run_main(["get", f"--token={SECRET}"], tmp_path)
    assert not leaks(err)


def test_main_keeps_stderr_of_a_success(tmp_path):
    """kubectl says 'No resources found' on stderr with exit code 0."""
    code, _, err, _ = run_main(["get", "pods"], tmp_path, "",
                               f"No resources found. token={SECRET}\n")
    assert code == 0 and "No resources found" in err and not leaks(err)


def test_main_failure(tmp_path):
    """A kubectl error becomes one stderr line and its output is kept."""
    code, out, err, _ = run_main(["get", "nodes"], tmp_path, "part\n",
                                 "bo\nom\n", code=1)
    assert code == 1 and out == "part\n"
    assert err.count("\n") == 1 and "bo om" in err


def test_main_missing_binary():
    """A missing kubectl is reported."""
    out, err = io.StringIO(), io.StringIO()
    with mock.patch.object(KC.toolrun, "TIMEOUT", "/nonexistent/x"), \
            mock.patch.object(KC, "pick_server", return_value=SERVER), \
            contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
        assert KC.main(["get", "nodes"]) == 1
    assert "cannot run kubectl" in err.getvalue()


def test_main_output_capped(tmp_path):
    """Output is limited to the shared cap, with a note."""
    _, out, _, _ = run_main(["get", "pods"], tmp_path, "a b\n" * 30_000)
    assert 50_000 < len(out) <= 50 * 1024 + 200 and "truncated" in out


def test_main_capture_is_bounded(tmp_path):
    """Endless output stops at the capture limit and says so."""
    code, out, err, _ = run_main(
        ["get", "pods"], tmp_path, f"a b\nx token={SECRET}\n" * 10,
        MAX_CAPTURE=100)
    assert code == 1 and "too long" in err and not leaks(out)


@pytest.mark.parametrize("cmd", [
    ["get", "pods", "-o", "json"], ["get", "pods", "-o", "yaml"],
    ["logs", "p"], ["describe", "pod", "x"]])
@pytest.mark.parametrize("text", [
    "x" * 20_000, '{"a":"' + "x" * 20_000 + '"}', "x" * 3_000_000,
    '["' + "x" * 20_000 + '"]\n'])
def test_one_huge_line_is_marked(tmp_path, cmd, text):
    """One overlong line becomes a marker, never silence."""
    _, out, _, _ = run_main(cmd, tmp_path, text)
    assert "[line over 8192 characters not shown]" in out
    assert "xxxx" not in out


def test_huge_line_hides_only_itself(tmp_path):
    """Other lines of the output stay."""
    _, out, _, _ = run_main(["get", "pods", "-o", "yaml"], tmp_path,
                            "a: 1\nb: " + "x" * 9000 + "\nc: 3\n")
    assert out.splitlines()[0] == "a: 1" and out.splitlines()[2] == "c: 3"


def test_logs_orphan_only_output_is_marked(tmp_path):
    """If the only line could be a secret's value, the output says so."""
    _, out, _, _ = run_main(["logs", "p"], tmp_path, SECRET + "\n")
    assert not leaks(out) and "hidden" in out


def test_logs_tail_is_applied_after_masking(tmp_path):
    """--tail may drop the key line but never unmask its value."""
    text = f"a b\nc d\nk password:\n  {SECRET}\ne f\n"
    for count in range(1, 7):
        _, out, _, _ = run_main(["logs", "p", "--tail", str(count)],
                                tmp_path, text)
        assert not leaks(out) and out.count("\n") <= count
    assert out == "a b\nc d\nk password:\n  [redacted]\ne f\n"


def test_logs_keep_the_newest(tmp_path):
    """Oversized logs lose their oldest lines, not the newest."""
    text = ("a b" * 100 + "\n") * 490 + "the newest\n"
    _, out, _, _ = run_main(["logs", "p"], tmp_path, text)
    assert out.endswith("the newest\n") and out.startswith("[older output")


def start_kubectl(tmp_path, body, timeout_s):
    """Starts the wrapper as a process with a shell-stub kubectl."""
    return stubs.start_wrapper(
        tmp_path, "triage-kubectl",
        f"M.KUBECTL = {{stub}}; M.TIMEOUT_S = {timeout_s}; "
        f"M.pick_server = lambda: {SERVER!r}", ["get", "nodes"], body)


def test_hung_kubectl_is_killed(tmp_path):
    """A hanging kubectl is killed; its partial output is not printed."""
    begin = time.monotonic()
    body = f"echo x token={SECRET}; sleep 60"
    with start_kubectl(tmp_path, body, 1) as proc:
        got = proc.communicate(timeout=20)
    assert time.monotonic() - begin < 15
    assert proc.returncode == 1 and got[0] == ""
    assert "kubectl timed out" in got[1] and not leaks(got[1])
    assert stubs.gone(stubs.child_pid(tmp_path))


def test_group_sigkill_leaves_no_kubectl(tmp_path):
    """If the wrapper's group is SIGKILLed, kubectl still ends in time."""
    with start_kubectl(tmp_path, stubs.STUBBORN, 2) as proc:
        assert stubs.ends_after_sigkill(proc, tmp_path)
