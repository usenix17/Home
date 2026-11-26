"""Tests for payload parsing."""
from triage.payloads import parse_alertmanager, parse_gatus


def test_parse_alertmanager_group_and_resolved():
    """A firing group yields one trigger; resolved yields none."""
    body = {
        "status": "firing",
        "groupKey": "{}:{alertname=\"KubeNodeNotReady\"}",
        "alerts": [
            {"status": "firing",
             "labels": {"alertname": "KubeNodeNotReady",
                        "node": "talos-hb1-9mk", "severity": "warning"},
             "annotations": {}},
            {"status": "firing",
             "labels": {"alertname": "KubeNodeNotReady",
                        "node": "talos-zv2-ov6", "severity": "warning"},
             "annotations": {}}]}
    ts = parse_alertmanager(body)
    assert len(ts) == 1
    assert ts[0].key == body["groupKey"]
    assert ts[0].title == "KubeNodeNotReady"
    assert ts[0].severity == "warning"
    assert {a["labels"]["node"] for a in ts[0].detail["alerts"]} == {
        "talos-hb1-9mk", "talos-zv2-ov6"}
    assert not parse_alertmanager({**body, "status": "resolved"})


def test_parse_alertmanager_malformed():
    """Malformed bodies yield no triggers."""
    assert not parse_alertmanager({})
    assert not parse_alertmanager({"status": "firing", "alerts": []})


def test_parse_gatus_triggered_only():
    """Only TRIGGERED gatus payloads yield a trigger."""
    b = {"endpoint": "talos/talos-hb1-9mk Talos API",
         "status": "TRIGGERED", "description": "x",
         "errors": "dial tcp: i/o timeout"}
    t = parse_gatus(b)[0]
    assert t.key == "gatus:talos/talos-hb1-9mk Talos API"
    assert t.severity == "critical"
    assert t.source == "gatus"
    assert not parse_gatus({**b, "status": "RESOLVED"})
