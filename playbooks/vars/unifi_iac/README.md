# UniFi as code (`unifi_iac`)

Declarative management of the UniFi zone-based firewall, networks, and DHCP
options via the [`starnix.unifi`](https://github.com/usenix17/starnix.unifi)
collection. Driven by `playbooks/unifi_iac.yml`.

## Files

Each vars file is reverse-engineered from the live controller, so a run against
the current controller is a **no-op**. Edit the values to change config.

| File | Manages | Module |
|---|---|---|
| `groups.yml` | address/port matching lists | `unifi_firewall_group` |
| `zones.yml` | custom firewall zones | `unifi_firewall_zone` |
| `networks.yml` | networks / VLAN identity | `unifi_network` |
| `policies.yml` | firewall policies | `unifi_firewall_policy` |
| `ordering.yml` | policy evaluation order | `unifi_firewall_policy_order` |
| `dhcp.yml` | per-network DHCP (DNS/NTP/...) | `unifi_network_dhcp` |

## How references work (no UUIDs)

The vars are **name-based** -- there are no UUIDs. Resources are matched by their
(unique) names, and every cross-reference is written as a name and resolved at
play time. The playbook first queries the controller and builds four maps --
`zone_ids`, `list_ids`, `network_ids`, `policy_ids` (name to id) -- so a policy
reads, for example:

```yaml
- name: Allow all nebula (Internal->Internal)
  source:
    zoneId: "{{ zone_ids['Internal'] }}"
    trafficFilter:
      type: IP_ADDRESS
      ipAddressFilter:
        type: TRAFFIC_MATCHING_LIST
        trafficMatchingListId: "{{ list_ids['Nebula'] }}"
```

Because policies are matched by name, every policy name must be unique. The v1
Policy Engine expands each logical rule into one policy per zone-pair, so the
duplicates were renamed with a `(Source->Destination)` suffix (e.g.
`Allow DNS (Internal->External)`). The opaque objects (`action`, `source`,
`destination`, `ip_protocol_scope`) are otherwise passed through verbatim; get a
new shape by inspecting an existing policy.

## Usage

```bash
export UNIFI_API_KEY=...            # the controller API key
ansible-playbook playbooks/unifi_iac.yml --check --diff   # preview (safe)
ansible-playbook playbooks/unifi_iac.yml                   # enforce
```

## Regenerating the vars from the live controller

```bash
UNIFI_API_KEY=... OUT=playbooks/vars/unifi_iac \
  python3 playbooks/vars/unifi_iac/discover_unifi_iac.py
```

## The v2 reconciler is retired

This playbook replaced the old tag-based v2 reconciler (`roles/unifi_firewall`),
which has been removed -- it is now the sole source of truth for the firewall.
See [RETIREMENT.md](RETIREMENT.md) for the cutover steps (Semaphore template +
making the collection installable on the runner).
