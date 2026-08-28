#!/usr/bin/env python3
"""Receive OpenSnitch JSON events over UDP and emit a slim ndjson feed.

OpenSnitch's SIEM logger streams every connection decision, including a
large and sensitive ``process_env`` map. This bridge drops that field,
keeps only security-relevant events (every deny, plus the first time a
given process talks to a given destination), and appends them as ndjson
for a Wazuh ``json`` localfile to collect.
"""

import argparse
import json
import signal
import socket
import sys
import time
from collections import OrderedDict

_MAX_DATAGRAM = 65535
_DROP_FIELDS = ("process_env",)


class Deduper:
    """Bounded time-to-live set of (process, destination) keys."""

    def __init__(self, ttl_seconds, max_entries):
        self._ttl = ttl_seconds
        self._max = max_entries
        self._seen = OrderedDict()

    def __len__(self):
        """Return the number of tracked keys."""
        return len(self._seen)

    def is_new(self, key, now):
        """Return True and record the key when it is unseen or expired."""
        stamp = self._seen.get(key)
        if stamp is not None and now - stamp <= self._ttl:
            return False
        self._seen[key] = now
        self._seen.move_to_end(key)
        self._prune(now)
        return True

    def _prune(self, now):
        """Evict expired keys, then oldest keys past the size cap."""
        while self._seen:
            stamp = next(iter(self._seen.values()))
            if now - stamp <= self._ttl and len(self._seen) <= self._max:
                break
            self._seen.popitem(last=False)


def _classify(event, action, deduper, now):
    """Return a reason string for a shippable event, else None."""
    if action and action.lower() != "allow":
        return "deny"
    dest = event.get("dst_host") or event.get("dst_ip", "")
    key = f"{event.get('process_path', '')}\x00{dest}"
    if deduper.is_new(key, now):
        return "first_seen"
    return None


def _transform(raw, deduper, now):
    """Parse one datagram; return slim record bytes or None to drop it."""
    start = raw.find(b"{")
    if start < 0:
        return None
    try:
        obj = json.loads(raw[start:].decode("utf-8", "replace"))
    except json.JSONDecodeError:
        return None
    event = obj.get("Event")
    if not isinstance(event, dict):
        return None
    reason = _classify(event, obj.get("Action"), deduper, now)
    if reason is None:
        return None
    for field in _DROP_FIELDS:
        event.pop(field, None)
    obj["bridge_reason"] = reason
    return (json.dumps(obj, separators=(",", ":")) + "\n").encode("utf-8")


def _serve(args):
    """Bind the UDP socket and stream slim records to the output file."""
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    sock.bind((args.host, args.port))
    deduper = Deduper(args.dedup_ttl, args.dedup_max)
    # copytruncate logrotate keeps this fd valid, so open once and append.
    with open(args.output, "a", encoding="utf-8") as out:
        while True:
            raw = sock.recvfrom(_MAX_DATAGRAM)[0]
            record = _transform(raw, deduper, time.time())
            if record is None:
                continue
            out.write(record.decode("utf-8"))
            out.flush()


def _parse_args(argv):
    """Build the command-line configuration."""
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=5140)
    parser.add_argument("--output", default="/var/log/opensnitch/events.json")
    parser.add_argument("--dedup-ttl", type=int, default=86400)
    parser.add_argument("--dedup-max", type=int, default=100000)
    return parser.parse_args(argv)


def main(argv):
    """Run the bridge until terminated by a signal."""
    signal.signal(signal.SIGTERM, lambda *_: sys.exit(0))
    _serve(_parse_args(argv))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
