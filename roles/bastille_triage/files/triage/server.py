"""HTTP receiver, worker loop and health check for the triage agent."""
import argparse
import dataclasses
import datetime
import hmac
import http.server
import json
import logging
import os
import secrets
import threading
import time
import urllib.request
from typing import Any, Callable, Optional

from triage.payloads import Trigger, parse_alertmanager, parse_gatus
from triage.policy import Batcher, DailyCap, Deduper
from triage.report import build_prompt, format_notice, format_report
from triage.runner import RunResult, run_claude

LOG = logging.getLogger("triage")
MAX_BODY = 1024 * 1024
MAX_BATCH = 30
PARSERS = {"/alertmanager": parse_alertmanager, "/gatus": parse_gatus}


def post_discord(url: str, content: str) -> None:
    """Posts a message to a Discord webhook without allowing mentions.

    Args:
        url: Webhook URL.
        content: Message text.
    """
    body = json.dumps({"content": content,
                       "allowed_mentions": {"parse": []}}).encode()
    req = urllib.request.Request(
        url, data=body, method="POST",
        headers={"Content-Type": "application/json",
                 "User-Agent": "triage-agent/1"})
    try:
        with urllib.request.urlopen(req, timeout=10):
            pass
    except (OSError, ValueError) as err:
        LOG.error("discord post failed: %s", err)


def _utc_date(now: float) -> datetime.date:
    """Returns the UTC date for an epoch time."""
    return datetime.datetime.fromtimestamp(
        now, datetime.timezone.utc).date()


class TriageApp:  # pylint: disable=too-many-instance-attributes
    """Owns the HTTP server, queue and worker state."""

    def __init__(self, cfg: dict[str, Any], runner: Callable[..., RunResult],
                 post: Callable[[str, str], None],
                 clock: Callable[[], float]) -> None:
        """Initializes state and binds the HTTP server.

        Args:
            cfg: Configuration dictionary.
            runner: Callable(prompt, cfg) -> RunResult.
            post: Callable(url, content) delivering a Discord message.
            clock: Callable returning epoch seconds.
        """
        self._cfg = cfg
        self._runner = runner
        self._post = post
        self._clock = clock
        state_dir = cfg["state_dir"]
        os.makedirs(state_dir, exist_ok=True)
        os.makedirs(cfg["log_dir"], exist_ok=True)
        self._lock = threading.Lock()
        self._batcher = Batcher(cfg["batch_window_s"])
        self._deduper = Deduper(cfg["dedupe_s"],
                                os.path.join(state_dir, "dedupe.json"))
        self._cap = DailyCap(cfg["daily_cap"],
                             os.path.join(state_dir, "cap.json"))
        self._queue: list[list[Trigger]] = []
        self.server = http.server.ThreadingHTTPServer(
            (cfg.get("listen_host", "0.0.0.0"), cfg["listen_port"]),
            self._make_handler())

    def _authorized(self, header: str) -> bool:
        """Checks a bearer header in constant time."""
        expected = f"Bearer {self._cfg['push_token']}"
        return hmac.compare_digest(header.encode(), expected.encode())

    def _accept(self, triggers: list[Trigger]) -> None:
        """Adds triggers to the batcher under the shared lock."""
        now = self._clock()
        with self._lock:
            for trig in triggers:
                self._batcher.add(trig, now)

    def _make_handler(self) -> type:
        """Builds the request handler class bound to this app."""
        app = self

        class Handler(http.server.BaseHTTPRequestHandler):
            """Handles webhook pushes and health probes."""

            timeout = 10

            def log_message(  # pylint: disable=arguments-differ
                    self, fmt: str, *args: Any) -> None:
                """Routes access logs to the logger."""
                LOG.debug("http: " + fmt, *args)

            def _reply(self, code: int, body: Optional[dict] = None) -> None:
                """Sends a JSON response."""
                data = json.dumps(body or {}).encode()
                self.send_response(code)
                self.send_header("Content-Type", "application/json")
                self.send_header("Content-Length", str(len(data)))
                self.end_headers()
                self.wfile.write(data)

            def do_GET(self) -> None:  # pylint: disable=invalid-name
                """Serves /health."""
                if self.path != "/health":
                    self._reply(404)
                    return
                code, body = app.health()
                self._reply(code, body)

            def do_POST(self) -> None:  # pylint: disable=invalid-name
                """Accepts authenticated alert webhooks."""
                parser = PARSERS.get(self.path)
                if parser is None:
                    self._reply(404)
                    return
                if not app._authorized(  # pylint: disable=protected-access
                        self.headers.get("Authorization", "")):
                    self._reply(401)
                    return
                try:
                    length = int(self.headers.get("Content-Length", "0"))
                except ValueError:
                    length = -1
                if length < 0:
                    self._reply(400)
                    return
                if length > MAX_BODY:
                    self._reply(413)
                    return
                try:
                    body = json.loads(self.rfile.read(length))
                    # pylint: disable-next=protected-access
                    app._accept(parser(body))
                except Exception:  # pylint: disable=broad-exception-caught
                    LOG.warning("rejected malformed payload", exc_info=True)
                    self._reply(400)
                    return
                self._reply(202)

        return Handler

    def enqueue_ready(self) -> None:
        """Moves a ready batch into the queue, dropping the oldest."""
        now = self._clock()
        with self._lock:
            batch = self._batcher.ready(now)
            if not batch:
                return
            if len(batch) > MAX_BATCH:
                extra = len(batch) - MAX_BATCH
                batch = batch[:MAX_BATCH] + [Trigger(
                    "triage", f"triage:omitted:{int(now)}",
                    f"+{extra} more alerts omitted", "", {})]
            self._queue.append(batch)
            while len(self._queue) > self._cfg["queue_max"]:
                dropped = self._queue.pop(0)
                LOG.warning("queue full, dropped batch of %d", len(dropped))

    def queued(self) -> list[list[Trigger]]:
        """Returns a copy of the queued batches, oldest first."""
        with self._lock:
            return list(self._queue)

    def _write_log(self, triggers: list[Trigger], prompt: str,
                   res: RunResult, now: float) -> None:
        """Writes one run log file."""
        day_dir = os.path.join(self._cfg["log_dir"],
                               _utc_date(now).isoformat())
        os.makedirs(day_dir, exist_ok=True)
        run_id = f"{int(now)}-{secrets.token_hex(3)}"
        record = {"triggers": [dataclasses.asdict(t) for t in triggers],
                  "prompt": prompt, "raw": res.raw, "kind": res.kind,
                  "duration_s": res.duration_s, "result": res.result}
        try:
            with open(os.path.join(day_dir, f"{run_id}.json"), "w",
                      encoding="utf-8") as fh:
                json.dump(record, fh)
        except OSError as err:
            LOG.error("run log write failed: %s", err)

    def _say(self, content: str) -> None:
        """Posts to the configured Discord webhook."""
        self._post(self._cfg["discord_webhook"], content)

    def tick(self) -> None:
        """Runs one worker step: enqueue, then process one batch."""
        self.enqueue_ready()
        with self._lock:
            if not self._queue:
                return
            popped = self._queue.pop(0)
        now = self._clock()
        batch = [t for t in popped
                 if not self._deduper.seen_recently(t.key, now)]
        if not batch:
            return
        if not self._cap.try_take(now):
            if self._cap.notice_due(now):
                self._say(format_notice("cap", batch))
            return
        keys = {t.key for t in batch}
        with self._lock:
            self._batcher.set_inflight(keys)
        prompt = build_prompt(batch)
        try:
            res = self._runner(prompt, self._cfg)
        finally:
            done = self._clock()
            for key in keys:
                self._deduper.mark(key, done)
            with self._lock:
                self._batcher.set_inflight(None)
        self._write_log(batch, prompt, res, done)
        if res.kind == "ok":
            self._say(format_report(res.result or {}, batch))
        else:
            self._say(format_notice(res.kind, batch))

    def health(self) -> tuple[int, dict[str, Any]]:
        """Reports readiness and token lifetime.

        Returns:
            (HTTP status, body). 503 when the token is missing, claude is
            not executable or the token is inside its warning window.
        """
        cfg = self._cfg
        problems = []
        try:
            with open(cfg["token_path"], encoding="utf-8") as fh:
                if not fh.read().strip():
                    problems.append("token file empty")
        except OSError:
            problems.append("token file missing")
        if not os.access(cfg["claude_bin"], os.X_OK):
            problems.append("claude not executable")
        created = datetime.date.fromisoformat(cfg["token_created"])
        expires = created + datetime.timedelta(
            days=cfg["token_lifetime_days"])
        days_left = (expires - _utc_date(self._clock())).days
        if days_left <= cfg["warn_days"]:
            problems.append("token near expiry")
        body = {"status": "unhealthy" if problems else "ok",
                "days_left": days_left, "problems": problems}
        return (503 if problems else 200), body


def make_app(cfg: dict[str, Any],
             runner: Callable[..., RunResult] = run_claude,
             post: Callable[[str, str], None] = post_discord,
             clock: Callable[[], float] = time.time) -> TriageApp:
    """Builds a TriageApp with its HTTP server bound.

    Args:
        cfg: Configuration dictionary.
        runner: Callable(prompt, cfg) -> RunResult.
        post: Callable(url, content) delivering a Discord message.
        clock: Callable returning epoch seconds.

    Returns:
        The app; call app.server.serve_forever() and app.tick().
    """
    return TriageApp(cfg, runner, post, clock)


def _loop(step: Callable[[], None]) -> None:
    """Calls step every second forever, logging any exception."""
    while True:
        try:
            step()
        except Exception:  # pylint: disable=broad-exception-caught
            LOG.exception("%s failed", getattr(step, "__name__", "step"))
        time.sleep(1)


def main(argv: Optional[list[str]] = None) -> int:
    """Runs the receiver and worker until interrupted.

    Args:
        argv: Command-line arguments; defaults to sys.argv.

    Returns:
        Process exit code.
    """
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--config", required=True, help="JSON config path")
    args = parser.parse_args(argv)
    logging.basicConfig(level=logging.INFO,
                        format="%(asctime)s %(levelname)s %(message)s")
    with open(args.config, encoding="utf-8") as fh:
        cfg = json.load(fh)
    app = make_app(cfg)
    threading.Thread(target=app.server.serve_forever, daemon=True).start()
    threading.Thread(target=_loop, args=(app.enqueue_ready,),
                     daemon=True).start()
    try:
        _loop(app.tick)
    except KeyboardInterrupt:
        app.server.shutdown()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
