"""Tests for the triage-talosctl allowlist wrapper."""
import contextlib
import io
import subprocess
from unittest import mock

import pytest

from test_wrappers import load

TC = load("triage-talosctl")
NODE = "192.168.7.203"
FIXED = [TC.TALOSCTL, f"--talosconfig={TC.TALOSCONFIG}"]
AT = ["-n", NODE]


def refused(args):
    """Returns True if the wrapper refuses args."""
    with pytest.raises(TC.Refused):
        TC.build_argv(args)
    return True


def run_main(args, keyfile, stdout="ok\n", side_effect=None, code=0):
    """Runs main against a fake talosctl; returns (rc, out, err, mock)."""
    out, err = io.StringIO(), io.StringIO()
    done = subprocess.CompletedProcess([], code, stdout, "boom\n")
    with mock.patch.object(TC, "KEY_FILE", str(keyfile)), \
            mock.patch("subprocess.run", return_value=done,
                       side_effect=side_effect) as sub, \
            contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
        status = TC.main(args)
    return status, out.getvalue(), err.getvalue(), sub


@pytest.fixture(name="keyfile")
def keyfile_fixture(tmp_path):
    """Writes a temporary Omni key file."""
    path = tmp_path / "omni.key"
    path.write_text("s3cret\n")
    return path


@pytest.mark.parametrize("args, tail", [
    (["services"], ["service"]),
    (["services", "kubelet"], ["service", "kubelet"]),
    (["dmesg"], ["dmesg"]),
    (["dmesg", "--tail", "50"], ["dmesg"]),
    (["logs", "etcd"], ["logs", "etcd", "--tail=200"]),
    (["logs", "etcd", "--tail=10"], ["logs", "etcd", "--tail=10"]),
    (["get", "members"], ["get", "members"]),
    (["get", "links", "eth0", "-o", "yaml"],
     ["get", "links", "eth0", "--output=yaml"]),
    (["get", "disks", "-ojson"], ["get", "disks", "--output=json"]),
    (["version"], ["version"]),
    (["memory"], ["memory"]),
    (["mounts"], ["mounts"]),
    (["processes", "--sort=cpu"], ["processes", "--sort=cpu"]),
])
def test_allowed(args, tail):
    """Allowed commands are rewritten with the fixed config and node."""
    want = [*FIXED, f"--nodes={NODE}", *tail]
    assert TC.build_argv([*args, *AT]) == want


@pytest.mark.parametrize("form", [
    ["-n", NODE], [f"-n{NODE}"], [f"-n={NODE}"], ["--nodes", NODE],
    [f"--nodes={NODE}"]])
def test_node_forms(form):
    """Every spelling of the node flag is re-emitted joined."""
    assert f"--nodes={NODE}" in TC.build_argv(["version", *form])


@pytest.mark.parametrize("last", ["201", "202", "203", "204", "205"])
def test_all_nodes_allowed(last):
    """Each cluster node is accepted."""
    assert TC.build_argv(["version", "-n", f"192.168.7.{last}"])


@pytest.mark.parametrize("node", [
    "192.168.7.200", "192.168.7.206", "192.168.1.99", "talos-p93-8cq", "",
    "192.168.7.203,192.168.7.204", "192.168.7.203:50000", "127.0.0.1",
    "192.168.7.203 ", "192.168.7.2030", "--help"])
def test_other_nodes_refused(node):
    """Only one node of the cluster may be named."""
    assert refused(["version", "-n", node])


def test_node_required_once():
    """A command needs exactly one node."""
    assert refused(["version"])
    assert refused(["version", *AT, "-n", "192.168.7.204"])


@pytest.mark.parametrize("verb", [
    "apply-config", "bootstrap", "cluster", "conformance", "containers",
    "copy", "cp", "dashboard", "debug", "edit", "etcd", "events", "gen",
    "health", "image", "images", "inject", "inspect", "kubeconfig", "list",
    "ls", "machineconfig", "meta", "netstat", "patch", "pcap", "read",
    "cat", "reboot", "reset", "restart", "rollback", "rotate-ca", "service",
    "shutdown", "support", "time", "upgrade", "upgrade-k8s", "usage", "du",
    "validate", "wipe", "config", "cgroups", "completion", "g", "ps", "",
    "-n", "--talosconfig=/x", "Dmesg"])
def test_verbs_refused(verb):
    """Mutating, file-reading and unlisted verbs never build a command."""
    assert refused([verb, *AT])
    assert refused([verb, "x", *AT])


def test_no_verb_refused():
    """An empty command line is refused."""
    assert refused([])


@pytest.mark.parametrize("action", [
    "start", "stop", "restart", "status"])
def test_service_actions_refused(action):
    """The services verb can only show state, never act on a service."""
    assert refused(["services", "kubelet", action, *AT])
    assert refused(["services", "kubelet", *AT, action])


@pytest.mark.parametrize("flag", [
    # endpoint, credentials, client config
    "-e", "--endpoints", "--talosconfig", "-c", "--cluster", "--context",
    "--siderov1-keys-dir", "-i", "--insecure", "--cert-fingerprint",
    # streaming, files, anything else
    "-f", "--follow", "-w", "--watch", "--namespace", "-v", "--verbose",
    "--client", "--schema-file", "--check", "-h", "--help", "--", "-",
    "--nodes-file", "--outputs", "--tailx", "-N", "-O"])
@pytest.mark.parametrize("form", ["sep", "eq", "joined"])
@pytest.mark.parametrize("cmd", [["get", "members"], ["dmesg"],
                                 ["logs", "etcd"], ["version"]])
def test_flag_classes_refused(flag, form, cmd):
    """Every other flag is refused in separate, = and joined forms."""
    extra = {"sep": [flag, "/x"], "eq": [f"{flag}=/x"],
             "joined": [f"{flag}zz"]}[form]
    assert refused([*cmd, *AT, *extra])


@pytest.mark.parametrize("args", [
    ["get", "members", "--tail=5"], ["dmesg", "-o", "json"],
    ["version", "--sort=cpu"], ["logs", "etcd", "-oyaml"],
    ["services", "--tail=5"]])
def test_flags_belong_to_their_verb(args):
    """A flag allowed for one verb is refused for the others."""
    assert refused([*args, *AT])


@pytest.mark.parametrize("res", [
    "machineconfig", "machineconfigs", "mc", "MachineConfig", "rd",
    "machineconfigs.config.talos.dev", "kernelcmdline", "cmdline",
    "osrootsecrets", "kubernetesrootsecrets", "etcdsecrets", "kubeletsecrets",
    "kubernetessecrets", "apicertificates", "trustdcertificates",
    "encryptionsalts", "identities", "siderolinkconfigs", "kubeletkubeconfig",
    "registriesconfig", "staticpods", "env", "environments", "etcfilespecs",
    "members,machineconfig", "members/x", "Members", "links.net.talos.dev",
    "linkspecs", "wireguardspecs", ""])
def test_resources_refused(res):
    """Only listed resource types pass, so no config or secret can."""
    assert refused(["get", res, *AT])


@pytest.mark.parametrize("value", [
    "jsonpath={.spec}", "jsonpath", "", "YAML", "yaml,/x", "--output"])
def test_output_formats_refused(value):
    """Only the table, yaml and json outputs pass."""
    assert refused(["get", "members", "-o", value, *AT])


@pytest.mark.parametrize("value", ["0", "-1", "501", "x", "", "1e2", "+5",
                                   "5 "])
@pytest.mark.parametrize("cmd", [["dmesg"], ["logs", "etcd"]])
def test_tail_bounds(value, cmd):
    """--tail takes 1 to 500 lines."""
    assert refused([*cmd, "--tail", value, *AT])


@pytest.mark.parametrize("args", [
    ["logs"], ["logs", "etcd", "kubelet"], ["logs", "/var/log/x"],
    ["logs", "../x"], ["logs", "k8s.io/x"], ["services", "a/b"],
    ["services", "Kubelet;x"], ["get"], ["get", "links", "a", "b"],
    ["get", "links", "a b"], ["get", "links", "$(x)"], ["dmesg", "x"],
    ["version", "x"], ["memory", "x"], ["mounts", "/"], ["processes", "1"],
    ["processes", "--sort=/x"], ["logs", "etcd\n"]])
def test_positionals_refused(args):
    """Missing, extra and path-like positionals are refused."""
    assert refused([*args, *AT])


def test_dangling_flag_value():
    """A flag without its value is refused."""
    assert refused(["version", "-n"])
    assert refused(["get", "members", *AT, "-o"])


def test_paths_are_fixed():
    """The config and key live in the agent's own config dir."""
    assert TC.TALOSCONFIG == "/usr/local/etc/triage-agent/talosconfig"
    assert TC.KEY_FILE == "/usr/local/etc/triage-agent/omni.key"
    assert TC.TALOSCTL == "/usr/local/bin/talosctl"


def test_main_refuses_before_running(keyfile):
    """A refused command never reads the key or starts talosctl."""
    keyfile.unlink()
    for verb in ("reboot", "shutdown", "reset", "upgrade", "apply-config",
                 "rollback", "read", "kubeconfig"):
        code, out, err, sub = run_main([verb, *AT], keyfile)
        assert code == 2 and not out and "verb must be one of" in err
        sub.assert_not_called()


def test_main_env_and_argv(keyfile):
    """The key goes through a minimal env, stripped, and never in argv."""
    with mock.patch.dict("os.environ", {"SIDEROV1_KEYS_DIR": "/x",
                                        "TALOSCONFIG": "/x"}):
        code, out, _, sub = run_main(["version", *AT], keyfile)
    assert code == 0 and out == "ok\n"
    argv, kwargs = sub.call_args[0][0], sub.call_args[1]
    assert "s3cret" not in " ".join(argv)
    assert kwargs["env"] == {"PATH": "/usr/local/bin:/usr/bin:/bin",
                             "HOME": "/nonexistent", "LANG": "C.UTF-8",
                             "SIDERO_SERVICE_ACCOUNT_KEY": "s3cret"}
    assert kwargs["timeout"] == TC.TIMEOUT_S
    assert kwargs["stdin"] is subprocess.DEVNULL


def test_main_tails_dmesg(keyfile):
    """dmesg has no line limit of its own, so the wrapper keeps the end."""
    text = "".join(f"line{i}\n" for i in range(1000))
    _, out, _, _ = run_main(["dmesg", "--tail=2", *AT], keyfile, text)
    assert out == "line998\nline999\n"
    _, out, _, _ = run_main(["dmesg", *AT], keyfile, text)
    assert len(out.splitlines()) == 200


@pytest.mark.parametrize("line", [
    "siderolink.api=https://x:8090?jointoken=ABC123secret foo",
    "x?grpc_tunnel=true&jointoken=ABC123secret&y=1",
    "x%26jointoken%3DABC123secret", '"token":"ABC123secret"',
    "password: ABC123secret", "client_secret=ABC123secret",
    "JOINTOKEN=ABC123secret", "Authorization: Bearer ABC123secret",
    '\\"token\\":\\"ABC123secret\\"', "jointoken=x%2BABC123secret%3D",
    "api_key=ABC123secret", "privateKey: ABC123secret"])
def test_main_redacts(keyfile, line):
    """Kernel command lines hold the SideroLink join token: mask it."""
    _, out, _, _ = run_main(["logs", "machined", *AT], keyfile, line + "\n")
    assert "ABC123secret" not in out and "[redacted]" in out


@pytest.mark.parametrize("line", [
    'secret "default/foo" not found', "token expired for node",
    "failed to get configmap: key not found", "MetadataToken rotation ok"])
def test_main_keeps_plain_text(keyfile, line):
    """Prose that only mentions a secret or token is left alone."""
    _, out, _, _ = run_main(["logs", "kubelet", *AT], keyfile, line + "\n")
    assert out == line + "\n"


def test_main_redacts_stderr(keyfile):
    """Error text is masked too."""
    fail = subprocess.CompletedProcess([], 1, "", "jointoken=ABC123secret")
    code, _, err, _ = run_main(["version", *AT], keyfile,
                               side_effect=[fail])
    assert code == 1 and "ABC123secret" not in err


def test_main_failure_and_timeout(keyfile):
    """talosctl errors and hangs become one stderr line."""
    code, _, err, _ = run_main(["version", *AT], keyfile, "", code=1)
    assert code == 1 and "boom" in err
    code, out, err, _ = run_main(
        ["version", *AT], keyfile,
        side_effect=subprocess.TimeoutExpired("talosctl", 1))
    assert code == 1 and not out and "timed out" in err


def test_main_missing_key(keyfile):
    """A missing key file is reported without running talosctl."""
    keyfile.unlink()
    code, _, err, sub = run_main(["version", *AT], keyfile)
    assert code == 1 and "key" in err
    sub.assert_not_called()


def test_main_output_capped(keyfile):
    """Output is limited to the shared cap."""
    _, out, _, _ = run_main(["mounts", *AT], keyfile, "a" * 100_000)
    assert len(out) <= 50 * 1024 + 200


def test_main_logs_keep_the_newest(keyfile):
    """Oversized logs lose their oldest lines, not the newest."""
    text = "a" * 100_000 + "\nnewest\n"
    _, out, _, _ = run_main(["logs", "etcd", *AT], keyfile, text)
    assert out.endswith("newest\n") and out.startswith("[older output")
