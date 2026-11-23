"""Tests for dedupe, daily cap and batching."""
from triage.payloads import Trigger
from triage.policy import Batcher, DailyCap, Deduper


def test_dedupe_window(tmp_path):
    """Keys are seen for exactly the window."""
    d = Deduper(21600, str(tmp_path / "d.json"))
    d.mark("k", 1000)
    assert d.seen_recently("k", 1000 + 21599)
    assert not d.seen_recently("k", 1000 + 21601)


def test_dedupe_survives_reload(tmp_path):
    """Dedupe state persists across instances."""
    p = str(tmp_path / "d.json")
    Deduper(21600, p).mark("k", 1000)
    assert Deduper(21600, p).seen_recently("k", 1001)


def test_corrupt_state_is_empty(tmp_path):
    """A corrupt state file is treated as empty."""
    p = tmp_path / "d.json"
    p.write_text("{not json")
    assert not Deduper(10, str(p)).seen_recently("k", 1)


def test_cap_per_utc_day_and_single_notice(tmp_path):
    """The cap resets per UTC day and notifies once."""
    c = DailyCap(10, str(tmp_path / "c.json"))
    day = 1_795_000_000 - 1_795_000_000 % 86400
    assert all(c.try_take(day + i) for i in range(10))
    assert not c.try_take(day + 11)
    assert c.notice_due(day + 12)
    assert not c.notice_due(day + 13)
    assert c.try_take(day + 86400)


def test_notice_not_due_before_exhaustion(tmp_path):
    """No notice while budget remains."""
    c = DailyCap(2, str(tmp_path / "c.json"))
    c.try_take(100)
    assert not c.notice_due(101)


def test_cap_survives_reload(tmp_path):
    """Cap state persists across instances."""
    p = str(tmp_path / "c.json")
    c = DailyCap(1, p)
    assert c.try_take(100)
    assert not DailyCap(1, p).try_take(101)


def test_batch_window():
    """A batch is ready once its window elapses."""
    b = Batcher(120)
    t = Trigger("gatus", "gatus:a", "a", "critical", {})
    b.add(t, 0)
    assert b.ready(119) is None
    assert b.ready(120) == [t]
    assert b.ready(121) is None


def test_batch_merges_duplicate_key():
    """The same key in one batch is not duplicated."""
    b = Batcher(10)
    t = Trigger("gatus", "k", "a", "critical", {})
    b.add(t, 0)
    b.add(t, 5)
    assert b.ready(10) == [t]


def test_batch_during_inflight_run():
    """In-flight keys are dropped; others queue."""
    b = Batcher(120)
    t1 = Trigger("gatus", "k1", "a", "critical", {})
    t2 = Trigger("gatus", "k2", "b", "critical", {})
    b.add(t1, 0)
    assert b.ready(120) == [t1]
    b.set_inflight({"k1"})
    b.add(t1, 130)
    b.add(t2, 131)
    assert b.ready(251) == [t2]
    b.set_inflight(None)
    b.add(t1, 300)
    assert b.ready(420) == [t1]
