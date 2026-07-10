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

Each entry pins its live `id` so lookups are an exact, efficient GET-by-id. Drop
the `id` from an entry to make it portable to a fresh controller (matched by
name instead). Opaque objects (`action`, `source`, `destination`,
`ip_protocol_scope`) are passed through verbatim; obtain new shapes by
inspecting an existing policy.

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

## Relationship to the v2 reconciler

`roles/unifi_firewall` (the tag-based v2 reconciler) manages the same firewall
policies through a different API. Treat **one** of them as the source of truth
-- this collection-based playbook is the migration path off the reconciler.
Running both in enforce mode with divergent desired states would have them
fight; check-mode here never writes and is always safe.
