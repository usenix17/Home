"""Runs a command with bounded time, output and lifetime."""
import dataclasses
import os
import selectors
import signal
import subprocess
import time

_SIGNALS = (signal.SIGTERM, signal.SIGINT, signal.SIGHUP)
_POLL_S = 0.2
_CHUNK = 65536


@dataclasses.dataclass
class Result:  # pylint: disable=too-many-instance-attributes
    """Outcome of a bounded run.

    Attributes:
        out: Captured stdout, at most the limit.
        err: Captured stderr, at most the limit.
        code: Exit code, or None if the command was killed.
        cut: True if stdout passed its limit and the command was killed.
        err_cut: True if stderr passed its limit (the rest is dropped).
        timed_out: True if the command was killed at the deadline.
        signum: Signal that ended the run, if any.
    """
    out: bytes = b""
    err: bytes = b""
    code: int | None = None
    cut: bool = False
    err_cut: bool = False
    timed_out: bool = False
    signum: int | None = None


def _kill_group(proc: subprocess.Popen) -> None:
    """Kills the command's process group and reaps the command."""
    try:
        os.killpg(proc.pid, signal.SIGKILL)
    except (ProcessLookupError, PermissionError):
        proc.kill()
    proc.wait()


def _store(res: Result, name: str, data: bytes, limit: int) -> None:
    """Appends data to one captured stream within its limit."""
    have = getattr(res, name)
    setattr(res, name, (have + data)[:limit])
    if len(have) + len(data) > limit:
        setattr(res, "cut" if name == "out" else "err_cut", True)


def _collect(proc: subprocess.Popen, res: Result, deadline: float,
             limits: dict[str, int], caught: list[int]) -> None:
    """Reads both pipes until EOF, a limit, a signal or the deadline."""
    with selectors.DefaultSelector() as sel:
        sel.register(proc.stdout, selectors.EVENT_READ, "out")
        sel.register(proc.stderr, selectors.EVENT_READ, "err")
        while True:
            if sel.get_map():
                for key, _ in sel.select(_POLL_S):
                    data = os.read(key.fd, _CHUNK)
                    if data:
                        _store(res, key.data, data, limits[key.data])
                    else:
                        sel.unregister(key.fileobj)
            elif proc.poll() is not None:
                res.code = proc.returncode
                return
            else:
                time.sleep(_POLL_S / 10)
            if caught:
                res.signum = caught[0]
                return
            if res.cut:
                return
            if time.monotonic() > deadline:
                res.timed_out = True
                return


def run(cmd: list[str], env: dict[str, str], timeout_s: float,
        max_out: int, max_err: int) -> Result:
    """Runs cmd in its own process group and captures bounded output.

    The group is killed at the deadline, when stdout passes its limit, and
    when this process gets SIGTERM, SIGINT or SIGHUP.

    Args:
        cmd: Argument vector.
        env: Complete environment.
        timeout_s: Seconds before the command is killed.
        max_out: Largest stdout to keep, in bytes.
        max_err: Largest stderr to keep, in bytes.

    Returns:
        The result.

    Raises:
        OSError: If the command cannot be started.
    """
    res = Result()
    caught: list[int] = []
    old = {sig: signal.signal(sig, lambda num, _: caught.append(num))
           for sig in _SIGNALS}
    try:
        with subprocess.Popen(
                cmd, env=env, stdin=subprocess.DEVNULL,
                stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                start_new_session=True) as proc:
            try:
                _collect(proc, res, time.monotonic() + timeout_s,
                         {"out": max_out, "err": max_err}, caught)
            finally:
                if proc.returncode is None:
                    _kill_group(proc)
    finally:
        for sig, handler in old.items():
            signal.signal(sig, handler)
    return res
