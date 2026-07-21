# bastille_minio

Provision the **MinIO** S3 object-storage jail on a FreeBSD 15 host using
[Bastille](https://bastillebsd.org/). This reproduces the retired iocage `minio`
jail from mimir as part of the TrueNAS Core -> FreeBSD 15 migration.

The bucket data itself is **not** copied by this role -- it rides the `Store` pool
import and is bind-mounted (nullfs) into the jail, exactly as under iocage.

## What it does

1. Ensures Bastille is installed/configured and the release bootstrapped (via the
   `bastille` role dependency).
2. Creates a VNET jail **L2-bridged onto the existing `bridge0`** (real LAN IP
   `192.168.1.150`), if it does not already exist.
3. nullfs-mounts `/mnt/Store/minio` -> `/mnt/minio` inside the jail.
4. Installs `minio` + `minio-client` and **asserts the service account is uid/gid
   473** (must match the on-disk bucket ownership, or MinIO cannot read its data).
5. Deploys an env-file rc.d script, `/usr/local/etc/minio.env` (mode `0600`), and
   `/etc/rc.conf.d/minio`, then enables and starts the service (`:9000` API,
   `:9001` console).
6. Optionally verifies the expected buckets are served (`minio_verify_buckets`).

## Requirements

- FreeBSD 15 target with the `Store` pool imported at `/mnt/Store`.
- `/mnt/Store/minio` present (owner `473:473`).
- `minio_root_password` supplied via **ansible-vault** (never in plaintext).
- Collections: `community.general` (pkgng, sysrc).

## Key variables (see `defaults/main.yml`)

| Variable | Default | Notes |
|----------|---------|-------|
| `minio_jail_ip` | `192.168.1.150` | LAN address of the jail |
| `bastille_network_bridge` | `bridge0` | existing LACP bridge; L2, not NAT |
| `minio_data_src` / `minio_data_dst` | `/mnt/Store/minio` -> `/mnt/minio` | nullfs bucket data |
| `minio_uid` / `minio_gid` | `473` | must match on-disk ownership |
| `minio_root_password` | `vault_minio_root_password` | set in vault |
| `minio_verify_buckets` | `true` | assert expected buckets present |

## Usage

```bash
ansible-playbook playbooks/minio.yml
ansible-playbook playbooks/minio.yml --check   # dry run
```

## Notes

- Idempotent: the jail is created only when missing; every step is guarded.
- Credentials live only in `minio.env` (0600) / ansible-vault, never in git.
- Optional: promote `/mnt/Store/minio` to its own dataset (`zfs create
  Store/minio`) before running, for independent snapshots/replication.
