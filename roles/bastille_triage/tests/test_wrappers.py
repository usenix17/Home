"""Tests for the read-only query wrapper scripts."""
import base64
import contextlib
import importlib.machinery
import importlib.util
import io
import json
import os
import urllib.error
import urllib.parse
from unittest import mock

import pytest

BIN = os.path.join(
    os.path.dirname(os.path.dirname(__file__)), "files", "bin")


def load(name):
    """Loads a wrapper script as a module."""
    path = os.path.join(BIN, name)
    loader = importlib.machinery.SourceFileLoader(
        name.replace("-", "_"), path)
    spec = importlib.util.spec_from_loader(loader.name, loader)
    module = importlib.util.module_from_spec(spec)
    loader.exec_module(module)
    return module


def fake_response(body):
    """Builds a urlopen return value holding body."""
    if not isinstance(body, bytes):
        body = json.dumps(body).encode()
    resp = mock.MagicMock()
    resp.read.side_effect = lambda n=-1: body if n < 0 else body[:n]
    resp.__enter__.return_value = resp
    return resp


def run(name, argv, body=b"{}"):
    """Runs a script; returns (rc, stdout, captured Request or None)."""
    module = load(name)
    out = io.StringIO()
    with mock.patch("urllib.request.urlopen") as urlopen, \
            contextlib.redirect_stdout(out):
        urlopen.return_value = fake_response(body)
        code = module.main(argv)
    req = urlopen.call_args[0][0] if urlopen.call_args else None
    return code, out.getvalue(), req


@pytest.fixture(name="tokenfile")
def tokenfile_fixture(tmp_path, monkeypatch):
    """Points the graylog wrapper at a temporary token file."""
    path = tmp_path / "graylog.token"
    path.write_text("s3cret\n")
    monkeypatch.setenv("TRIAGE_GRAYLOG_TOKEN_FILE", str(path))
    return path


def test_promql_builds_get_only():
    """Instant queries are a plain GET to the fixed host."""
    code, _, req = run("triage-promql", ["up == 0"])
    assert code == 0
    assert req.get_method() == "GET"
    parts = urllib.parse.urlsplit(req.full_url)
    assert parts.scheme == "https"
    assert parts.netloc == "prometheus.starnix.net"
    assert parts.path == "/api/v1/query"
    assert urllib.parse.parse_qs(parts.query)["query"] == ["up == 0"]


def test_promql_range_uses_query_range():
    """A range query hits query_range with bounded points."""
    _, _, req = run("triage-promql", ["up", "--range", "60"])
    parts = urllib.parse.urlsplit(req.full_url)
    assert parts.path == "/api/v1/query_range"
    qs = urllib.parse.parse_qs(parts.query)
    points = (float(qs["end"][0]) - float(qs["start"][0])) / float(
        qs["step"][0])
    assert points <= 201


@pytest.mark.parametrize("value", ["0", "2000"])
def test_promql_range_bounds(value):
    """Out-of-range minutes are a usage error."""
    with pytest.raises(SystemExit) as exc:
        run("triage-promql", ["up", "--range", value])
    assert exc.value.code == 2


@pytest.mark.parametrize(
    "name", ["triage-promql", "triage-gatus", "triage-graylog"])
def test_no_url_override(name):
    """No script accepts a URL option."""
    argv = ["--url", "http://x"]
    if name != "triage-gatus":
        argv.insert(0, "q")
    with pytest.raises(SystemExit) as exc:
        run(name, argv)
    assert exc.value.code == 2


def test_graylog_limit_capped(tokenfile):
    """A huge --limit is clamped to 200."""
    assert tokenfile
    _, _, req = run("triage-graylog", ["err", "--limit", "5000"])
    body = json.loads(req.data)
    types = body["queries"][0]["search_types"]
    assert types[0]["limit"] == 200


def test_graylog_fixed_endpoint_and_auth(tokenfile):
    """The single POST goes to the fixed URL with basic auth."""
    assert tokenfile
    code, out, req = run(
        "triage-graylog", ["level:3", "--minutes", "30"],
        {"results": {}})
    assert code == 0
    assert req.full_url == (
        "http://192.168.1.151:9000/api/views/search/sync")
    assert req.get_method() == "POST"
    want = "Basic " + base64.b64encode(b"s3cret:token").decode()
    assert req.get_header("Authorization") == want
    assert req.get_header("X-requested-by") == "triage"
    body = json.loads(req.data)
    query = body["queries"][0]
    assert query["query"]["query_string"] == "level:3"
    assert query["timerange"] == {"type": "relative", "range": 1800}
    assert out is not None


def test_graylog_token_is_the_agents():
    """The token sits with the agent's credentials, not the receiver's."""
    assert load("triage-graylog").TOKEN_FILE == (
        "/usr/local/etc/triage-agent/graylog.token")


def test_graylog_prints_messages(tokenfile):
    """Messages print one per line, message text truncated."""
    assert tokenfile
    msgs = [{"message": {"timestamp": "T1", "source": "h1",
                         "message": "x" * 500}}]
    resp = {"results": {"q": {"search_types": {"s": {
        "messages": msgs}}}}}
    _, out, _ = run("triage-graylog", ["q"], resp)
    line = out.strip()
    assert line.startswith("T1 h1 ")
    assert len(line) == len("T1 h1 ") + 300


def test_output_capped():
    """Output over 50 KB is truncated with a note."""
    _, out, _ = run("triage-promql", ["up"], b"a" * 100_000)
    assert len(out.encode()) <= 50 * 1024 + 200
    assert "truncated" in out


def test_gatus_failing_filter():
    """--failing hides healthy endpoints."""
    data = [
        {"group": "g", "name": "good",
         "results": [{"success": True, "errors": []}]},
        {"group": "g", "name": "bad",
         "results": [{"success": False, "errors": ["boom", "x"]}]},
    ]
    _, out, req = run("triage-gatus", [], data)
    assert req.full_url == (
        "http://192.168.1.60:8080/api/v1/endpoints/statuses")
    assert "g/good OK" in out
    assert "g/bad FAIL boom" in out
    _, out, _ = run("triage-gatus", ["--failing"], data)
    assert "good" not in out
    assert "g/bad FAIL boom" in out


def test_http_error_one_line_exit1(capsys):
    """HTTP errors give one stderr line and exit 1."""
    module = load("triage-promql")
    err = urllib.error.HTTPError("u", 500, "Boom", {}, None)
    with mock.patch("urllib.request.urlopen", side_effect=err):
        code = module.main(["up"])
    assert code == 1
    captured = capsys.readouterr()
    assert captured.out == ""
    assert len(captured.err.strip().splitlines()) == 1


@pytest.mark.parametrize("name", [
    "triage-kubectl", "triage-promql", "triage-gatus", "triage-graylog",
    "triage-talosctl"])
def test_wrapper_shebang_is_pinned_and_isolated(name):
    """Wrappers ignore PYTHONPATH/user site: pinned python in -I mode."""
    with open(os.path.join(BIN, name), encoding="utf-8") as handle:
        assert handle.readline() == "#!/usr/local/bin/python3.11 -I\n"


def test_every_wrapper_is_covered():
    """The shebang test lists every wrapper in files/bin."""
    assert sorted(n for n in os.listdir(BIN) if n.startswith("triage-")) == [
        "triage-gatus", "triage-graylog", "triage-kubectl", "triage-promql",
        "triage-talosctl"]
