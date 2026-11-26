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
                         "message": "abcde fghi" * 50}}]
    resp = {"results": {"q": {"search_types": {"s": {
        "messages": msgs}}}}}
    _, out, _ = run("triage-graylog", ["q"], resp)
    line = out.strip()
    assert line.startswith("T1 h1 ")
    assert len(line) == len("T1 h1 ") + 300


def test_output_capped():
    """Output over 50 KB is truncated with a note."""
    _, out, _ = run("triage-promql", ["up"], b"a b c d e\n" * 10_000)
    assert 50_000 < len(out.encode()) <= 50 * 1024 + 200
    assert "truncated" in out


SECRET = "S3cr3tV4lueXq"


def leaks(text):
    """Returns True if any 4-character piece of SECRET is in text."""
    return any(SECRET[i:i + 4] in text for i in range(len(SECRET) - 3))


def vector(*metrics):
    """Builds an instant-query response."""
    return {"status": "success", "data": {
        "resultType": "vector",
        "result": [{"metric": m, "value": [1, "1"]} for m in metrics]}}


def test_emit_masks_before_the_cut(capsys):
    """The output cap may land anywhere around a secret."""
    lib = load("triage-promql").wrapperlib
    for pad in range(0, 60):
        fill = "y z\n" * (lib.MAX_OUTPUT // 4 - 12) + "w" * pad
        lib.emit(f"{fill} password={SECRET} x\nend\n")
        assert not leaks(capsys.readouterr().out)


def test_emit_bounds_what_it_masks():
    """Very long output is cut at a line before masking."""
    lib = load("triage-promql").wrapperlib
    out = io.StringIO()
    with contextlib.redirect_stdout(out), \
            mock.patch.object(lib.masking, "mask", side_effect=str) as mask:
        lib.emit("a b\n" * 2_000_000)
    assert len(mask.call_args[0][0]) <= lib.MAX_MASK + 100
    assert mask.call_args[0][0].split("\n")[-2] in ("a b", lib.CUT)


@pytest.mark.parametrize("text", ["x" * 9000, "x" * 5_000_000,
                                  "{" + "'a':1," * 900_000])
def test_emit_one_huge_line_says_so(capsys, text):
    """One overlong line becomes a marker, never silence."""
    lib = load("triage-promql").wrapperlib
    lib.emit(text)
    assert lib.masking.TOO_LONG in capsys.readouterr().out


def test_report_error_is_masked(capsys):
    """Error text is masked too."""
    lib = load("triage-promql").wrapperlib
    lib.report_error(f"bad url http://u:{SECRET}@h/ token={SECRET}")
    assert not leaks(capsys.readouterr().err)


@pytest.mark.parametrize("name, argv, body", [
    ("triage-promql", ["up"], vector({"password": SECRET})),
    ("triage-promql", ["up"], vector({"url": f"http://u:{SECRET}@h/"})),
    ("triage-promql", ["up"], f"x password={SECRET}\n".encode()),
    ("triage-gatus", [], [{"name": "n", "results": [
        {"success": False, "errors": [f"Get http://u:{SECRET}@h/"]}]}]),
    ("triage-gatus", [], [{"name": f"token={SECRET}", "results": []}]),
])
def test_wrappers_mask_their_output(name, argv, body):
    """Promql and Gatus output is masked."""
    _, out, _ = run(name, argv, body)
    assert not leaks(out) and "[redacted]" in out


def graylog(*texts):
    """Runs triage-graylog over messages; returns its output."""
    msgs = [{"message": {"timestamp": "T1", "source": "h1", "message": t}}
            for t in texts]
    resp = {"results": {"q": {"search_types": {"s": {"messages": msgs}}}}}
    return run("triage-graylog", ["q"], resp)[1]


def test_graylog_masks_before_the_message_cut(tokenfile):
    """The 300-character cut may land anywhere around a secret."""
    assert tokenfile
    for pad in range(0, 320):
        assert not leaks(graylog("y" * pad + f" password={SECRET} end"))


@pytest.mark.parametrize("text", [
    f"a\npassword:\n  {SECRET}\nb", f"password: two {SECRET}",
    f"x http://u:{SECRET}@h", f"{{\"token\":\"{SECRET}\"}}"])
def test_graylog_masks_messages(tokenfile, text):
    """A message's own lines are masked before they are joined."""
    assert tokenfile
    assert not leaks(graylog("ok a", text, "ok b"))


def test_graylog_overlong_message_is_marked(tokenfile):
    """One overlong message shows a marker and hides no other."""
    assert tokenfile
    out = graylog("ok a", "x" * 9000, "ok b").splitlines()
    assert out[0] == "T1 h1 ok a" and out[2] == "T1 h1 ok b"
    assert out[1] == "T1 h1 [line over 8192 characters not shown]"


def test_promql_prints_one_series_per_line():
    """Each series is its own JSON line, so one can't hide the rest."""
    metrics = [{"__name__": "up", "instance": f"n{i}"} for i in range(500)]
    _, out, _ = run("triage-promql", ["up"], vector(*metrics))
    lines = [x for x in out.splitlines() if '"metric"' in x]
    assert len(lines) == 500 and "[line over" not in out
    assert json.loads(lines[7])["metric"]["instance"] == "n7"
    assert "vector" in out.splitlines()[0] and "500" in out.splitlines()[0]


def test_promql_one_huge_series_hides_only_itself():
    """A series with an overlong label is marked; the others show."""
    _, out, _ = run("triage-promql", ["up"], vector(
        {"a": "1"}, {"b": "x" * 9000}, {"c": "3"}))
    lines = out.splitlines()
    assert json.loads(lines[1])["metric"] == {"a": "1"}
    assert lines[2].startswith("[line over")
    assert json.loads(lines[-1])["metric"] == {"c": "3"}


@pytest.mark.parametrize("key", ["values", "histograms"])
def test_promql_long_range_series_is_split(key):
    """A range series over the line limit prints in parts."""
    points = [[1795584309.123 + i, "0.12345678901234567890"]
              for i in range(400)]
    body = {"status": "success", "data": {
        "resultType": "matrix",
        "result": [{"metric": {"a": "1"}, key: points}]}}
    _, out, _ = run("triage-promql", ["up", "--range", "60"], body)
    lines = [json.loads(x) for x in out.splitlines()[1:]]
    assert lines[0]["metric"] == {"a": "1"} and "[line over" not in out
    assert [p for x in lines for p in x.get(key, [])] == points


@pytest.mark.parametrize("data, want", [
    ({"resultType": "scalar", "result": [1, "2"]}, '[1,"2"]'),
    ({"resultType": "vector", "result": []}, "0 series")])
def test_promql_other_results(data, want):
    """Scalars and empty results are shown, never silent."""
    _, out, _ = run("triage-promql", ["up"],
                    {"status": "success", "data": data})
    assert want in out


def test_promql_warnings_are_shown():
    """Prometheus warnings still reach the agent."""
    body = dict(vector({"a": "1"}), warnings=["partial data"])
    assert "partial data" in run("triage-promql", ["up"], body)[1]


@pytest.mark.parametrize("body", [[1], "x", 5, None, {}, {"data": 3},
                                  {"data": {"result": {"a": 1}}},
                                  {"data": {"result": [1, "a"]}}])
def test_promql_odd_bodies_are_shown(body):
    """Unexpected JSON is printed as it is."""
    code, out, _ = run("triage-promql", ["up"], json.dumps(body).encode())
    assert code == 0 and out.strip()


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
