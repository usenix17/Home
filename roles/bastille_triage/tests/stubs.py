"""Stub binaries and processes for the wrapper tests."""
import contextlib
import json
import os
import shlex
import signal
import subprocess
import sys
import time

STUBBORN = "trap '' TERM; while :; do sleep 1; done"


def python_stub(path, streams, code=0):
    """Writes a stub that records its start and replays two streams.

    Args:
        path: Where to write the stub.
        streams: Its stdout (str or bytes) and stderr (str).
        code: Its exit code.
    """
    base = path.parent
    out, err = streams
    (base / "out").write_bytes(out if isinstance(out, bytes)
                               else out.encode())
    (base / "err").write_text(err)
    log = base / "log"
    log.unlink(missing_ok=True)
    path.write_text(
        f"#!{sys.executable} -I\n"
        "import json, os, sys\n"
        f"json.dump([sys.argv, dict(os.environ)], open({str(log)!r}, 'w'))\n"
        f"sys.stdout.buffer.write(open({str(base / 'out')!r}, 'rb').read())\n"
        f"sys.stderr.write(open({str(base / 'err')!r}).read())\n"
        f"sys.exit({code})\n")
    path.chmod(0o755)


def started(base):
    """Returns the stub's (argv, env), or None if it never ran."""
    log = base / "log"
    return json.loads(log.read_text()) if log.exists() else None


def start_wrapper(base, name, setup, args, body):
    """Starts a wrapper as a process with a shell-stub binary.

    Args:
        base: Temporary directory; the stub's pid goes to base/pid.
        name: The wrapper's name.
        setup: Python statements that prepare the module M.
        args: The wrapper's arguments.
        body: The stub's shell code; its path is in {stub} of setup.

    Returns:
        The process.
    """
    stub = base / "stub"
    pid = shlex.quote(str(base / "pid"))
    stub.write_text(f"#!/bin/sh\necho $$ > {pid}\n{body}\n")
    stub.chmod(0o755)
    here = os.path.dirname(os.path.abspath(__file__))
    code = (f"import sys; sys.path[:0] = [{here!r}]\n"
            "import conftest\n"
            "from test_wrappers import load\n"
            f"M = load({name!r})\n"
            + setup.format(stub=repr(str(stub)))
            + f"\nsys.exit(M.main({args!r}))\n")
    # pylint: disable-next=consider-using-with
    return subprocess.Popen([sys.executable, "-c", code], text=True,
                            stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                            start_new_session=True)


def child_pid(base):
    """Waits for the shell stub to start; returns its pid."""
    path = base / "pid"
    for _ in range(100):
        if path.exists() and path.read_text().strip():
            break
        time.sleep(0.1)
    time.sleep(0.3)
    return int(path.read_text())


def gone(pid, wait_s=5):
    """Waits until pid is gone; returns False if it stays alive."""
    for _ in range(wait_s * 10):
        try:
            os.kill(pid, 0)
        except ProcessLookupError:
            return True
        time.sleep(0.1)
    return False


def ends_after_sigkill(proc, base):
    """SIGKILLs the wrapper's group; returns True if the stub then ends."""
    pid = child_pid(base)
    os.killpg(proc.pid, signal.SIGKILL)
    proc.wait()
    try:
        os.kill(pid, 0)  # It outlives the wrapper, if only briefly.
        return gone(pid, 15)
    finally:
        with contextlib.suppress(ProcessLookupError):
            os.kill(pid, signal.SIGKILL)
