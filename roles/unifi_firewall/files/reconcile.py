#!/usr/bin/env python3
"""Stateless reconciler for the UniFi zone-based firewall.

Desired state (address groups and zone policies) is declared in YAML using
friendly zone and group names. This script resolves those against the live
controller and converges it:

  * Address groups -- created or updated by name.
  * Zone policies  -- matched by their ``description`` tag (``iac:<key>``), so
    reconciliation needs no external state file. A policy is created when its
    tag is absent, adopted in place when an untagged rule already matches, and
    updated when fields drift. Any tagged policy no longer declared is pruned.

Predefined rules and untagged (manually created) rules are never touched, and
``index`` is deliberately not managed -- rule ordering is left to the UDM,
which reassigns index on write.

Safety: dry-run is the default (pass ``--apply`` to execute) and a prune guard
aborts when more deletions than ``--max-prune`` are required, so an empty or
broken desired file cannot wipe the firewall.

Exit codes:
  0  converged (or clean dry-run)
  2  drift found during a dry-run
  3  prune guard tripped
  4  API or configuration error
"""

from __future__ import annotations

import argparse
import json
import os
import ssl
import sys
import urllib.error
import urllib.request
from typing import Any

try:
    import yaml
except ImportError:
    yaml = None

TAG = "iac:"
CLASSIC_API = "/proxy/network/api/s/default"
V2_API = "/proxy/network/v2/api/site/default"

# Only the fields we manage are set from the spec and compared for drift;
# server-managed fields (matching_target, connection_state_type, matchers) are
# preserved from the live rule and never trigger a false update.
SOURCE_KEYS = (
    "zone_id", "network_ids", "ips", "ip_group_id", "port", "port_group_id",
)
DEST_KEYS = SOURCE_KEYS + ("regions", "web_domains", "app_ids")
TOP_KEYS = (
    "action", "name", "enabled", "protocol", "ip_version", "logging",
    "description",
)


class Controller:
    """Minimal UniFi controller HTTP client using X-API-KEY auth."""

    def __init__(self, base: str, key: str, insecure: bool) -> None:
        """Stores connection settings.

        Args:
            base: Controller base URL (e.g. ``https://192.168.1.1``).
            key: API key sent as the ``X-API-KEY`` header.
            insecure: If true, skip TLS verification (self-signed cert).
        """
        self._base = base.rstrip("/")
        self._key = key
        context = ssl.create_default_context()
        if insecure:
            context.check_hostname = False
            context.verify_mode = ssl.CERT_NONE
        self._ctx = context

    def call(self, method: str, path: str,
             body: Any = None) -> tuple[int, Any]:
        """Sends one request.

        Args:
            method: HTTP verb.
            path: Path beneath the controller base URL.
            body: Optional JSON-serialisable payload.

        Returns:
            The status code and decoded JSON body (or raw error text on an
            HTTP error).
        """
        data = json.dumps(body).encode() if body is not None else None
        request = urllib.request.Request(
            self._base + path, data=data, method=method)
        request.add_header("X-API-KEY", self._key)
        request.add_header("Accept", "application/json")
        if body is not None:
            request.add_header("Content-Type", "application/json")
        try:
            with urllib.request.urlopen(
                    request, timeout=30, context=self._ctx) as resp:
                status, text = resp.getcode(), resp.read().decode()
            return status, (json.loads(text) if text else None)
        except urllib.error.HTTPError as err:
            return err.code, err.read().decode()

    def get(self, path: str) -> Any:
        """Returns the decoded body of a GET request."""
        return self.call("GET", path)[1]


def project(source: dict, keys: tuple) -> dict:
    """Returns a copy of ``source`` restricted to ``keys``."""
    return {key: source.get(key) for key in keys}


def normalize(policy: dict) -> dict:
    """Projects a policy onto managed fields for drift comparison.

    Args:
        policy: A full policy object or merged body.

    Returns:
        Only the managed top-level, source and destination fields; server
        managed fields such as ``_id`` and ``index`` are dropped.
    """
    result = project(policy, TOP_KEYS)
    result["source"] = project(policy.get("source") or {}, SOURCE_KEYS)
    result["destination"] = project(policy.get("destination") or {}, DEST_KEYS)
    return result


def build_endpoint(base: dict, spec_side: dict, ctx: dict) -> dict:
    """Overlays spec-declared fields onto a base endpoint.

    Only declared fields are changed, so the live rule's other (server
    managed) fields and their exact types are preserved -- the controller
    rejects type drift such as an integer ``port``.

    Args:
        base: The template or current endpoint to build upon.
        spec_side: The friendly source/destination mapping from a spec.
        ctx: Context with ``zones``, ``nets``, ``groups`` and ``ports`` maps.

    Returns:
        A resolved endpoint block.
    """
    out = dict(base or {})
    want = spec_side or {}
    if want.get("zone"):
        out["zone_id"] = ctx["zones"].get(want["zone"])
    if want.get("networks"):
        out["network_ids"] = [ctx["nets"].get(n) for n in want["networks"]]
    if want.get("ips"):
        out["ips"] = want["ips"]
    if want.get("group"):
        out["ip_group_id"] = ctx["groups"].get(want["group"])
    if want.get("port"):
        out["port"] = str(want["port"])
    if want.get("port_group"):
        out["port_group_id"] = ctx["ports"].get(want["port_group"])
    for passthrough in ("web_domains", "regions", "app_ids"):
        if want.get(passthrough):
            out[passthrough] = want[passthrough]
    return out


def build_body(spec: dict, ctx: dict, base: dict) -> dict:
    """Builds an API policy body by overlaying a spec onto a base policy.

    Args:
        spec: Desired policy spec.
        ctx: Resolution context.
        base: Policy to build on -- the clone template (create) or the current
            live policy (update), so unmanaged fields are preserved.

    Returns:
        A policy body ready to POST or PUT.
    """
    body = json.loads(json.dumps(base))
    for managed in ("_id", "predefined", "index"):
        body.pop(managed, None)
    body["name"] = spec["name"]
    body["description"] = TAG + spec["key"]
    body["action"] = spec.get("action", "ALLOW")
    body["enabled"] = spec.get("enabled", True)
    body["protocol"] = spec.get("protocol", "all")
    body["ip_version"] = spec.get("ip_version", "BOTH")
    if "logging" in spec:
        body["logging"] = spec["logging"]
    body["source"] = build_endpoint(
        body.get("source", {}), spec.get("source"), ctx)
    body["destination"] = build_endpoint(
        body.get("destination", {}), spec.get("destination"), ctx)
    return body


def resolve_context(ctrl: Controller) -> dict:
    """Fetches zones, networks, groups, policies and a clone template.

    Args:
        ctrl: Controller client.

    Returns:
        A context dict consumed by the build and reconcile helpers.

    Raises:
        SystemExit: If the zone listing cannot be retrieved.
    """
    zone_list = ctrl.get(V2_API + "/firewall/zone")
    if not isinstance(zone_list, list):
        sys.exit(f"[4] cannot list zones: {zone_list}")
    net_list = ctrl.get(CLASSIC_API + "/rest/networkconf")
    groups = ctrl.get(CLASSIC_API + "/rest/firewallgroup").get("data", [])
    policies = ctrl.get(V2_API + "/firewall-policies")
    template = next(
        p for p in policies
        if not p.get("predefined") and (p.get("source") or {}).get("zone_id"))
    tagged = {
        p["description"][len(TAG):]: p
        for p in policies
        if (p.get("description") or "").startswith(TAG)
    }
    return {
        "zones": {z["name"]: z["_id"] for z in zone_list},
        "nets": {n["name"]: n["_id"] for n in net_list.get("data", [])},
        "groups": {g["name"]: g["_id"] for g in groups},
        "ports": {
            g["name"]: g["_id"] for g in groups
            if g.get("group_type") == "port-group"
        },
        "policies": policies,
        "template": template,
        "tagged": tagged,
    }


def reconcile_groups(ctrl: Controller, specs: list, apply: bool) -> int:
    """Creates or updates address groups.

    Args:
        ctrl: Controller client.
        specs: Desired group specs.
        apply: If true, execute; otherwise report only.

    Returns:
        The number of groups changed.
    """
    listing = ctrl.get(CLASSIC_API + "/rest/firewallgroup").get("data", [])
    live = {g["name"]: g for g in listing}
    changes = 0
    for group in specs:
        want = sorted(group.get("members", []))
        current = live.get(group["name"])
        if current is None:
            print(f"  + group  {group['name']}  {want}")
            changes += 1
            if apply:
                ctrl.call("POST", CLASSIC_API + "/rest/firewallgroup", {
                    "name": group["name"],
                    "group_type": group.get("type", "address-group"),
                    "group_members": group["members"],
                })
        elif sorted(current.get("group_members", [])) != want:
            print(f"  ~ group  {group['name']}  -> {want}")
            changes += 1
            if apply:
                current["group_members"] = group["members"]
                ctrl.call(
                    "PUT",
                    CLASSIC_API + "/rest/firewallgroup/" + current["_id"],
                    current)
    return changes


def is_untagged(policy: dict) -> bool:
    """Returns true for a non-predefined policy with no iac tag."""
    description = policy.get("description") or ""
    return not description.startswith(TAG) and not policy.get("predefined")


def dst_selector(policy: dict) -> tuple:
    """Returns a tuple distinguishing rules that share a name and zone pair."""
    dst = policy.get("destination") or {}
    return (policy.get("action"), dst.get("ip_group_id"), dst.get("port"),
            tuple(dst.get("ips") or []), dst.get("port_group_id"))


def find_current(spec: dict, body: dict, ctx: dict) -> tuple[Any, str]:
    """Finds the live policy a spec maps to.

    Args:
        spec: Desired policy spec.
        body: Built API body (for its resolved zone ids).
        ctx: Resolution context.

    Returns:
        A ``(policy_or_none, status)`` tuple where status is ``"tagged"``,
        ``"adopt"``, ``"create"`` or ``"ambiguous"``.
    """
    current = ctx["tagged"].get(spec["key"])
    if current is not None:
        return current, "tagged"
    src = body["source"]["zone_id"]
    dst = body["destination"]["zone_id"]
    matches = [
        p for p in ctx["policies"]
        if is_untagged(p)
        and p.get("name") == spec["name"]
        and (p.get("source") or {}).get("zone_id") == src
        and (p.get("destination") or {}).get("zone_id") == dst
    ]
    if len(matches) > 1:
        # Several live rules share name + zone pair; narrow by the destination
        # selector (action, group, port, ips).
        want = dst_selector(body)
        matches = [p for p in matches if dst_selector(p) == want]
    if len(matches) == 1:
        return matches[0], "adopt"
    if len(matches) > 1:
        return None, "ambiguous"
    return None, "create"


def upsert_policy(ctrl: Controller, spec: dict, ctx: dict,
                  apply: bool) -> int:
    """Creates, adopts or updates a single policy.

    Args:
        ctrl: Controller client.
        spec: Desired policy spec.
        ctx: Resolution context.
        apply: If true, execute; otherwise report only.

    Returns:
        1 if the policy changed, else 0.

    Raises:
        SystemExit: If a create or update request fails.
    """
    probe = build_body(spec, ctx, ctx["template"])
    current, status = find_current(spec, probe, ctx)
    if status == "ambiguous":
        print(f"  ! skip   {spec['key']}: multiple untagged matches")
        return 0
    if status == "create":
        print(f"  + policy {spec['key']}  ({spec['name']})")
        if apply:
            code, resp = ctrl.call(
                "POST", V2_API + "/firewall-policies", probe)
            if code not in (200, 201):
                sys.exit(f"[4] create {spec['key']} failed: {code} "
                         f"{str(resp)[:160]}")
        return 1
    desired = build_body(spec, ctx, current)
    if normalize(desired) == normalize(current):
        return 0
    label = "adopt" if status == "adopt" else "update"
    print(f"  ~ {label:6} {spec['key']}  ({spec['name']})")
    if apply:
        code, resp = ctrl.call(
            "PUT", V2_API + "/firewall-policies/" + current["_id"], desired)
        if code != 200:
            sys.exit(f"[4] update {spec['key']} failed: {code} "
                     f"{str(resp)[:160]}")
    return 1


def prune_policies(ctrl: Controller, ctx: dict, keep: set, apply: bool,
                   max_prune: int) -> int:
    """Deletes tagged policies that are no longer declared.

    Args:
        ctrl: Controller client.
        ctx: Resolution context.
        keep: The set of desired keys to retain.
        apply: If true, execute; otherwise report only.
        max_prune: Abort if more than this many rules would be deleted.

    Returns:
        The number of policies pruned.

    Raises:
        SystemExit: If the prune guard is tripped.
    """
    stale = [(key, p) for key, p in ctx["tagged"].items() if key not in keep]
    if len(stale) > max_prune:
        print(f"[3] PRUNE GUARD: {len(stale)} tagged rules would be deleted "
              f"(> --max-prune {max_prune}); refusing.")
        sys.exit(3)
    for key, policy in stale:
        print(f"  - policy {key}  ({policy['name']})  PRUNE")
        if apply:
            ctrl.call("DELETE", V2_API + "/firewall-policies/" + policy["_id"])
    return len(stale)


def load_desired(path: str) -> dict:
    """Loads the desired state from a JSON or YAML file.

    JSON is parsed with the standard library so the reconciler can run on a
    host without PyYAML (the playbook renders the data as JSON); YAML is a
    fallback for local editing.

    Args:
        path: Path to the desired-state file.

    Returns:
        The parsed desired state.

    Raises:
        SystemExit: If the file is not JSON and PyYAML is unavailable.
    """
    with open(path, encoding="utf-8") as handle:
        text = handle.read()
    try:
        return json.loads(text)
    except json.JSONDecodeError:
        if yaml is None:
            sys.exit("[4] data is not JSON and PyYAML is unavailable")
        return yaml.safe_load(text) or {}


def parse_args() -> argparse.Namespace:
    """Parses command-line arguments."""
    parser = argparse.ArgumentParser(description="UniFi firewall reconciler")
    parser.add_argument("--data", required=True, help="desired-state YAML")
    parser.add_argument(
        "--url",
        default=os.environ.get("UNIFI_API_URL", "https://192.168.1.1"))
    parser.add_argument("--insecure", action="store_true", default=True)
    parser.add_argument(
        "--apply", action="store_true", help="execute (default: dry-run)")
    parser.add_argument("--max-prune", type=int, default=10)
    return parser.parse_args()


def main() -> int:
    """Loads desired state, reconciles, and returns a process exit code."""
    args = parse_args()
    key = os.environ.get("UNIFI_API_KEY")
    if not key:
        print("UNIFI_API_KEY is not set", file=sys.stderr)
        return 4
    desired = load_desired(args.data)

    ctrl = Controller(args.url, key, args.insecure)
    print(f"== UniFi firewall reconcile "
          f"[{'APPLY' if args.apply else 'DRY-RUN'}] vs {args.url} ==")

    changes = reconcile_groups(ctrl, desired.get("groups", []), args.apply)
    ctx = resolve_context(ctrl)
    keep = {spec["key"] for spec in desired.get("policies", [])}
    for spec in desired.get("policies", []):
        changes += upsert_policy(ctrl, spec, ctx, args.apply)
    changes += prune_policies(ctrl, ctx, keep, args.apply, args.max_prune)

    verb = "applied" if args.apply else "pending"
    print(f"== {changes} change(s) {verb}; {len(keep)} managed ==")
    return 2 if changes and not args.apply else 0


if __name__ == "__main__":
    sys.exit(main())
