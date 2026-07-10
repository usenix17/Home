#!/usr/bin/env python3
"""Reverse-engineer the live UniFi config into starnix.unifi Ansible vars.

Emits one vars file per resource type under the output dir. Each managed object
carries its live ``id`` so the generated playbook is an exact, efficient no-op
against the current controller (GET-by-id, not a name scan). Remove the ids to
make the vars portable to a fresh controller.
"""
import json
import os
import ssl
import urllib.request

import yaml

HOST = os.environ.get("UNIFI_HOST", "192.168.1.1")
KEY = os.environ["UNIFI_API_KEY"]
OUT = os.environ.get("OUT", "/kronos/IaC/playbooks/vars/unifi_iac")
V1 = f"https://{HOST}/proxy/network/integration"
CLASSIC = f"https://{HOST}/proxy/network/api/s/default"
CTX = ssl.create_default_context()
CTX.check_hostname = False
CTX.verify_mode = ssl.CERT_NONE


def get(base, path):
    req = urllib.request.Request(base + path)
    req.add_header("X-API-KEY", KEY)
    return json.loads(urllib.request.urlopen(req, timeout=30, context=CTX).read())


def page(sid, path):
    out, off = [], 0
    while True:
        body = get(V1, f"/v1/sites/{sid}/{path}?offset={off}&limit=200")
        data = body.get("data", [])
        out += data
        count = body.get("count", len(data))
        off += count
        if count == 0 or off >= body.get("totalCount", 0):
            break
    return out


def dump(name, key, rows):
    header = (
        f"# {name} -- generated from the live controller by discover_unifi_iac.py.\n"
        "# Each entry pins its live id (exact, efficient no-op). Edit values to\n"
        "# change config; drop the id to make an entry portable to a new site.\n")
    with open(os.path.join(OUT, name), "w", encoding="utf-8") as handle:
        handle.write(header)
        yaml.dump({key: rows}, handle, default_flow_style=False, sort_keys=False,
                  width=100, allow_unicode=True)
    print(f"  wrote {name}: {len(rows)} item(s)")


def main():
    os.makedirs(OUT, exist_ok=True)
    sid = get(V1, "/v1/sites")["data"][0]["id"]
    print(f"site {sid}")

    # traffic-matching lists (groups)
    groups = [{"id": g["id"], "name": g["name"], "type": g["type"],
               "items": g.get("items", [])}
              for g in page(sid, "traffic-matching-lists")]
    dump("groups.yml", "unifi_groups", groups)

    # zones (only user-defined are manageable)
    zones = [{"id": z["id"], "name": z["name"],
              "network_ids": z.get("networkIds", [])}
             for z in page(sid, "firewall/zones")
             if (z.get("metadata") or {}).get("origin") == "USER_DEFINED"]
    dump("zones.yml", "unifi_zones", zones)

    # networks
    nets = [{"id": n["id"], "name": n["name"], "management": n["management"],
             "enabled": n["enabled"], "vlan_id": n.get("vlanId"),
             "zone_id": n.get("zoneId")}
            for n in page(sid, "networks")]
    dump("networks.yml", "unifi_networks", nets)

    # firewall policies (user-defined)
    pols = page(sid, "firewall/policies")
    umap = {"ipProtocolScope": "ip_protocol_scope",
            "connectionStateFilter": "connection_state_filter",
            "ipsecFilter": "ipsec_filter", "loggingEnabled": "logging_enabled"}
    keep = ["enabled", "name", "description", "action", "source", "destination",
            "ipProtocolScope", "connectionStateFilter", "ipsecFilter",
            "loggingEnabled", "schedule"]
    codified = []
    pairs = set()
    for pol in pols:
        if (pol.get("metadata") or {}).get("origin") != "USER_DEFINED":
            continue
        entry = {"id": pol["id"]}
        for field in keep:
            if pol.get(field) is not None:
                entry[umap.get(field, field)] = pol[field]
        codified.append(entry)
        pairs.add((pol["source"]["zoneId"], pol["destination"]["zoneId"]))
    dump("policies.yml", "unifi_policies", codified)

    # ordering, per zone pair that carries user policies
    ordering = []
    for src, dst in sorted(pairs):
        body = get(V1, f"/v1/sites/{sid}/firewall/policies/ordering"
                        f"?sourceFirewallZoneId={src}"
                        f"&destinationFirewallZoneId={dst}")
        oid = body.get("orderedFirewallPolicyIds", {})
        before = oid.get("beforeSystemDefined") or []
        after = oid.get("afterSystemDefined") or []
        if before or after:
            entry = {"source_zone_id": src, "destination_zone_id": dst}
            if before:
                entry["before_system_defined"] = before
            if after:
                entry["after_system_defined"] = after
            ordering.append(entry)
    dump("ordering.yml", "unifi_ordering", ordering)

    # DHCP options (classic API), only where custom DNS/NTP is set
    dhcp = []
    for net in get(CLASSIC, "/rest/networkconf")["data"]:
        entry = {"name": net["name"]}
        has = False
        if net.get("dhcpd_dns_enabled"):
            servers = [net.get(f"dhcpd_dns_{i}") for i in range(1, 5)]
            entry["dns_servers"] = [s for s in servers if s]
            has = True
        if net.get("dhcpd_ntp_enabled"):
            ntp = [net.get(f"dhcpd_ntp_{i}") for i in range(1, 3)]
            entry["ntp_servers"] = [s for s in ntp if s]
            has = True
        if has:
            dhcp.append(entry)
    dump("dhcp.yml", "unifi_dhcp", dhcp)


if __name__ == "__main__":
    main()
