"""Dedupe, daily cap and batching policy for triage runs."""
import json
import os
import tempfile
from typing import Any, Optional

from triage.payloads import Trigger


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


class Deduper:
    """Remembers recently triaged keys across restarts."""

    def __init__(self, window_s: int, path: str) -> None:
        """Initializes the deduper.

        Args:
            window_s: Seconds a key stays suppressed.
            path: JSON state file.
        """
        self._window = window_s
        self._path = path
        self._seen: dict[str, float] = _load(path)

    def seen_recently(self, key: str, now: float) -> bool:
        """Returns True if key was marked within the window."""
        ts = self._seen.get(key)
        return isinstance(ts, (int, float)) and now - ts <= self._window

    def mark(self, key: str, now: float) -> None:
        """Records key at now and prunes expired entries."""
        self._seen = {k: v for k, v in self._seen.items()
                      if isinstance(v, (int, float))
                      and now - v <= self._window}
        self._seen[key] = now
        _save(self._path, self._seen)


class DailyCap:
    """Limits runs per UTC day and signals one notice on exhaustion."""

    def __init__(self, limit: int, path: str) -> None:
        """Initializes the cap.

        Args:
            limit: Maximum runs per UTC day.
            path: JSON state file.
        """
        self._limit = limit
        self._path = path
        self._state = _load(path)

    def _roll(self, now: float) -> None:
        """Resets counters when the UTC day changes."""
        day = int(now // 86400)
        if self._state.get("day") != day:
            self._state = {"day": day, "count": 0, "noticed": False}

    def try_take(self, now: float) -> bool:
        """Consumes one run if budget remains today."""
        self._roll(now)
        if self._state.get("count", 0) >= self._limit:
            return False
        self._state["count"] = self._state.get("count", 0) + 1
        _save(self._path, self._state)
        return True

    def notice_due(self, now: float) -> bool:
        """Returns True once per UTC day after the cap is exhausted."""
        self._roll(now)
        if (self._state.get("count", 0) < self._limit
                or self._state.get("noticed")):
            return False
        self._state["noticed"] = True
        _save(self._path, self._state)
        return True


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
