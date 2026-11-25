"""Tests for the secret masking used on talosctl output."""
import base64
import random
import time

import pytest

from triage import masking
from triage.masking import mask

VALUE = "S3cr3tV4lue"


@pytest.mark.parametrize("line", [
    # key=value and key: value
    "siderolink.api=https://x:8090?jointoken={v} foo",
    "x?grpc_tunnel=true&jointoken={v}&y=1", "JOINTOKEN={v}",
    "x%26jointoken%3D{v}", "x%2526jointoken%253D{v}", "token => {v}",
    "jointoken=x%2B{v}%3D", "password: {v}", "password: two words {v}",
    "  db-password: 'a {v} b'", "tokenSecretKey: {v}", "api_key={v}",
    "privateKey: {v}", "token=a,{v}", "PASSWORD1={v}", "password=ab\"{v}",
    'env:["A_TOKEN=x {v}"]', "'DB_PASSWORD=a b {v}'", '"Token: a {v}"',
    # flags
    "cmd --token {v}", "cmd --token={v}", "redis --requirepass {v}",
    "redis-server [--requirepass {v}]", "cmd -password {v} x",
    "cmd --db-password 'a {v}'", "cmd --password \"a b,{v}\"",
    "requirepass {v}", "masterauth {v}",
    # environment forms
    "DB_SECRET={v}", "AWS_SECRET_ACCESS_KEY={v}", "ENC_KEY={v}",
    "CF_TOKEN={v}", "PGPASSWORD={v}", "DB_PASS={v}",
    '{{"name":"DB_PASSWORD","value":"{v}"}}',
    # URL userinfo
    "postgres://user:{v}@db:5432/x", "https://{v}@host/x",
    "redis://:{v}@host", "amqp://u:a/{v}@host", "x://u:p@{v}@host",
    # headers
    "Authorization: Bearer {v}", "Authorization: Basic {v}==",
    "authorization: Digest a {v}", 'curl -H "Authorization: Basic {v}" x',
    '"Authorization":["Basic {v}"]', "map[Authorization:[Bearer {v}]]",
    "Cookie: a=b; session={v}", "Bearer {v}",
    # JSON
    '"token":"{v}"', '\\"token\\":\\"{v}\\"', '"password":"a b, {v}"',
    '"password":"a\\"b {v}"', '\\"password\\":\\"a\\\\\\"b{v}\\"',
    '"tokens":["a","{v}"]', '"tokens":["a]","{v}"]',
    '["redis","--requirepass","{v}"]', '["a", "--token", "x {v}"]',
    '"secret":{{"a":"{v}"}}', '"token":"unterminated {v}',
    # bare tokens
    "got eyJhbGciOi{v}.eyJzdWIi{v}.{v}", "x eyJhbGciOi{v}", "sk-ant-{v}",
    # preshared keys
    "presharedKey: {v}", "preshared_key={v}", "preshared-key: {v}",
    "preshared key: {v}", '"presharedKey":"{v}"', "psk={v}", "PSK: {v}",
    '"psk":"{v}"', "wpa_psk={v}", "psk {v}", "preshared-key {v}",
    # control characters between key and value
    "token\x1b[0m=\x1b[1m{v}", "tok\x00en={v}",
    # PEM
    "-----BEGIN PRIVATE KEY-----\n{v}\n-----END PRIVATE KEY-----",
    "-----BEGIN PRIVATE KEY-----\n{v}", "{v}\n-----END PRIVATE KEY-----",
    # key and value on separate lines
    "key:\npassword:\n  {v}", "token:\n\n  {v}", "token:\n{v}",
    "token: |\n  a\n  {v}\nnext: x", "password: \"\n  {v}", "token:\r\n {v}",
    '  "token": [\n    "a",\n    "{v}"\n  ]', '"--token",\n"{v}"',
    "- name: DB_PASSWORD\n  value: {v}", "cmd --token \\\n  {v}",
    '"name": "X_TOKEN",\n"value": "{v}"',
    "kern: info: [2026-11-25T00:00:00Z]: token:\n"
    "kern: info: [2026-11-25T00:00:00Z]:   {v}",
])
def test_masked(line):
    """No form of a secret survives."""
    out = mask(line.format(v=VALUE))
    assert VALUE not in out and "[redacted]" in out


@pytest.mark.parametrize("line", [
    'secret "default/foo" not found', "token expired for node",
    "failed to get configmap: key not found", "MetadataToken rotation ok",
    "EXT4-fs (sdm): unmounting filesystem 31b639c9-6a25-475d-b509-82821ccd",
    "pulling ghcr.io/netbymatt/ws4kp:5.24.1", "eth0: renamed from tmp6a227",
    "GET https://192.168.7.203:6443/api/v1/nodes 200",
    '{"ts":1,"caller":"conn.go:353","msg":"Error on socket receive"}',
    "publicKey: abc", "basic authentication is disabled",
    "invalid bearer token\nnext line stays"])
def test_plain_text_kept(line):
    """Text that holds no secret is left alone."""
    assert mask(line) == line


def test_multi_line_mask_stops():
    """Masking a value on the next lines stops at the dedent."""
    out = mask("a: 1\ntoken:\n  x1y2z3\n  more\nnext: ok\n")
    assert out == "a: 1\ntoken:\n  [redacted]\n  [redacted]\nnext: ok\n"


@pytest.mark.parametrize("head", ["", "token", "token=", "--token "])
@pytest.mark.parametrize("chunk", [
    "a" * 1000, "-" * 1000, "://", "token", "secret=\"", "eyJ", "a:b@",
    "-----BEGIN x", "[\"", "\\", "token:\n", "--token ", "authorization:",
    " \t", "{"])
def test_mask_is_fast(head, chunk):
    """No input makes the patterns backtrack badly."""
    text = head + chunk * (200_000 // len(chunk))
    start = time.monotonic()
    mask(text)
    assert time.monotonic() - start < 5


EVIL = 'basic token=["\\=\\"'


def timed(text):
    """Returns (seconds, masked text)."""
    start = time.monotonic()
    out = mask(text)
    return time.monotonic() - start, out


def test_long_line_is_fast_and_hidden_whole():
    """A 256 KB line costs nothing and shows nothing."""
    took, out = timed("a b\n" + EVIL * (262_144 // len(EVIL)) + "\nc d\n")
    assert took < 2
    assert out == f"a b\n{masking.TOO_LONG}\nc d\n"


@pytest.mark.parametrize("unit", [
    EVIL, 'token=["', "token=[", 'token="', "token=\\\"", "token={", "a://b:",
    "--token ", "authorization: ", '"token=', "psk ", "basic a", "eyJ ",
    "-----BEGIN a-----", "-----END a-----", "x-----", "name: a_token, ",
    "token%3d", "token    ", "token,", "Aa1" * 13])
def test_many_long_lines_are_bounded(unit):
    """2 MiB of 8 KB lines stays well under the wrapper's time limit."""
    line = (unit * (8192 // len(unit) + 1))[:8191] + "\n"
    took, _ = timed(line * 256)
    assert took < 10


@pytest.mark.parametrize("size, hidden", [(8192, False), (8193, True)])
def test_line_limit_edge(size, hidden):
    """Only lines over the limit are replaced."""
    assert (mask(("a " * size)[:size]) == masking.TOO_LONG) is hidden


def test_long_line_ending_in_a_key():
    """The value under a hidden line's trailing key is still masked."""
    out = mask("x " * 5000 + f"password:\n  {VALUE}\nc d")
    assert out == f"{masking.TOO_LONG}\n  [redacted]\nc d"


def pem(lines=26, last="Qk9MbA=="):
    """Returns the lines of a fake PEM key."""
    rng = random.Random(1)
    body = [base64.b64encode(rng.randbytes(48)).decode()
            for _ in range(lines)]
    return ["-----BEGIN RSA PRIVATE KEY-----", *body, last,
            "-----END RSA PRIVATE KEY-----"]


@pytest.mark.parametrize("prefix", [
    "", "  ", "kern: info: [2026-11-25T00:00:00Z]: ", "2026-11-25Z stdout F ",
    '    "'])
def test_pem_cut_anywhere_is_masked(prefix):
    """A PEM key masks whole or with any lines missing at either end."""
    key = pem()
    for first in range(len(key) + 1):
        for end in (first + 1, first + 2, len(key) - 1, len(key)):
            part = key[first:end]
            out = mask("\n".join(["a b c", *(prefix + x for x in part),
                                  "d e f"]))
            lone = len(part) == 1 and part[0] == key[-2]
            for item in part:
                if not item.startswith("-----") and not lone:
                    assert item not in out, (first, end)
            assert out.startswith("a b c\n") and out.endswith("\nd e f")


@pytest.mark.parametrize("sep", ["\\n", "\\\\n", " ", "\\r\\n"])
def test_pem_on_one_line_is_masked(sep):
    """A PEM key in an escaped string masks whole or cut at either end."""
    key = pem(6)
    for part in (key, key[:-1], key[1:]):
        out = mask('{"msg":"got ' + sep.join(part) + '","level":"info"}')
        if sep == " " and part is not key:
            part = part[:-1] if part[-1] == "Qk9MbA==" else part
        for item in part:
            if not item.startswith("-----") and not (
                    sep == " " and item == "Qk9MbA=="):
                assert item not in out
        assert "info" in out


def test_encrypted_pem_is_masked():
    """PEM headers do not hide the body."""
    key = pem()
    key[1:1] = ["Proc-Type: 4,ENCRYPTED", "DEK-Info: AES-256-CBC,00", ""]
    out = mask("\n".join(key))
    assert not any(item in out for item in key[4:-1])


@pytest.mark.parametrize("marker", [
    "-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----",
    "got -----BEGIN x----- here", "got -----END x----- here"])
def test_unmatched_pem_marker_hides_nothing_else(marker):
    """A lone marker does not blank the lines around it."""
    before = [f"log line {i} before" for i in range(300)]
    after = [f"log line {i} after" for i in range(300)]
    out = mask("\n".join([*before, marker, *after])).split("\n")
    assert out[:300] == before and out[301:] == after


def test_lines_between_distant_markers_are_kept():
    """Only a key's body is masked between markers."""
    mid = [f"log line {i} middle" for i in range(50)]
    out = mask("\n".join(["-----BEGIN A-----", *mid, "-----END A-----"]))
    assert all(line in out for line in mid)
