"""Runs a read-only CLI with limits and prints its output masked."""
import sys
from typing import Callable

from triage import boundedrun
from triage import masking
from triage import wrapperlib

TIMEOUT = "/usr/bin/timeout"
OLDER = "[older output cut]\n"
HIDDEN = "[output hidden: it could be the rest of a secret]\n"


def bounded(cmd: list[str], timeout_s: int) -> list[str]:
    """Adds a backstop limit that outlives a SIGKILLed wrapper."""
    return [TIMEOUT, "-k", "2", str(timeout_s + 2), *cmd]


def decode(raw: bytes, cut: bool) -> str:
    """Decodes captured output, without the part line of a cut capture."""
    text = raw.decode("utf-8", "replace")
    return masking.drop_partial(text) if cut else text


def log_tail(text: str, lines: int) -> str:
    """Masks a log, then keeps its last whole lines within the cap."""
    text = masking.mask(masking.drop_orphans(text))
    text = "".join(text.splitlines(keepends=True)[-lines:])
    if len(text) > wrapperlib.MAX_OUTPUT:
        text = text[len(OLDER) - wrapperlib.MAX_OUTPUT:]
        text = OLDER + text.partition("\n")[2]
    return text


def fail(message: object, code: int = 1) -> int:
    """Reports a masked one-line error."""
    wrapperlib.report_error(masking.mask(str(message))[:1000])
    return code


def error_text(res: boundedrun.Result) -> str:
    """Returns the masked end of the command's stderr on one line."""
    text = masking.mask(decode(res.err, res.err_cut))
    return " ".join(text.split()[-150:])


def report(name: str, res: boundedrun.Result, timeout_s: int,
           render: Callable[[bytes, bool], str | None]) -> int:
    """Prints the masked outcome of a run.

    Args:
        name: The command's name.
        res: The outcome.
        timeout_s: The limit the command ran under.
        render: Turns (stdout, cut) into masked text, or None if it can't
            be printed safely.

    Returns:
        The wrapper's exit code.
    """
    if res.signum is not None:
        return 128 + res.signum
    if res.timed_out:
        return fail(f"{name} timed out after {timeout_s}s (unreachable?)")
    text = render(res.out, res.cut)
    if text is not None and res.out.strip() and not text.strip():
        text = HIDDEN
    if text:
        wrapperlib.write(text)
    if res.cut:
        return fail("output too long, stopped early")
    if res.code:
        return fail(f"{name} failed ({res.code}): {error_text(res)}")
    if text is None:
        return fail("output format not recognised, not shown")
    if res.err.strip():
        sys.stderr.write(error_text(res)[:1000] + "\n")
    return 0
