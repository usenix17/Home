# opensnitch_wazuh

Ships OpenSnitch application-firewall events into Wazuh.

OpenSnitch (>= 1.6.0) can stream every connection decision as JSON to a syslog
endpoint (`Server.Loggers` in `default-config.json`). That stream is a firehose
and each event carries a large, secret-bearing `process_env`. This role stands
up a small bridge that tames it:

```
opensnitchd --(remote_syslog, JSON, UDP 127.0.0.1:5140)--> bridge
   bridge: drop process_env, keep denies + first-seen (process,dst)
     --> /var/log/opensnitch/events.json (ndjson)
       --> Wazuh json localfile --> manager rules 100750-100752
```

## What it does

- Installs `opensnitch_wazuh_bridge.py` as `/usr/local/bin/opensnitch-wazuh-bridge`
  and runs it as a hardened `DynamicUser` systemd service.
- Patches `Server.Loggers` in the OpenSnitch daemon config (idempotent merge,
  every other key preserved) to emit JSON to the bridge.
- Appends a `json` localfile to the Wazuh agent's `ossec.conf` (host-local,
  same pattern as the arch role's Falco wiring).
- Rotates the event log with `copytruncate` (the bridge holds the file open).

## Scope of what reaches Wazuh

- **Every deny** (`bridge_reason=deny`).
- **First-seen** `(process_path, dst_host|dst_ip)` pairs, re-surfaced after
  `opensnitch_wazuh_dedup_ttl` (default 24h).
- Repeat allows are dropped. `process_env` is never forwarded.

## Manager side

The Wazuh manager rules (`100750-100752`) live in the ArgoCD repo
(`applications/wazuh/wazuh-manager.yaml`), not here. Deny -> level 6,
first-seen egress -> level 3.

## Variables

See `defaults/main.yml`. Common overrides: `opensnitch_wazuh_listen_port`,
`opensnitch_wazuh_dedup_ttl`, `opensnitch_wazuh_output`.

## Requirements

OpenSnitch >= 1.6.0 (SIEM logger support) already installed and running.
