# bastille_certbot

Provision the **certbot** jail on a FreeBSD 15 host (Bastille), reproducing the
retired iocage `certbot` jail. It renews the `mimir.starnix.net` Let's Encrypt
certificate using the **DNS-01 challenge via Cloudflare**, on a weekly
`periodic(8)` timer.

## What it does

1. Ensures Bastille + release (via the `bastille` role dependency).
2. Creates a VNET jail (static `192.168.1.9`, L2-bridged onto `bridge0`).
3. Installs `py311-certbot` + `py311-certbot-dns-cloudflare`.
4. nullfs-mounts the staged Let's Encrypt config into `/etc/letsencrypt`.
5. Deploys the Cloudflare API-token credential (`0600`, from vault).
6. Enables weekly renewal via `periodic(8)`.
7. Verifies certbot lists the `mimir.starnix.net` certificate.

## Migration notes

- The old jail's renewal hook (`/opt/deploy-freenas/deploy_freenas.py`) pushed the
  cert into the **TrueNAS API**, which is **retired** after migration. Set
  `certbot_deploy_hook` only if another service now needs the renewed cert.
- Stage `/etc/letsencrypt` from the old jail onto `certbot_le_src`
  (`/mnt/Backup/letsencrypt` by default) in Phase 0 -- the role does not create
  certs, it carries the existing account/live/renewal data.
- Adjust the `py311-*` package prefix if the FreeBSD 15 default python differs.

## Key variables

| Variable | Default | Notes |
|----------|---------|-------|
| `certbot_jail_ip` | `192.168.1.9` | static (was DHCP) |
| `certbot_le_src` / `certbot_le_dst` | `/mnt/Backup/letsencrypt` -> `/etc/letsencrypt` | nullfs config |
| `certbot_cloudflare_api_token` | `vault_certbot_cloudflare_api_token` | set in vault |
| `certbot_deploy_hook` | `""` | old TrueNAS hook retired |
| `certbot_primary_domain` | `mimir.starnix.net` | verification target |

## Usage

```bash
ansible-playbook playbooks/certbot.yml
ansible-playbook playbooks/certbot.yml --check
```
