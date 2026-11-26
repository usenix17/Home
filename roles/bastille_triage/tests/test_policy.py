"""Tests for dedupe, daily cap and batching."""
import json
import shutil

import pytest

from triage import policy
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


def test_dedupe_future_mark_expires():
    """A mark from a clock that ran ahead does not suppress for long."""
    d = Deduper(100, "/nonexistent/d.json")
    d.mark("k", 10_000)
    assert not d.seen_recently("k", 5_000)
    assert d.seen_recently("k", 9_950)
    d.mark("other", 5_000)
    assert not d.seen_recently("k", 10_000)


@pytest.mark.parametrize("make", [
    lambda p: Deduper(100, p), lambda p: DailyCap(3, p)])
def test_write_errors_are_caught_and_retried(tmp_path, make, caplog):
    """A failed save is logged, kept in memory, and saved later."""
    state = tmp_path / "state"
    state.mkdir()
    store = make(str(state / "s.json"))

    def change():
        if isinstance(store, Deduper):
            store.mark("k", 1000)
        else:
            store.try_take(1000)

    assert store.saved()
    shutil.rmtree(state)
    change()
    assert not store.saved()
    assert "state" in caplog.text and "Traceback" not in caplog.text
    if isinstance(store, Deduper):
        assert store.seen_recently("k", 1001)
    else:
        assert store.try_take(1001) and store.try_take(1002)
        assert not store.try_take(1003)
        assert store.notice_due(1004) and not store.notice_due(1005)
    state.mkdir()
    assert store.saved()
    assert json.loads((state / "s.json").read_text())


def test_failed_replace_leaves_no_temp_file(tmp_path):
    """A target that can't be replaced is reported, without litter."""
    (tmp_path / "s.json").mkdir()
    cap = DailyCap(3, str(tmp_path / "s.json"))
    assert cap.try_take(1) and not cap.saved()
    assert [p.name for p in tmp_path.iterdir()] == ["s.json"]


def test_writable_probe(tmp_path):
    """The probe writes a real file and removes it."""
    assert policy.writable(str(tmp_path)) and not list(tmp_path.iterdir())
    assert not policy.writable(str(tmp_path / "absent"))
