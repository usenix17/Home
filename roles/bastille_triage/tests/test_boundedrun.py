"""Tests for the bounded subprocess runner, with real processes."""
import os
import signal
import sys
import threading
import time

import pytest

from triage import boundedrun

ENV = {"PATH": "/usr/bin:/bin"}


def run(script, timeout_s=10, max_out=1000, max_err=1000):
    """Runs a Python script through the bounded runner."""
    return boundedrun.run([sys.executable, "-c", script], ENV, timeout_s,
                          max_out, max_err)


def alive(pid):
    """Returns True if pid is a running (not zombie) process."""
    try:
        with open(f"/proc/{pid}/stat", encoding="utf-8") as handle:
            return handle.read().rsplit(")", 1)[1].split()[0] != "Z"
    except OSError:
        return False


def wait_gone(pid):
    """Waits for pid to die."""
    for _ in range(50):
        if not alive(pid):
            return True
        time.sleep(0.1)
    return False


def test_plain_run():
    """Both streams and the exit code come back."""
    res = run("import sys; print('o'); print('e', file=sys.stderr);"
              "sys.exit(3)")
    assert (res.code, res.out, res.err) == (3, b"o\n", b"e\n")
    assert not res.cut and not res.timed_out and res.signum is None


def test_output_is_bounded_and_stopped():
    """An endless writer is cut at the limit and killed."""
    start = time.monotonic()
    res = run("import sys\nwhile True: sys.stdout.write('x' * 9000)",
              max_out=5000)
    assert res.cut and len(res.out) == 5000
    assert time.monotonic() - start < 5


def test_stderr_is_bounded():
    """Extra stderr is dropped without blocking the child."""
    res = run("import sys; sys.stderr.write('x' * 500000); print('done')")
    assert res.code == 0 and res.out == b"done\n" and len(res.err) == 1000
    assert res.err_cut


def test_exactly_at_the_limit_is_not_cut():
    """Output that just fits is complete."""
    assert not run("print('x' * 999)").cut


def test_timeout_kills_the_group(tmp_path):
    """A hang is bounded, and grandchildren die with the child."""
    pidfile = tmp_path / "pid"
    start = time.monotonic()
    res = run("import subprocess, time\n"
              "p = subprocess.Popen(['sleep', '60'])\n"
              f"open({str(pidfile)!r}, 'w').write(str(p.pid))\n"
              "time.sleep(60)", timeout_s=2)
    assert res.timed_out and time.monotonic() - start < 6
    assert wait_gone(int(pidfile.read_text()))


def test_closed_pipes_do_not_dodge_the_timeout():
    """A child that closes its output and hangs is still killed."""
    res = run("import os, time; os.close(1); os.close(2); time.sleep(60)",
              timeout_s=1)
    assert res.timed_out


@pytest.mark.parametrize("sig", [signal.SIGTERM, signal.SIGINT,
                                 signal.SIGHUP])
def test_signal_kills_the_child(tmp_path, sig):
    """A signal to the wrapper ends the child group; handlers return."""
    pidfile = tmp_path / "pid"
    old = signal.getsignal(sig)

    def fire():
        while not (pidfile.exists() and pidfile.read_text()):
            time.sleep(0.05)
        os.kill(os.getpid(), sig)

    thread = threading.Thread(target=fire)
    thread.start()
    res = run("import os, signal, time\n"
              "signal.signal(signal.SIGTERM, signal.SIG_IGN)\n"
              f"open({str(pidfile)!r}, 'w').write(str(os.getpid()))\n"
              "time.sleep(60)")
    thread.join()
    assert res.signum == sig and signal.getsignal(sig) is old
    assert wait_gone(int(pidfile.read_text()))


def test_missing_binary_raises():
    """A missing binary is an OSError and restores the handlers."""
    old = signal.getsignal(signal.SIGTERM)
    with pytest.raises(OSError):
        boundedrun.run(["/nonexistent/x"], ENV, 1, 10, 10)
    assert signal.getsignal(signal.SIGTERM) is old
