"""Tests for the secret masking used on talosctl output."""
import time

import pytest

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
