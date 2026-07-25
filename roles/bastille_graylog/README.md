# bastille_graylog

Provision the monolithic **Graylog** jail on a FreeBSD 15 host (Bastille),
reproducing the retired iocage `graylog` jail: **Graylog + MongoDB + OpenSearch**
in a single VNET jail at `192.168.1.151`.

> This is the highest-risk migration. The ~764 GB OpenSearch index tree lives
> *inside* the old jail root with no data seam. This role does **not** move that
> data -- it expects the data to be **pre-staged onto datasets in Phase 0** (from
> the STOPPED old jail) and simply nullfs-mounts it. See the migration design doc.

## What it does

1. Ensures Bastille + release (via the `bastille` role dependency).
2. Creates the VNET jail (L2-bridged onto `bridge0`).
3. Installs `graylog`, `mongodb70`, the OpenSearch package, `openjdk21`, tools.
4. **Asserts service uids** (opensearch 855, mongodb 922, graylog 848) match the
   staged data ownership.
5. nullfs-mounts the three staged data dirs (OpenSearch, MongoDB, graylog data).
6. Installs the carried **node-id** (`c9dbffc7-…`) so Graylog resumes as the same
   node (index ranges / input bindings depend on it).
7. Templates `mongodb.conf`, `opensearch.yml`, the JVM heap override, and the key
   `graylog.conf` settings (single, correct `root_password_sha2` -- **not** the
   `sha256("password")` duplicate the old config carried).
8. Starts services **in order** (mongod -> opensearch -> graylog) with readiness
   waits, then verifies the Graylog API is healthy.

## Requirements / caveats

- **Pre-stage data in Phase 0** onto `graylog_os_data_src`,
  `graylog_mongo_data_src`, `graylog_data_src` (defaults under `/mnt/Store/...`).
- `opensearch210-2.10.0` (the exact old version) is **EOL and may be absent** from
  the FreeBSD 15 repo. Override `graylog_opensearch_pkg` with the available 2.x
  build -- 2.10 indices are read by newer 2.x without a reindex (do not jump to 3.x).
- Provide `vault_graylog_password_secret` (must equal the OLD value, or encrypted
  fields/tokens won't decrypt) and `vault_graylog_root_password_sha2` via vault.

## Key variables

| Variable | Default | Notes |
|----------|---------|-------|
| `graylog_jail_ip` | `192.168.1.151` | LAN address |
| `graylog_opensearch_pkg` | `opensearch210` | override if EOL/absent |
| `graylog_os_data_src` | `/mnt/Store/graylog-opensearch` | ~764 GB, nullfs |
| `graylog_node_id` | `c9dbffc7-…` | carried identity |
| `graylog_password_secret` | `vault_…` | MUST match old value |
| `graylog_root_password_sha2` | intended hash | not the weak duplicate |

## Usage

```bash
ansible-playbook playbooks/graylog.yml
```
