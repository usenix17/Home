#!/usr/bin/env python3
"""Reverse-engineer the live UniFi config into name-based starnix.unifi vars.

Every cross-reference is emitted as a name, resolved at play time via the
``zone_ids`` / ``list_ids`` / ``network_ids`` / ``policy_ids`` maps that
playbooks/unifi_iac.yml builds from the controller. No literal UUIDs appear in
the generated vars; resources are matched by their (unique) names.
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


def jref(mapping, name):
    """Return a Jinja reference into a name->id map, e.g. {{ zone_ids['X'] }}."""
    if "'" in name:
        return '{{ %s["%s"] }}' % (mapping, name)
    return "{{ %s['%s'] }}" % (mapping, name)


def dump(name, key, rows):
    header = (
        f"# {name} -- generated from the live controller by discover_unifi_iac.py.\n"
        "# Names only; cross-references resolve at play time via the *_ids maps\n"
        "# that unifi_iac.yml builds. Matched by name -- edit values freely.\n")
    with open(os.path.join(OUT, name), "w", encoding="utf-8") as handle:
        handle.write(header)
        yaml.dump({key: rows}, handle, default_flow_style=False, sort_keys=False,
                  width=100, allow_unicode=True)
    print(f"  wrote {name}: {len(rows)} item(s)")


def tf_to_names(tf, lmap, nmap):
    """Copy a trafficFilter, swapping list/network UUIDs for name refs."""
    out = dict(tf)
    for sub in ("ipAddressFilter", "portFilter"):
        node = tf.get(sub)
        if node and node.get("type") == "TRAFFIC_MATCHING_LIST":
            lid = node.get("trafficMatchingListId")
            if lid in lmap:
                out[sub] = {**node,
                            "trafficMatchingListId": jref("list_ids", lmap[lid])}
    nfilter = tf.get("networkFilter")
    if nfilter and nfilter.get("networkIds"):
        out["networkFilter"] = {**nfilter, "networkIds": [
            jref("network_ids", nmap[i]) if i in nmap else i
            for i in nfilter["networkIds"]]}
    return out


def side_to_names(side, zmap, lmap, nmap):
    """Copy a policy source/destination, swapping zone/list/network UUIDs."""
    out = {}
    if side.get("zoneId") in zmap:
        out["zoneId"] = jref("zone_ids", zmap[side["zoneId"]])
    if side.get("trafficFilter") is not None:
        out["trafficFilter"] = tf_to_names(side["trafficFilter"], lmap, nmap)
    for key, val in side.items():
        if key not in ("zoneId", "trafficFilter"):
            out[key] = val
    return out


def main():
    os.makedirs(OUT, exist_ok=True)
    sid = get(V1, "/v1/sites")["data"][0]["id"]
    print(f"site {sid}")

    zmap = {z["id"]: z["name"] for z in page(sid, "firewall/zones")}
    lmap = {g["id"]: g["name"] for g in page(sid, "traffic-matching-lists")}
    nmap = {n["id"]: n["name"] for n in page(sid, "networks")}

    dump("groups.yml", "unifi_groups",
         [{"name": g["name"], "type": g["type"], "items": g.get("items", [])}
          for g in page(sid, "traffic-matching-lists")])

    dump("zones.yml", "unifi_zones",
         [{"name": z["name"],
           "network_ids": [jref("network_ids", nmap[i])
                           for i in z.get("networkIds", []) if i in nmap]}
          for z in page(sid, "firewall/zones")
          if (z.get("metadata") or {}).get("origin") == "USER_DEFINED"])

    networks = []
    for net in page(sid, "networks"):
        entry = {"name": net["name"], "management": net["management"],
                 "enabled": net["enabled"], "vlan_id": net.get("vlanId")}
        if net.get("zoneId") in zmap:
            entry["zone_id"] = jref("zone_ids", zmap[net["zoneId"]])
        networks.append(entry)
    dump("networks.yml", "unifi_networks", networks)

    pols = page(sid, "firewall/policies")
    pmap = {p["id"]: p["name"] for p in pols
            if (p.get("metadata") or {}).get("origin") == "USER_DEFINED"}
    codified, pairs = [], set()
    for pol in pols:
        if (pol.get("metadata") or {}).get("origin") != "USER_DEFINED":
            continue
        entry = {"name": pol["name"], "enabled": pol["enabled"]}
        if pol.get("description"):
            entry["description"] = pol["description"]
        entry["action"] = pol["action"]
        entry["source"] = side_to_names(pol["source"], zmap, lmap, nmap)
        entry["destination"] = side_to_names(pol["destination"], zmap, lmap, nmap)
        entry["ip_protocol_scope"] = pol["ipProtocolScope"]
        if pol.get("connectionStateFilter"):
            entry["connection_state_filter"] = pol["connectionStateFilter"]
        if pol.get("ipsecFilter"):
            entry["ipsec_filter"] = pol["ipsecFilter"]
        entry["logging_enabled"] = pol.get("loggingEnabled", False)
        if pol.get("schedule"):
            entry["schedule"] = pol["schedule"]
        codified.append(entry)
        pairs.add((pol["source"]["zoneId"], pol["destination"]["zoneId"]))
    dump("policies.yml", "unifi_policies", codified)

    ordering = []
    for src, dst in sorted(pairs):
        body = get(V1, f"/v1/sites/{sid}/firewall/policies/ordering"
                        f"?sourceFirewallZoneId={src}"
                        f"&destinationFirewallZoneId={dst}")
        oid = body.get("orderedFirewallPolicyIds", {})
        entry = {"source_zone": jref("zone_ids", zmap[src]),
                 "destination_zone": jref("zone_ids", zmap[dst])}
        for field, bucket in (("before_system_defined", "beforeSystemDefined"),
                              ("after_system_defined", "afterSystemDefined")):
            ids = oid.get(bucket) or []
            if ids:
                entry[field] = [jref("policy_ids", pmap[i]) if i in pmap else i
                                for i in ids]
        if len(entry) > 2:
            ordering.append(entry)
    dump("ordering.yml", "unifi_ordering", ordering)

    dhcp = []
    for net in get(CLASSIC, "/rest/networkconf")["data"]:
        entry = {"name": net["name"]}
        has = False
        if net.get("dhcpd_dns_enabled"):
            entry["dns_servers"] = [net.get(f"dhcpd_dns_{i}")
                                    for i in range(1, 5)
                                    if net.get(f"dhcpd_dns_{i}")]
            has = True
        if net.get("dhcpd_ntp_enabled"):
            entry["ntp_servers"] = [net.get(f"dhcpd_ntp_{i}")
                                    for i in range(1, 3)
                                    if net.get(f"dhcpd_ntp_{i}")]
            has = True
        if has:
            dhcp.append(entry)
    dump("dhcp.yml", "unifi_dhcp", dhcp)


if __name__ == "__main__":
    main()
