"""Dedupe, daily cap and batching policy for triage runs."""
import json
import logging
import os
import tempfile
import threading
from typing import Any, Optional

from triage.payloads import Trigger

LOG = logging.getLogger("triage")


def _load(path: str) -> dict[str, Any]:
    """Loads a JSON object, returning {} if missing or corrupt."""
    try:
        with open(path, encoding="utf-8") as fh:
            data = json.load(fh)
    except (OSError, ValueError):
        return {}
    return data if isinstance(data, dict) else {}


def _save(path: str, data: dict[str, Any]) -> None:
    """Atomically writes a JSON object to path."""
    directory = os.path.dirname(os.path.abspath(path))
    fd, tmp = tempfile.mkstemp(dir=directory, suffix=".tmp")
    try:
        with os.fdopen(fd, "w", encoding="utf-8") as fh:
            json.dump(data, fh)
        os.replace(tmp, path)
    except OSError:
        if os.path.exists(tmp):
            os.unlink(tmp)
        raise


def writable(directory: str) -> bool:
    """Returns True if a file with content can be written in directory."""
    try:
        with tempfile.NamedTemporaryFile(dir=directory,
                                         suffix=".tmp") as fh:
            fh.write(b"probe")
    except OSError:
        return False
    return True


class _State:
    """A JSON object held in memory and saved when possible.

    Memory is authoritative: a failed save is logged and retried. Each
    change is a new dict, so readers on other threads need no lock.
    """

    def __init__(self, path: str) -> None:
        """Loads the state from path."""
        self._path = path
        self._lock = threading.Lock()
        self._dirty = False
        self.data: dict[str, Any] = _load(path)

    def set(self, data: dict[str, Any]) -> None:
        """Replaces the state and tries to save it."""
        with self._lock:
            self.data = data
            self._dirty = True
            self._flush()

    def saved(self) -> bool:
        """Retries a failed save; returns True if nothing is unsaved."""
        with self._lock:
            if self._dirty:
                self._flush()
            return not self._dirty

    def _flush(self) -> None:
        """Saves the state, logging a failure."""
        try:
            _save(self._path, self.data)
        except OSError as err:
            LOG.error("state write failed: %s", err)
        else:
            self._dirty = False


class Deduper:
    """Remembers recently triaged keys across restarts."""

    def __init__(self, window_s: int, path: str) -> None:
        """Initializes the deduper.

        Args:
            window_s: Seconds a key stays suppressed.
            path: JSON state file.
        """
        self._window = window_s
        self._state = _State(path)

    def _recent(self, mark: Any, now: float) -> bool:
        """Returns True if a mark is within the window of now."""
        return isinstance(mark, (int, float)) and (
            abs(now - mark) <= self._window)

    def seen_recently(self, key: str, now: float) -> bool:
        """Returns True if key was marked within the window."""
        return self._recent(self._state.data.get(key), now)

    def mark(self, key: str, now: float) -> None:
        """Records key at now and prunes expired entries."""
        kept = {k: v for k, v in self._state.data.items()
                if self._recent(v, now)}
        self._state.set({**kept, key: now})

    def saved(self) -> bool:
        """Returns True if the marks are on disk, retrying if not."""
        return self._state.saved()


class DailyCap:
    """Limits runs per UTC day and signals one notice on exhaustion."""

    def __init__(self, limit: int, path: str) -> None:
        """Initializes the cap.

        Args:
            limit: Maximum runs per UTC day.
            path: JSON state file.
        """
        self._limit = limit
        self._state = _State(path)

    def _today(self, now: float) -> dict[str, Any]:
        """Returns the counters, new ones when the UTC day changed."""
        day = int(now // 86400)
        if self._state.data.get("day") != day:
            return {"day": day, "count": 0, "noticed": False}
        return self._state.data

    def try_take(self, now: float) -> bool:
        """Consumes one run if budget remains today."""
        today = self._today(now)
        count = today.get("count", 0)
        if count >= self._limit:
            return False
        self._state.set({**today, "count": count + 1})
        return True

    def notice_due(self, now: float) -> bool:
        """Returns True once per UTC day after the cap is exhausted."""
        today = self._today(now)
        if today.get("count", 0) < self._limit or today.get("noticed"):
            return False
        self._state.set({**today, "noticed": True})
        return True

    def saved(self) -> bool:
        """Returns True if the counters are on disk, retrying if not."""
        return self._state.saved()


class Batcher:
    """Collects triggers into windowed batches."""

    def __init__(self, window_s: int) -> None:
        """Initializes the batcher.

        Args:
            window_s: Seconds from a batch's first trigger to release.
        """
        self._window = window_s
        self._items: dict[str, Trigger] = {}
        self._first: Optional[float] = None
        self._inflight: set[str] = set()

    def set_inflight(self, keys: Optional[set[str]]) -> None:
        """Sets keys of the running batch; None clears them."""
        self._inflight = set(keys or ())

    def add(self, t: Trigger, now: float) -> None:
        """Adds a trigger, merging duplicates and dropping in-flight."""
        if t.key in self._inflight or t.key in self._items:
            return
        if not self._items:
            self._first = now
        self._items[t.key] = t

    def ready(self, now: float) -> Optional[list[Trigger]]:
        """Returns and clears the batch once its window has elapsed."""
        if self._first is None or now - self._first < self._window:
            return None
        batch = list(self._items.values())
        self._items = {}
        self._first = None
        return batch
