# FreeIPA-on-FreeBSD replica (`ipa10`) -- Design

- **Date:** 2026-09-04
- **Status:** Approved (brainstorming); ready for implementation planning
- **Author:** IaC / brainstorming session

## 1. Problem & goal

We want to evaluate the FreeBSD port of FreeIPA (now upstreamed as
`net/freeipa-server`, FreeIPA 4.13.2) by standing up a **replica of the
production FreeIPA server `ipa9.starnix.net`** inside a **Bastille VNET jail on
`beastyboi`**.

The port is young and the maintainer explicitly states it is "not recommended
for production" and that multi-server topologies need testing. Therefore the
work is done as a **manual, fully documented spike first**, proving replication
works and capturing every command, and is **only then codified** into an
idempotent Ansible role.

### Why a replica (accepted risk)

The user chose a replica of the production realm rather than a standalone test
realm. A replica is **not** isolated: `ipa-replica-install` creates a
replication agreement that writes into `ipa9`'s live 389 Directory Server. A
failed or half-configured replica can leave orphaned replication agreements,
RUV entries, or replication conflicts on `ipa9` itself. This is recoverable
(see Section 7, Rollback) but it means the procedure **touches production** and
must be guarded by a pre-flight backup.

### Success criteria (definition of done for the spike)

1. `ipactl status` inside the `ipa10` jail reports every service RUNNING.
2. On `ipa9`, `ipa server-find` lists `ipa10.starnix.net`, and
   `ipa-replica-manage list` shows a healthy agreement with clean RUVs.
3. **Two-way replication verified:** a user created on `ipa9` appears on
   `ipa10`, and a user created on `ipa10` appears on `ipa9`.
4. `kinit` + `ipa user-find` succeed when a client is pointed explicitly at
   `ipa10.starnix.net`.

## 2. Discovered facts (baseline)

Gathered read-only during brainstorming on 2026-09-04.

### Production server `ipa9.starnix.net`
| Property | Value |
|---|---|
| OS | Rocky Linux 9.8 |
| FreeIPA | `ipa-server 4.13.1` (FreeBSD port is 4.13.2 -- matches on 4.13) |
| 389-ds / krb5 | `389-ds-base 2.8.0` / `krb5-server 1.21.1` |
| Domain level | `1` (supports one-step `ipa-replica-install` promotion) |
| Integrated CA | **Yes** -- Dogtag / `pki-tomcatd` running |
| Integrated DNS | **No** -- `ipa-server-dns` not installed |
| IP | `192.168.1.26` (`ipa9`), CA alias `ipa-ca -> 192.168.1.26` |

### DNS (as code)
- Managed in the `~/dns` git repo (`github.com/usenix17/dns`), served
  authoritatively by **Knot** (ArgoCD-managed; see `applications/knot/knot.yaml`
  in the ArgoCD repo). `ns1.starnix.net` = `192.168.7.211`.
- Client-facing resolver is the **`unbound` jail at `192.168.1.53`**, which runs
  on beastyboi itself.
- Zone files: `starnix.net.zone` (forward) and `168.192.in-addr.arpa.zone`
  (reverse). The reverse zone is **apex-only**; Knot **synthesises all PTR
  records** from the forward A records (`zonefile-load: difference-no-serial`),
  so no manual PTR edit is needed.
- Existing relevant records: `ipa9 -> .26`, `ipa-ca -> .26`, a stale-looking
  `ipa2 -> .25`, and all `_kerberos`/`_ldap`/`_kpasswd` SRV + URI records point
  **only to ipa9**.

### Host `beastyboi.starnix.net`
| Property | Value |
|---|---|
| OS | FreeBSD 15.0-RELEASE-p11 |
| RAM | 16 GB (`hw.physmem` = 16892551168) |
| Bastille | Installed; already running jails `aim`, `gvm`, `nebula`, `nrelay`, `snakes`, `unbound` on `192.168.1.53,.55-.59` |
| Jail bridge | **`jailbridge`** (NOT `bridge0`, which is what mimir uses) |
| IP | `192.168.1.99` (the host itself) |

## 3. Chosen parameters

| Item | Value | Notes |
|---|---|---|
| Jail name | `ipa10` | |
| FQDN | `ipa10.starnix.net` | `ipa9` is live; `ipa2/.25` looks stale -- avoid both |
| IP / gw | `192.168.1.60` / `192.168.1.1` | `.60` is free: not in the zone, sits after the beastyboi jail block `.53-.59` |
| Resolver | `192.168.1.53` | the `unbound` jail |
| Release | `15.0-RELEASE` | matches host userland |
| Bridge | `jailbridge` | beastyboi-specific |
| Realm / domain / basedn | `STARNIX.NET` / `starnix.net` / `dc=starnix,dc=net` | |
| CA scope (v1) | **CA-less** | no `--setup-ca`; certs issued by ipa9's CA. Add `ipa-ca-install` later |
| DNS discovery records | **Deferred** | v1 leaves clients on ipa9; add SRV/`ipa-ca` only once proven |

> **IP note:** neither the zone file nor a ping-scan is a complete IP registry
> (the zone omits the beastyboi jails; ICMP is filtered on some hosts). `.60`
> was chosen by excluding both the zone assignments and the running-jail IPs.
> Re-confirm `.60` is unused immediately before creating the jail.

## 4. Architecture / sequence

### 4.1 DNS-as-code prerequisite (must land first)
Kerberos requires forward **and** reverse resolution of the replica FQDN before
install.
1. In `~/dns/starnix.net.zone`: add `ipa10  IN  A  192.168.1.60`; bump the SOA
   serial.
2. Commit + push; wait for Knot/ArgoCD to serve the new serial.
3. Verify both directions resolve **via `192.168.1.53`**:
   `host ipa10.starnix.net` and `host 192.168.1.60` (PTR synthesised by Knot).

### 4.2 Jail creation (Bastille, VNET on `jailbridge`)
Mirror the proven `bastille_graylog` pattern:
- `bastille create -B -g 192.168.1.1 ipa10 15.0-RELEASE 192.168.1.60/24 jailbridge`
- Seed `resolv.conf` (nameserver `192.168.1.53`), set `defaultrouter`, start jail.

### 4.3 Packaging -- the main risk (`GSSAPI_MIT`)
FreeIPA requires **MIT** Kerberos, not base Heimdal. Cheapest-first:
1. Try `pkg install freeipa-server` in the jail; inspect whether it pulled MIT
   `krb5` and MIT-built `cyrus-sasl2` / `py-gssapi`.
2. If the stock package is built wrong, **build from ports** with `make.conf`
   forcing `GSSAPI_MIT` on `security/cyrus-sasl2`, `security/py-gssapi`, and
   `net/freeipa-server` -- preferably via **poudriere on the host** (or a
   dedicated build jail) so the heavy toolchain does not live in the IPA jail.

### 4.4 In-jail install
1. Set hostname `ipa10.starnix.net`; fix `/etc/hosts`, `resolv.conf`,
   `defaultrouter`.
2. **NTP:** enable `ntpd`; confirm clock within Kerberos's +/-5 min (skew is the
   number-one replica failure).
3. Enable + start `dbus` and `gssproxy` (both required by the port).
4. `ipa-client-install` against `ipa9` -> `kinit admin` ->
   `ipa-replica-install` **without `--setup-ca`**, `--no-ntp`.
5. Enable `freeipa_server`, `dbus`, `gssproxy` in `rc.conf`.

### 4.5 Host firewall (pf)
beastyboi runs a hand-maintained `pf.conf`. The jail is VNET with a real LAN
address, so confirm pf does not block the jail reaching `ipa9` on 389/636,
88/464 (tcp+udp), 80/443, and that the LAN can reach the jail on the same. (CA
ports 8080/8443 are not needed in CA-less v1.)

## 5. Components (phase 2 codification -- only after the spike passes)

Each is a small, independently reviewable unit modeled on existing repo
patterns.

- **`roles/bastille_freeipa`** -- modeled on `roles/bastille_graylog`:
  create VNET jail -> seed resolv/route -> install or build packages ->
  idempotent replica-install (guarded on `ipactl status` / `ipa server-find`)
  -> enable services. Defaults in `defaults/main.yml`; no secrets hard-coded.
- **`playbooks/freeipa_jail.yml`** -- targets beastyboi; applies the role.
- **`inventory/host_vars/beastyboi.starnix.net.yml`** -- beastyboi's Bastille
  vars (`bastille_network_bridge: jailbridge`, prefix, zpool), since the current
  `roles/bastille/defaults/main.yml` values target mimir.
- **`~/dns` change** -- the `ipa10` A record, landed as a PR in that repo.
- **Secrets** -- admin password via `ansible-vault` if the promotion step is
  automated; otherwise the promotion stays a manual step.

## 6. Testing strategy

- **Spike:** validated by the Section 1 success criteria, primarily the two-way
  replication test.
- **Role idempotency:** re-running `playbooks/freeipa_jail.yml` converges with no
  changes (guards on `ipactl status` / `ipa server-find`).
- **Documentation:** the spike runbook and outcomes recorded under `docs/`.

## 7. Safeguards & rollback (production touch)

**Before install (pre-flight on ipa9):**
- Run a full `ipa-backup`.
- Record current state: `ipa server-find`, `ipa-replica-manage list`,
  `ipa-replica-manage list-ruv`.

**Rollback if the install fails or must be undone:**
1. On ipa9: `ipa-replica-manage del ipa10.starnix.net --force`
2. On ipa9: `ipa server-del ipa10.starnix.net`
3. On ipa9: `clean-ruv` the stray replica RUV id (from `list-ruv`).
4. Remove the `ipa10` A record from `~/dns` (and let Knot re-serve).
5. Destroy the jail: `bastille destroy ipa10`.

## 8. Open risks

1. **`GSSAPI_MIT` packaging** -- the stock `pkg` may not be built with MIT
   GSSAPI, forcing a poudriere/ports build (largest schedule risk).
2. **Dogtag on FreeBSD** -- deliberately avoided in v1 (CA-less) because
   `pki-tomcat` is the maintainer's flagged-fragile component. Revisit only
   after replication is proven.
3. **Clock skew** -- Kerberos fails hard beyond +/-5 min; NTP must be verified
   before install.
4. **Replication into production** -- mitigated by the pre-flight `ipa-backup`
   and the documented rollback.
5. **`oddjob-mkhomedir` untested on FreeBSD** -- not on the replica critical
   path; note for any client-side home-dir behavior.

## 9. Decisions recorded

- Replica of production STARNIX.NET (not a standalone test realm).
- Manual spike first, then codify.
- CA-less for v1.
- Identity: `ipa10.starnix.net` / `192.168.1.60` / jail `ipa10` on `jailbridge`.
- DNS discovery records (SRV, second `ipa-ca` A) deferred until the replica is
  proven.
- Packaging: try stock `pkg` first, fall back to a `GSSAPI_MIT` ports build.
