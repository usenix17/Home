# bastille_watcher

Out-of-cluster watcher: a Bastille VNET jail on beastyboi running Gatus
(`www/gatus`). It exists because in-cluster monitoring can't report its own
death: on 2026-11-22 two Talos nodes went down unnoticed.

## What it checks

- Talos API (TCP 50000) on every node, kube-apiserver (TCP 6443) on the
  control planes, the Traefik ingress VIP, all by IP.
- DNS answers from knot and unbound for a known ingress name.
- Prometheus, Alertmanager, Grafana, Authentik, Graylog, OpenBao, bork vLLM
  health endpoints, and the mimir NFS port.
- The AI triage receiver's `/health` (token file, Claude binary, token age).
- **Dead man's switch:** Alertmanager posts its always-firing `Watchdog` to
  the external endpoint `cluster_alertmanager-watchdog`; no push within
  `watcher_heartbeat_interval` alerts "in-cluster alerting has gone silent".

Alerts go to Discord. Every endpoint except `triage /health` also posts to
the triage receiver (Gatus `custom` alert, `roles/bastille_triage`), which
diagnoses it read-only; `triage /health` is Discord only so a broken triage
jail is never asked to triage itself. The UI is LAN-only at
`http://192.168.1.60:8080`.

## Secrets

Supplied at run time from OpenBao `cluster/monitoring-secrets`:
`discordWebhookUrl` -> `vault_watcher_discord_webhook`, `watcherPushToken` ->
`vault_watcher_push_token`, `triagePushToken` -> `vault_triage_push_token`.
The watcher token is the bearer credential in the Alertmanager webhook
(ArgoCD `applications/monitoring/alertmanager-config.yaml`).

## Run

    ansible-playbook playbooks/watcher.yml -e @watcher-secrets.yml --check --diff
    ansible-playbook playbooks/watcher.yml -e @watcher-secrets.yml

Connect by IP (`-e ansible_host=192.168.1.99`) to avoid the `*.starnix.net`
cert-first ssh wildcard tripping the Wazuh sshd ban.
