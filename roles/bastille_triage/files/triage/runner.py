"""Run `claude -p` headless and classify the outcome."""
import json
import os
import signal
import subprocess
import time
from dataclasses import dataclass
from typing import Optional

_DROPPED_ENV = ("ANTHROPIC_API_KEY", "ANTHROPIC_AUTH_TOKEN")
_USAGE_PHRASES = ("hit your session limit", "hit your weekly limit",
                  "usage limit")
_AUTH_PHRASES = ("oauth token revoked", "login expired", "time limit",
                 "invalid api key", "authentication")
_KILL_GRACE_S = 3


@dataclass
class RunResult:
    """Outcome of one claude run.

    Attributes:
        kind: One of ok, usage_limit, auth, timeout or error.
        result: The validated structured output when kind is ok.
        raw: Raw stdout/stderr text, or an error message.
        duration_s: Wall-clock seconds the run took.
    """
    kind: str
    result: Optional[dict]
    raw: str
    duration_s: float


def child_env(base: dict, token: str) -> dict:
    """Builds the child environment so the subscription token is used.

    Args:
        base: The parent environment.
        token: The Claude Code OAuth token.

    Returns:
        A copy without API credentials that outrank the OAuth token.
    """
    env = {k: v for k, v in base.items() if k not in _DROPPED_ENV}
    env["CLAUDE_CODE_OAUTH_TOKEN"] = token
    env["DISABLE_AUTOUPDATER"] = "1"
    return env


def _classify_text(text: str) -> Optional[str]:
    """Returns usage_limit or auth if the text matches a known phrase."""
    low = text.lower()
    if any(p in low for p in _USAGE_PHRASES):
        return "usage_limit"
    if any(p in low for p in _AUTH_PHRASES):
        return "auth"
    return None


def _classify(stdout: str, stderr: str) -> tuple:
    """Classifies finished output into (kind, structured_result)."""
    try:
        data = json.loads(stdout)
    except ValueError:
        data = None
    if isinstance(data, dict) and not data.get("is_error"):
        out = data.get("structured_output")
        if isinstance(data.get("structured_output"), dict):
            return "ok", out
    text = stderr
    if isinstance(data, dict):
        text += "\n" + str(data.get("result", ""))
    else:
        text += "\n" + stdout
    return _classify_text(text) or "error", None


def _kill_group(proc: subprocess.Popen) -> None:
    """Terminates the child's whole process group."""
    for sig in (signal.SIGTERM, signal.SIGKILL):
        try:
            os.killpg(proc.pid, sig)
        except ProcessLookupError:
            return
        if sig == signal.SIGTERM:
            time.sleep(_KILL_GRACE_S)
    proc.wait()


def _build_argv(prompt: str, cfg: dict) -> list:
    """Builds the claude command line."""
    with open(cfg["schema_path"], encoding="utf-8") as handle:
        schema = handle.read()
    argv = [cfg["claude_bin"], "-p", prompt, "--permission-mode", "dontAsk",
            "--max-turns", str(cfg.get("max_turns", 25)),
            "--output-format", "json", "--json-schema", schema]
    if cfg.get("model"):
        argv += ["--model", cfg["model"]]
    return argv


def run_claude(prompt: str, cfg: dict) -> RunResult:
    """Runs claude headless and classifies the result. Never raises.

    Args:
        prompt: The triage prompt.
        cfg: Config with claude_bin, workdir, schema_path, token_path,
            model, max_turns and timeout_s.

    Returns:
        A RunResult describing what happened.
    """
    start = time.monotonic()
    try:
        with open(cfg["token_path"], encoding="utf-8") as handle:
            token = handle.read().strip()
        argv = _build_argv(prompt, cfg)
        with subprocess.Popen(
                argv, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                text=True, start_new_session=True, cwd=cfg["workdir"],
                env=child_env(dict(os.environ), token)) as proc:
            try:
                stdout, stderr = proc.communicate(
                    timeout=cfg.get("timeout_s", 600))
            except subprocess.TimeoutExpired:
                _kill_group(proc)
                proc.communicate()
                return RunResult("timeout", None, "timed out",
                                 time.monotonic() - start)
        kind, result = _classify(stdout, stderr)
        return RunResult(kind, result, stdout + stderr,
                         time.monotonic() - start)
    except Exception as exc:  # pylint: disable=broad-exception-caught
        return RunResult("error", None, f"{type(exc).__name__}: {exc}",
                         time.monotonic() - start)
