# nsm_sensor

Codifies the passive **NSM tap appliance** on huginn (FreeBSD, on both switch
mirrors igb0+igb1): ntopng, Suricata, Zeek, and fluent-bit shipping to Graylog.

## What it does
1. **Base** -- mirror NICs `up promisc -lro -tso -rxcsum -txcsum` (rc.conf) and
   `net.inet.ip.portrange.reservedhigh=0` so ntopng can bind 443.
2. **ntopng** -- pkg ntopng+redis, `ntopng.conf` (both mirror ifaces, https on
   443, custom `protos.txt` nDPI apps), service enabled.
3. **ntopng cert** -- certbot + Cloudflare DNS-01 for `ntop.starnix.net`; a
   deploy-hook rebuilds the combined PEM ntopng reads and restarts it; weekly
   `periodic` renewal. (Reuses `vault_certbot_cloudflare_api_token`.)
4. **Suricata** -- pkg, capture on all `-i` mirror ifaces with `checksum-checks:
   no`, threshold-file enabled, mirror-noise SIDs suppressed, ET Open rules
   fetched, `newsyslog` rotation, and `/var/log/suricata` set 0751 so fluent-bit
   (`nobody`) can read `eve.json`.
5. **Zeek** -- pkg, cluster `node.cfg` (worker per mirror iface), tap tuning in
   `local.zeek` (`ignore_checksums`, JSON logs, community-id), `zeek_enable=YES`
   (the port leaves it NO), and a `zeekctl cron` housekeeping job.
6. **fluent-bit** -- pkg, config tailing Zeek + Suricata `eve.json`, a Lua filter
   lifting the DNS queried name to a flat `dns_rrname`, and two GELF outputs to
   Graylog. `parsers.conf` is left at the port default.

## Key variables
| var | default | purpose |
|-----|---------|---------|
| `nsm_mirror_interfaces` | `[igb0, igb1]` | SPAN receive NICs (drives ntopng `-i`, Suricata `-i`, Zeek workers) |
| `nsm_graylog_host` / `nsm_graylog_gelf_port` | `graylog.starnix.net` / `12201` | GELF sink |
| `nsm_gelf_source` | `{{ inventory_hostname }}` | GELF `host` field |
| `ntopng_domain` | `ntop.starnix.net` | cert CN |
| `nsm_cf_api_token` | `{{ vault_certbot_cloudflare_api_token }}` | Cloudflare DNS-01 token (see note) |
| `suricata_suppress` | 5 mirror-noise SIDs | `suppress` lines in threshold.config |
| `nsm_zeek_cron` | `true` | install the `zeekctl cron` housekeeping job |

## Usage
```
ansible-playbook -i inventory/inventory.yml playbooks/nsm_sensor.yml
# one component:
ansible-playbook -i inventory/inventory.yml playbooks/nsm_sensor.yml --tags suricata
```

## Prerequisites (before the `cert` tag)
- `vault_certbot_cloudflare_api_token` currently lives in `group_vars/storage`
  (mimir scope) -- huginn is NOT in `storage`, so it is undefined here. Provision
  it in huginn's scope before running the cert task: add a vaulted
  `group_vars/nsm.yml` with `vault_certbot_cloudflare_api_token: <token>`, or move
  it to `group_vars/all` if you want it cluster-wide. Until then `nsm_cf_api_token`
  falls back to `CHANGE_ME_IN_VAULT` and certbot will fail.

## One-time cutover on the already-hand-built huginn
- `local.zeek` already has the three tap lines added by hand (unmarked); this
  role appends its own marker-guarded copy, so on the existing host remove the
  hand-added lines once so only the `# ANSIBLE nsm_sensor` block remains. A fresh
  host has no duplication.
- Remove the old `acme.sh --cron` root-crontab line (certbot now owns renewal).
- First apply rewrites config headers to `# IaC Controlled`, which restarts
  ntopng/suricata/fluent-bit and redeploys Zeek -- brief capture gap; do it in a
  quiet window.

## Notes / not managed
- **Mirror NIC changes are rc.conf-only** -- the role does not bounce `netif`
  (that would drop live capture); they apply on reboot, or by hand.
- **wazuh-agent** and the Suricata `eve.json` -> Wazuh `<localfile>` are owned by
  the `wazuh_agent` role (huginn is in `wazuh_agents`), not here.
- **ntopng admin password** is set at first UI login (admin/admin), not codified.
- **acme.sh cutover:** ntopng previously renewed via acme.sh. After this role
  takes over with certbot, remove the old acme.sh cron so two renewers don't both
  rewrite the PEM: `crontab -e` on huginn and delete the `acme.sh --cron` line
  (leave `/root/.acme.sh` in place, dormant).
- `digest_salt` in `local.zeek` is left at the stock placeholder.
