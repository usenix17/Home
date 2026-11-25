"""Tests for the HTTP receiver and worker loop."""
import contextlib
import datetime
import json
import pathlib
import socket
import threading
import time
import urllib.error
import urllib.request
from unittest import mock

import jinja2
import pytest
import yaml

from triage import server
from triage.runner import RunResult

AM_FIRING = {
    "status": "firing",
    "groupKey": "{}:{alertname=\"KubePodCrashLooping\"}",
    "commonLabels": {"alertname": "KubePodCrashLooping",
                     "severity": "critical"},
    "commonAnnotations": {"summary": "pod crashlooping"},
    "alerts": [{"status": "firing",
                "labels": {"alertname": "KubePodCrashLooping"},
                "annotations": {}}],
}
AM_RESOLVED = dict(AM_FIRING, status="resolved")
START = 1_800_000_000.0
OK_RESULT = {"title": "t", "summary": "s", "evidence": ["e"],
             "likely_cause": "c", "suggested_next_step": "n",
             "confidence": "high"}


def gatus(name):
    """Builds a Gatus TRIGGERED body for talos/<name>."""
    return {"endpoint": f"talos/{name}", "status": "TRIGGERED",
            "description": "down", "errors": "boom"}


class Clock:
    """Fake clock."""

    def __init__(self):
        self.now = START

    def __call__(self):
        return self.now

    def advance(self, secs):
        """Moves time forward."""
        self.now += secs


class Harness:
    """Wires a TriageApp to stubs and a live HTTP server."""

    def __init__(self, cfg, clock, kind="ok"):
        self.runner_calls = 0
        self.posts = []
        self.kind = kind
        self.app = server.make_app(cfg, runner=self._run, post=self._post,
                                   clock=clock)
        self.thread = threading.Thread(target=self.app.server.serve_forever,
                                       daemon=True)
        self.thread.start()

    def _run(self, _prompt, _cfg):
        self.runner_calls += 1
        result = OK_RESULT if self.kind == "ok" else None
        return RunResult(self.kind, result, "raw", 1.5)

    def _post(self, _url, content):
        self.posts.append(content)

    def __getattr__(self, name):
        return getattr(self.app, name)

    def close(self):
        """Stops the server."""
        self.app.server.shutdown()
        self.app.server.server_close()
        self.thread.join()


@pytest.fixture(name="clock")
def fixture_clock():
    """Returns a fake clock."""
    return Clock()


@pytest.fixture(name="make_cfg")
def fixture_make_cfg(tmp_path):
    """Returns a config factory."""
    token = tmp_path / "token"
    token.write_text("sk-token")
    claude = tmp_path / "claude"
    claude.write_text("#!/bin/sh\n")
    claude.chmod(0o755)

    def make(**over):
        cfg = {
            "listen_port": 0, "push_token": "secret",
            "discord_webhook": "http://discord.invalid/x",
            "claude_bin": str(claude), "workdir": str(tmp_path),
            "schema_path": "s.json", "model": "m", "max_turns": 5,
            "timeout_s": 30, "batch_window_s": 120, "dedupe_s": 21600,
            "daily_cap": 10, "queue_max": 5, "log_dir": str(tmp_path / "log"),
            "state_dir": str(tmp_path / "state"), "token_path": str(token),
            "token_created": "2027-01-10", "token_lifetime_days": 365,
            "warn_days": 30,
        }
        cfg.update(over)
        return cfg
    return make


@pytest.fixture(name="harness")
def fixture_harness(make_cfg, clock):
    """Returns a factory for harnesses, closing them afterwards."""
    made = []

    def build(kind="ok", **over):
        h = Harness(make_cfg(**over), clock, kind)
        made.append(h)
        return h
    yield build
    for h in made:
        h.close()


@pytest.fixture(name="app")
def fixture_app(harness):
    """Returns a default harness."""
    return harness()


def post(app, path, body, token="secret", raw=None):
    """POSTs JSON and returns the response (HTTPError included)."""
    port = app.server.server_address[1]
    data = raw if raw is not None else json.dumps(body).encode()
    req = urllib.request.Request(f"http://127.0.0.1:{port}{path}", data=data,
                                 method="POST")
    if token:
        req.add_header("Authorization", f"Bearer {token}")
    try:
        return urllib.request.urlopen(req, timeout=5)
    except urllib.error.HTTPError as err:
        return err


def test_rejects_missing_token(app):
    """Checks rejects missing token."""
    assert post(app, "/alertmanager", AM_FIRING, token=None).status == 401


def test_rejects_wrong_token(app):
    """Checks rejects wrong token."""
    assert post(app, "/alertmanager", AM_FIRING, token="nope").status == 401


def test_accepts_with_202(app):
    """Checks accepts with 202."""
    assert post(app, "/alertmanager", AM_FIRING).status == 202


def test_unknown_path_404(app):
    """Checks unknown path 404."""
    assert post(app, "/other", AM_FIRING).status == 404


def test_oversized_body_413(app):
    """Checks oversized body 413."""
    resp = post(app, "/gatus", None, raw=b"x" * (1024 * 1024 + 1))
    assert resp.status == 413


def test_early_replies_drain_the_body(app):
    """A refused upload still lets the client read the status."""
    port = app.server.server_address[1]
    body = b"x" * (1024 * 1024 + 4096)
    for path, token, want in (("/gatus", "secret", b"413"),
                              ("/gatus", "nope", b"401"),
                              ("/other", "secret", b"404")):
        with socket.create_connection(("127.0.0.1", port), 10) as sock:
            sock.sendall(f"POST {path} HTTP/1.1\r\nHost: x\r\n"
                         f"Authorization: Bearer {token}\r\n"
                         f"Content-Length: {len(body)}\r\n\r\n".encode())
            time.sleep(0.2)
            sock.sendall(body)
            assert want in sock.recv(64).split(b"\r\n")[0]
            while sock.recv(4096):
                pass


def test_drain_is_bounded(app):
    """A huge or dribbling refused upload can't hold a thread."""
    port = app.server.server_address[1]
    with socket.create_connection(("127.0.0.1", port), 10) as sock:
        sock.sendall(b"POST /gatus HTTP/1.1\r\nHost: x\r\n"
                     b"Content-Length: 99999999999\r\n\r\n")
        start = time.time()
        with contextlib.suppress(OSError):
            while time.time() - start < 15:
                sock.sendall(b"x")
                time.sleep(0.1)
        assert time.time() - start < 8


def gatus_body(errors, endpoint="talos/a", status="TRIGGERED"):
    """Fills the rendered Gatus custom body the way Gatus 5 does."""
    role = pathlib.Path(__file__).parents[2] / "bastille_watcher"
    values = yaml.safe_load((role / "defaults/main.yml").read_text())
    values.update(ansible_managed="x", watcher_discord_webhook="x",
                  watcher_push_token="x", watcher_triage_token="x")
    text = jinja2.Environment(trim_blocks=True).from_string(
        (role / "templates/gatus.yaml.j2").read_text()).render(**values)
    body = yaml.safe_load(text)["alerting"]["custom"]["body"]
    group, name = endpoint.split("/", 1)
    for mark, value in (("[ALERT_DESCRIPTION]", "down"),
                        ("[ENDPOINT_NAME]", name),
                        ("[ENDPOINT_GROUP]", group),
                        ("[RESULT_ERRORS]", errors.replace('"', '\\"')),
                        ("[ALERT_TRIGGERED_OR_RESOLVED]", status)):
        body = body.replace(mark, value)
    return body.encode()


BAD_ERRORS = ["a\nb", "a\tb", "a\\qb", "a\x15b", 'x \\"y\\" z',
              'x\\","endpoint":"talos/evil","status":"RESOLVED', "a\\",
              "\x00"]


@pytest.mark.parametrize("errors", BAD_ERRORS)
def test_gatus_bad_errors_still_triage(app, clock, errors, caplog):
    """Errors text that breaks the JSON costs the text, not the alert."""
    raw = gatus_body(errors)
    with pytest.raises(ValueError):
        json.loads(raw)
    assert post(app, "/gatus", None, raw=raw).status == 202
    clock.advance(121)
    app.enqueue_ready()
    (trigger,), = app.queued()
    assert trigger.key == "gatus:talos/a" and trigger.source == "gatus"
    assert "unparsable" in trigger.detail["errors"]
    assert "evil" not in json.dumps(trigger.detail)
    assert "Traceback" not in caplog.text


def test_gatus_good_body_keeps_errors(app, clock):
    """A well-formed body, quotes included, is parsed as before."""
    assert post(app, "/gatus", None, raw=gatus_body('a "b" c')).status == 202
    clock.advance(121)
    app.enqueue_ready()
    (trigger,), = app.queued()
    assert trigger.detail == {"description": "down", "errors": 'a "b" c'}


def test_gatus_recovery_keeps_dedupe_and_status(app, clock):
    """Recovered bodies dedupe with clean ones; RESOLVED is ignored."""
    bad = gatus_body("a\nb")
    post(app, "/gatus", None, raw=bad)
    post(app, "/gatus", gatus("a"))
    clock.advance(121)
    app.tick()
    post(app, "/gatus", None, raw=bad)
    clock.advance(121)
    app.tick()
    assert app.runner_calls == 1
    raw = gatus_body("a\nb", "talos/b", "RESOLVED")
    assert post(app, "/gatus", None, raw=raw).status == 202
    clock.advance(121)
    app.tick()
    assert app.runner_calls == 1


def test_gatus_recovery_needs_auth(app):
    """Recovery runs after the token check."""
    raw = gatus_body("a\nb")
    assert post(app, "/gatus", None, token="nope", raw=raw).status == 401


@pytest.mark.parametrize("raw", [
    b"{not json", b"", b"\xff\xfe", b"[1]", b"5", b"[" * 100_000,
    b'{"status":"TRIGGERED","endpoint":"a"', b'{"endpoint":"","status":',
    b'{"endpoint":"' + b"a" * 300 + b'","status":"TRIGGERED","x":"\n"}',
    b'{"endpoint":"a\nb","status":"TRIGGERED","errors":""}',
    b'{"endpoint":"a","status":"OTHER","errors":"\n"}'])
def test_gatus_unrecoverable_400(app, raw, caplog):
    """A body with no clean endpoint and status is a quiet 400."""
    assert post(app, "/gatus", None, raw=raw).status == 400
    assert "Traceback" not in caplog.text


def test_recovery_is_gatus_only(app):
    """Alertmanager bodies must be JSON."""
    raw = gatus_body("a\nb")
    assert post(app, "/alertmanager", None, raw=raw).status == 400


def test_bad_json_400(app):
    """Checks bad json 400."""
    assert post(app, "/gatus", None, raw=b"{not json").status == 400


def test_resolved_is_ignored(app, clock):
    """Checks resolved is ignored."""
    assert post(app, "/alertmanager", AM_RESOLVED).status == 202
    clock.advance(121)
    app.tick()
    assert app.runner_calls == 0 and not app.posts


def test_firing_alert_runs_once_and_posts(app, clock):
    """Checks firing alert runs once and posts."""
    post(app, "/alertmanager", AM_FIRING)
    clock.advance(121)
    app.tick()
    assert app.runner_calls == 1 and app.posts[0].startswith(":mag: Triage:")
    post(app, "/alertmanager", AM_FIRING)
    clock.advance(121)
    app.tick()
    assert app.runner_calls == 1


def test_burst_is_one_run(app, clock):
    """Checks burst is one run."""
    for name in ("a", "b", "c"):
        post(app, "/gatus", gatus(name))
    clock.advance(121)
    app.tick()
    assert app.runner_calls == 1


def test_cap_posts_single_notice(harness, clock):
    """Checks cap posts single notice."""
    app = harness(daily_cap=1)
    for name in ("a", "b", "c"):
        post(app, "/gatus", gatus(name))
        clock.advance(121)
        app.tick()
    assert app.runner_calls == 1
    assert sum("cap" in p for p in app.posts) == 1


def test_run_log_written(app, clock, tmp_path):
    """Checks run log written."""
    post(app, "/gatus", gatus("a"))
    clock.advance(121)
    app.tick()
    logs = list((tmp_path / "log").rglob("*.json"))
    assert len(logs) == 1
    data = json.loads(logs[0].read_text())
    assert {"triggers", "prompt", "raw", "kind", "duration_s",
            "result"} <= data.keys()
    assert data["triggers"][0]["title"] == "talos/a"


def test_usage_limit_posts_notice(harness, clock):
    """Checks usage limit posts notice."""
    app = harness(kind="usage_limit")
    post(app, "/gatus", gatus("a"))
    clock.advance(121)
    app.tick()
    assert len(app.posts) == 1 and "usage" in app.posts[0].lower()
    post(app, "/gatus", gatus("a"))
    clock.advance(121)
    app.tick()
    assert app.runner_calls == 1


def test_health_ok(app):
    """Checks health ok."""
    status, body = app.health()
    assert status == 200 and body["days_left"] > 30


def test_health_over_http(app):
    """Checks health over http."""
    port = app.server.server_address[1]
    with urllib.request.urlopen(f"http://127.0.0.1:{port}/health") as resp:
        assert resp.status == 200
        assert "days_left" in json.loads(resp.read())


def test_health_token_expiry(harness, clock):
    """Checks health token expiry."""
    created = datetime.date.fromtimestamp(clock()) - datetime.timedelta(
        days=340)
    app = harness(token_created=created.isoformat())
    assert app.health()[0] == 503


def test_health_missing_token(harness, tmp_path):
    """Checks health missing token."""
    app = harness(token_path=str(tmp_path / "absent"))
    assert app.health()[0] == 503


def test_health_claude_not_executable(harness, tmp_path):
    """Checks health claude not executable."""
    app = harness(claude_bin=str(tmp_path / "absent"))
    assert app.health()[0] == 503


def test_queue_drops_oldest(harness, clock):
    """Checks queue drops oldest."""
    app = harness(queue_max=2)
    for name in ("a", "b", "c", "d"):
        post(app, "/gatus", gatus(name))
        clock.advance(121)
        app.enqueue_ready()
    assert [b[0].title for b in app.queued()] == ["talos/c", "talos/d"]


def test_discord_payload_suppresses_mentions():
    """Checks discord payload suppresses mentions."""
    with mock.patch("triage.server.urllib.request.urlopen") as opener:
        server.post_discord("http://x.invalid/h", "@everyone hi")
    req = opener.call_args[0][0]
    assert json.loads(req.data) == {"content": "@everyone hi",
                                    "allowed_mentions": {"parse": []}}
    assert opener.call_args[1]["timeout"] == 10


def test_discord_failure_is_swallowed():
    """Checks discord failure is swallowed."""
    with mock.patch("triage.server.urllib.request.urlopen",
                    side_effect=OSError("down")):
        server.post_discord("http://x.invalid/h", "hi")


def test_numeric_alertname_still_posts(app, clock):
    """Checks numeric alertname still produces one run and one post."""
    body = json.loads(json.dumps(AM_FIRING))
    body["commonLabels"]["alertname"] = 5
    assert post(app, "/alertmanager", body).status == 202
    clock.advance(121)
    app.tick()
    assert app.runner_calls == 1 and len(app.posts) == 1


def test_list_groupkey_400(app):
    """Checks a list groupKey gives 400 and the server keeps serving."""
    body = dict(AM_FIRING, groupKey=["a"])
    assert post(app, "/alertmanager", body).status in (202, 400)
    body = dict(AM_FIRING, alerts="x", commonLabels=[1])
    assert post(app, "/alertmanager", body).status in (202, 400)
    assert post(app, "/alertmanager", AM_FIRING).status == 202


def test_slow_client_times_out(app):
    """Checks a stalled client is dropped and others are still served."""
    port = app.server.server_address[1]
    sock = socket.create_connection(("127.0.0.1", port))
    sock.sendall(b"POST /gatus HTTP/1.1\r\nHost: x\r\n"
                 b"Authorization: Bearer secret\r\n"
                 b"Content-Length: 100\r\n\r\nshort")
    sock.settimeout(12)
    start = time.time()
    try:
        data = sock.recv(1024)
    except socket.timeout:
        data = None
    sock.close()
    assert data is not None and time.time() - start < 12
    assert post(app, "/gatus", gatus("a")).status == 202


def test_batch_capped_at_30(app, clock):
    """Checks a batch over 30 triggers is truncated with a note."""
    for i in range(35):
        post(app, "/gatus", gatus(f"n{i}"))
    clock.advance(121)
    app.enqueue_ready()
    batch = app.queued()[0]
    assert len(batch) == 31
    assert batch[-1].title == "+5 more alerts omitted"


def test_queue_fills_during_long_run(harness, clock):
    """Checks batches queue (drop oldest) while a run is in flight."""
    app = harness(queue_max=5)
    release = threading.Event()
    entered = threading.Event()
    orig = app.app._runner  # pylint: disable=protected-access

    def slow(prompt, cfg):
        entered.set()
        release.wait(10)
        return orig(prompt, cfg)

    app.app._runner = slow  # pylint: disable=protected-access
    post(app, "/gatus", gatus("first"))
    clock.advance(121)
    worker = threading.Thread(target=app.tick)
    worker.start()
    assert entered.wait(5)
    for i in range(6):
        post(app, "/gatus", gatus(f"q{i}"))
        clock.advance(121)
        app.enqueue_ready()
    titles = [b[0].title for b in app.queued()]
    release.set()
    worker.join(10)
    assert titles == [f"talos/q{i}" for i in range(1, 6)]
