"""Tests for the triage-talosctl allowlist wrapper."""
import contextlib
import io
import signal
import time
from unittest import mock

import pytest

import stubs
from test_wrappers import load

TC = load("triage-talosctl")
NODE = "192.168.7.203"
FIXED = [TC.TALOSCTL, f"--talosconfig={TC.TALOSCONFIG}"]
AT = ["-n", NODE]
SECRET = "S3cr3tV4lueXq"


def refused(args):
    """Returns True if the wrapper refuses args."""
    with pytest.raises(TC.Refused):
        TC.build_argv(args)
    return True


def run_main(args, keyfile, stdout="ok\n", stderr="", code=0, **consts):
    """Runs main against a real stub talosctl; returns (rc, out, err, log).

    The log is the stub's argv and environment, if it was started.
    """
    stub = keyfile.parent / "talosctl"
    stubs.python_stub(stub, (stdout, stderr), code)
    consts = {"KEY_FILE": str(keyfile), "TALOSCTL": str(stub), **consts}
    out, err = io.StringIO(), io.StringIO()
    with mock.patch.multiple(TC, **consts), \
            contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
        status = TC.main(args)
    return (status, out.getvalue(), err.getvalue(),
            stubs.started(keyfile.parent))


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
    (["logs", "etcd"], ["logs", "etcd", "--tail=300"]),
    (["logs", "etcd", "--tail=10"], ["logs", "etcd", "--tail=110"]),
    (["get", "members"], ["get", "members"]),
    (["get", "links", "eth0"], ["get", "links", "eth0"]),
    (["get", "links", "-o", "table"], ["get", "links", "--output=table"]),
    (["get", "routes", "-o", "yaml"], ["get", "routes", "--output=yaml"]),
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


def test_exact_allowlists():
    """The allowlists hold these entries and no others."""
    assert sorted(TC.VERBS) == [
        "dmesg", "get", "logs", "memory", "mounts", "processes", "services",
        "version"]
    assert {name: spec[0] for name, spec in TC.VERBS.items()} == {
        "services": "service", "dmesg": "dmesg", "logs": "logs",
        "get": "get", "version": "version", "memory": "memory",
        "processes": "processes", "mounts": "mounts"}
    assert sorted(TC.NODES) == [
        "192.168.7.201", "192.168.7.202", "192.168.7.203", "192.168.7.204",
        "192.168.7.205"]
    assert sorted(TC.RESOURCES) == [
        "addresses", "blockdevices", "cpustats", "diagnostics",
        "discoveredvolumes", "disks", "etcdmembers", "extensions",
        "hostname", "kubeletstatuses", "links", "machinestatus", "members",
        "memorystats", "mountstatus", "nodename", "nodestatuses",
        "resolvers", "routes", "services", "staticpodstatus", "systemdisk",
        "timeservers", "timestatus", "volumestatus"]
    assert TC.TABLE_ONLY == {"links"}


@pytest.mark.parametrize("out", ["yaml", "json"])
def test_links_table_only(out):
    """Link yaml and json can hold WireGuard keys."""
    assert refused(["get", "links", *AT, "-o", out])
    assert refused(["get", "-o", out, *AT, "links", "wg0"])


@pytest.mark.parametrize("verb", [
    "reboot", "shutdown", "reset", "upgrade", "apply-config", "rollback",
    "read", "kubeconfig", "etcd", "wipe", "bootstrap", "service"])
def test_main_refuses_before_running(keyfile, verb):
    """A refused command never reads the key or starts talosctl."""
    keyfile.unlink()
    code, out, err, started = run_main([verb, *AT], keyfile)
    assert code == 2 and not out and "verb must be one of" in err
    assert started is None


@pytest.mark.parametrize("action", ["start", "stop", "restart"])
def test_main_refuses_service_actions(keyfile, action):
    """A service action never starts talosctl."""
    code, _, _, started = run_main(
        ["services", "kubelet", action, *AT], keyfile)
    assert code == 2 and started is None


def test_main_env_and_argv(keyfile):
    """The key goes through a minimal env, stripped, and never in argv."""
    with mock.patch.dict("os.environ", {"SIDEROV1_KEYS_DIR": "/x",
                                        "TALOSCONFIG": "/x"}):
        code, out, _, (argv, env) = run_main(["version", *AT], keyfile)
    assert code == 0 and out == "ok\n"
    assert argv[1:] == [FIXED[1], f"--nodes={NODE}", "version"]
    env.pop("LC_CTYPE", None)  # set by the stub's Python itself
    assert env == {"PATH": "/usr/local/bin:/usr/bin:/bin",
                   "HOME": "/nonexistent", "LANG": "C.UTF-8",
                   "SIDERO_SERVICE_ACCOUNT_KEY": "s3cret"}


def test_main_tails_dmesg(keyfile):
    """dmesg has no line limit of its own, so the wrapper keeps the end."""
    text = "".join(f"a line{i}\n" for i in range(1000))
    _, out, _, started = run_main(["dmesg", "--tail=2", *AT], keyfile, text)
    assert out == "a line998\na line999\n"
    assert "--tail" not in " ".join(started[0])
    _, out, _, _ = run_main(["dmesg", *AT], keyfile, text)
    assert len(out.splitlines()) == 200


def test_main_strips_the_node_prefix(keyfile):
    """The node prefix can't hide that a value follows its key."""
    text = (f"{NODE}: a b\n{NODE}: password:\n"
            f"{NODE}:   {SECRET}\n{NODE}: c d\n")
    _, out, _, _ = run_main(["logs", "etcd", *AT], keyfile, text)
    assert out == "a b\npassword:\n  [redacted]\nc d\n"


@pytest.mark.parametrize("cmd", [["logs", "etcd"], ["dmesg"], ["mounts"],
                                 ["get", "routes", "-o", "yaml"]])
def test_main_redacts_stdout(keyfile, cmd):
    """Every output path is masked."""
    text = f"x a?jointoken={SECRET} b\nok c\n"
    _, out, _, _ = run_main([*cmd, *AT], keyfile, text)
    assert SECRET not in out and "[redacted]" in out and "ok c" in out


def test_main_redacts_stderr(keyfile):
    """Error text is masked before it is shortened."""
    for pad in range(0, 1100, 7):
        text = "e " * pad + f"jointoken={SECRET} end"
        code, _, err, _ = run_main(["version", *AT], keyfile, "", text, 1)
        assert code == 1 and not leaks(err)


def test_main_redacts_refusals(keyfile):
    """Arguments echoed in a refusal are masked too."""
    _, _, err, _ = run_main(["version", f"--token={SECRET}", *AT], keyfile)
    assert SECRET not in err


def leaks(text):
    """Returns True if any 4-character piece of SECRET is in text."""
    return any(SECRET[i:i + 4] in text for i in range(len(SECRET) - 3))


@pytest.mark.parametrize("cmd", [["dmesg"], ["mounts"], ["logs", "etcd"]])
def test_capture_cut_at_every_offset(keyfile, cmd):
    """The capture limit may land anywhere in a key=value pair."""
    for secret in (f"jointoken={SECRET}", f"http://u:{SECRET}@host",
                   f"password:\n  {SECRET}", f"eyJh{SECRET}.{SECRET}.x"):
        text = f"one two\n{'x ' * 10}{secret} more\nlast line\n"
        for limit in range(8, len(text) + 2, 1):
            raw = text.encode()[:limit]
            flags = {"--nodes": NODE, "--tail": "50"}
            if cmd == ["mounts"]:
                flags.pop("--tail")
            out = TC.render(cmd[0], flags, raw, limit < len(text))
            assert not leaks(out), (secret, limit)
    code, out, err, _ = run_main(
        [*cmd, *AT], keyfile, f"one two\nx jointoken={SECRET}\n",
        MAX_CAPTURE=25)
    assert code == 1 and not leaks(out) and "too long" in err


@pytest.mark.parametrize("secret", [
    f"jointoken={SECRET}", f"password:\n  {SECRET}",
    f"token: |\n  a\n  {SECRET}", f"password:\n{SECRET}"])
def test_tail_cut_at_every_line(keyfile, secret):
    """--tail may drop the key line but never unmask its value."""
    text = f"a b\nc d\nk {secret}\ne f\n"
    for count in range(1, 9):
        for cmd in (["dmesg"], ["logs", "etcd"]):
            _, out, _, _ = run_main(
                [*cmd, "--tail", str(count), *AT], keyfile, text)
            assert not leaks(out)


def test_output_cap_at_every_offset():
    """The 50 KB cap may land anywhere around a masked value."""
    flags = {"--nodes": NODE, "--tail": "500"}
    for pad in range(-40, 40):
        fill = "y " * ((TC.wrapperlib.MAX_OUTPUT + pad) // 2)
        raw = f"a b\nx jointoken={SECRET} {fill[:30]}\n{fill}\nz z\n"
        out = TC.render("logs", flags, raw.encode(), False)
        assert not leaks(out) and out.endswith("z z\n")


@pytest.mark.parametrize("first", [
    f"  {SECRET}", SECRET, f"kern: info: [2026-11-25T00:00:00Z]:   {SECRET}",
    f"kern: info: [2026-11-25T00:00:00Z]: {SECRET}", f"{NODE}: {SECRET}"])
@pytest.mark.parametrize("cmd", [["dmesg"], ["logs", "etcd"]])
def test_first_line_may_be_an_orphan_value(keyfile, first, cmd):
    """A value whose key was rotated out is not shown."""
    _, out, _, _ = run_main([*cmd, *AT], keyfile, f"{first}\na b\n")
    assert out == "a b\n"


PROCS = """\
NODE            PID      STATE   THREADS   CPU-TIME   VIRTMEM   RESMEM   \
LABEL                             COMMAND
192.168.7.203   9559     S       1         0.00       234 kB    0 B      \
system_u:system_r:pod_t:s0        /package/s6-supervise svc --token {v}
192.168.7.203   12       D       14        1234.56    1.2 GB    113 MB   \
system_u:system_r:pod_t:s0:c1,c2  redis-server --requirepass {v}
192.168.7.203   2        S       1         0.10       0 B       0 B      \
                                  kthreadd
192.168.7.203   77       S       1         0.10       0 B       0 B      \
system_u:system_r:pod_t:s0        /bin/sh -c export A=1
PASSWORD={v}
   {v}
192.168.7.203   78       S       1         0.10       0 B       0 B      \
system_u:system_r:pod_t:s0        TOKEN={v} x
192.168.7.203   79       S       1         0.10       0 B       0 B      \
system_u:system_r:pod_t:s0        eyJhbGciOi{v}
"""


def test_processes_drops_args(keyfile):
    """Only numbers and the executable's basename are printed."""
    _, out, _, _ = run_main(["processes", *AT], keyfile,
                            PROCS.format(v=SECRET))
    assert out.splitlines() == [
        "PID STATE THREADS CPU-TIME VIRTMEM RESMEM NAME",
        "9559 S 1 0.00 234kB 0B s6-supervise",
        "12 D 14 1234.56 1.2GB 113MB redis-server",
        "2 S 1 0.10 0B 0B kthreadd",
        "77 S 1 0.10 0B 0B sh",
        "78 S 1 0.10 0B 0B ?",
        "79 S 1 0.10 0B 0B [redacted]"]


@pytest.mark.parametrize("text", [
    "", "\n", f"junk {SECRET}\n",
    PROCS.replace("LABEL", "ARGS "), PROCS.replace("NODE  ", "HOST  "),
    PROCS.replace(" S       1 ", " 1 "), PROCS.split("\n", 1)[1],
    PROCS.split("\n", 1)[0] + "\n"])
def test_processes_unknown_format(keyfile, text):
    """An unrecognised process table prints nothing and fails."""
    code, out, err, _ = run_main(["processes", *AT], keyfile, text)
    assert code == 1 and not out and "not recognised" in err


def test_main_failure(keyfile):
    """A talosctl error becomes one stderr line."""
    code, out, err, _ = run_main(["version", *AT], keyfile, "", "bo\nom\n", 1)
    assert code == 1 and not out
    assert err.count("\n") == 1 and "bo om" in err


def test_main_missing_key(keyfile):
    """A missing key file is reported without running talosctl."""
    keyfile.unlink()
    code, _, err, started = run_main(["version", *AT], keyfile)
    assert code == 1 and "key" in err and started is None


def test_main_missing_binary(keyfile):
    """A missing talosctl is reported."""
    code, _, err, _ = run_main(["version", *AT], keyfile,
                               TALOSCTL="/nonexistent/talosctl")
    assert code == 1 and "talosctl" in err


def test_main_output_capped(keyfile):
    """Output is limited to the shared cap."""
    _, out, _, _ = run_main(["mounts", *AT], keyfile, "a b\n" * 30_000)
    assert 50_000 < len(out) <= 50 * 1024 + 200


def test_main_logs_keep_the_newest(keyfile):
    """Oversized logs lose their oldest lines, not the newest."""
    text = "a b\n" + "a b" * 100 + "\n"
    text = text * 490 + "the newest\n"
    _, out, _, _ = run_main(["logs", "etcd", "--tail=500", *AT], keyfile,
                            text)
    assert out.endswith("the newest\n") and out.startswith("[older output")
    assert len(out) <= 50 * 1024


@pytest.mark.parametrize("cmd", [["mounts"], ["logs", "etcd"], ["dmesg"]])
def test_hostile_long_line_is_fast(keyfile, cmd):
    """A long line built to slow the masking is hidden, quickly."""
    evil = 'basic token=["\\=\\"'
    text = "a b\n" + evil * (262_144 // len(evil)) + "\nc d\n"
    start = time.monotonic()
    code, out, _, _ = run_main([*cmd, *AT], keyfile, text)
    assert time.monotonic() - start < 2
    assert code == 0 and "token" not in out
    assert out.startswith("a b\n") and out.endswith("\nc d\n")


def start_talosctl(tmp_path, body, timeout_s):
    """Starts the wrapper as a process with a shell-stub talosctl."""
    key = tmp_path / "omni.key"
    key.write_text("k\n")
    return stubs.start_wrapper(
        tmp_path, "triage-talosctl",
        f"M.TALOSCTL = {{stub}}; M.KEY_FILE = {str(key)!r}; "
        f"M.TIMEOUT_S = {timeout_s}",
        ["version", "-n", "192.168.7.203"], body)


def test_real_timeout(tmp_path):
    """A hanging talosctl is killed; its partial output is not printed."""
    begin = time.monotonic()
    body = f"echo x token={SECRET}; sleep 60"
    with start_talosctl(tmp_path, body, 1) as proc:
        out, err = proc.communicate(timeout=20)
    assert proc.returncode == 1 and not out and "timed out" in err
    assert SECRET not in err and time.monotonic() - begin < 15
    assert stubs.gone(stubs.child_pid(tmp_path))


def test_real_sigterm(tmp_path):
    """SIGTERM to the wrapper kills talosctl, even one that ignores it."""
    with start_talosctl(tmp_path, "trap '' TERM; sleep 60 & wait; sleep 60",
                        60) as proc:
        pid = stubs.child_pid(tmp_path)
        proc.send_signal(signal.SIGTERM)
        out, _ = proc.communicate(timeout=20)
    assert proc.returncode == 128 + signal.SIGTERM and not out
    assert stubs.gone(pid)


def test_group_sigkill_leaves_no_talosctl(tmp_path):
    """If the wrapper's group is SIGKILLed, talosctl still ends in time."""
    with start_talosctl(tmp_path, stubs.STUBBORN, 2) as proc:
        assert stubs.ends_after_sigkill(proc, tmp_path)


def test_outer_limit_is_just_above_the_wrappers():
    """The backstop ends talosctl soon after the wrapper's own limit."""
    argv = TC.toolrun.bounded(["talosctl", "version"], TC.TIMEOUT_S)
    assert argv == ["/usr/bin/timeout", "-k", "2", str(TC.TIMEOUT_S + 2),
                    "talosctl", "version"]
    assert TC.TIMEOUT_S == 45


@pytest.mark.parametrize("cmd", [["logs", "etcd"], ["dmesg"], ["mounts"],
                                 ["get", "routes", "-o", "json"]])
@pytest.mark.parametrize("text", ["x" * 20_000, "x" * 3_000_000,
                                  '["' + "x" * 20_000 + '"]\n'])
def test_one_huge_line_is_marked(keyfile, cmd, text):
    """One overlong line becomes a marker, never silence."""
    _, out, _, _ = run_main([*cmd, *AT], keyfile, text)
    assert "[line over 8192 characters not shown]" in out
    assert "xxxx" not in out


def test_orphan_only_output_is_marked(keyfile):
    """If the only line could be a secret's value, the output says so."""
    _, out, _, _ = run_main(["logs", "etcd", *AT], keyfile, SECRET + "\n")
    assert SECRET not in out and "hidden" in out


def test_render_does_not_mask_twice(keyfile):
    """Masked output is printed as it is."""
    with mock.patch.object(TC.wrapperlib.masking, "mask",
                           side_effect=TC.wrapperlib.masking.mask) as mask:
        run_main(["mounts", *AT], keyfile, "a b\n")
    assert mask.call_count == 1
