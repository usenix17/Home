"""Shared helpers for the read-only triage query wrappers."""
import argparse
import sys
import urllib.error
import urllib.request

TIMEOUT_S = 15
MAX_OUTPUT = 50 * 1024
MAX_READ = 8 * 1024 * 1024
NOTE = "\n[output truncated at 50 KB]\n"


class WrapperError(Exception):
    """A failure to report as a single stderr line."""


def fetch(request: urllib.request.Request) -> bytes:
    """Performs the request and returns the (bounded) body.

    Args:
      request: A prepared request aimed at a fixed base URL.

    Returns:
      The response body bytes.

    Raises:
      WrapperError: On any HTTP or connection failure.
    """
    try:
        with urllib.request.urlopen(request, timeout=TIMEOUT_S) as resp:
            return resp.read(MAX_READ)
    except urllib.error.HTTPError as err:
        raise WrapperError(f"HTTP {err.code} {err.reason}") from err
    except (urllib.error.URLError, OSError) as err:
        raise WrapperError(f"request failed: {err}") from err


def emit(text: str) -> None:
    """Prints text, truncated to the output cap with a note."""
    data = text.encode("utf-8", "replace")
    if len(data) > MAX_OUTPUT:
        text = data[:MAX_OUTPUT].decode("utf-8", "ignore") + NOTE
    sys.stdout.write(text if text.endswith("\n") else text + "\n")


def report_error(err: object) -> int:
    """Writes a one-line error to stderr and returns exit code 1."""
    line = " ".join(str(err).split())
    sys.stderr.write(f"error: {line}\n")
    return 1


def minutes_arg(value: str) -> int:
    """argparse type for a look-back in minutes (1..1440)."""
    try:
        number = int(value)
    except ValueError as err:
        raise argparse.ArgumentTypeError("must be an integer") from err
    if not 1 <= number <= 1440:
        raise argparse.ArgumentTypeError("must be 1..1440")
    return number
