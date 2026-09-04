# FreeIPA-on-FreeBSD Replica (`ipa10`) Spike Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up a manually-driven, verified CA-less FreeIPA 4.13 replica of production `ipa9.starnix.net` inside a Bastille VNET jail (`ipa10`, 192.168.1.60) on `beastyboi`, capturing every command so it can be codified later.

**Architecture:** A thin VNET Bastille jail on `beastyboi`'s `jailbridge` gets a real LAN address and the FreeBSD `net/freeipa-server` port (FreeIPA 4.13.2). After DNS is in place and the port is confirmed to carry MIT Kerberos + a working `ipa-replica-install`, the jail is enrolled as a client of `ipa9` and promoted to a **CA-less** replica (no Dogtag). Production `ipa9` is protected by a pre-flight `ipa-backup` and a documented rollback.

**Tech Stack:** FreeBSD 15.0-RELEASE, Bastille (VNET jails), `net/freeipa-server` (FreeIPA 4.13.2, 389-ds + MIT krb5 + Apache), poudriere/ports (fallback build), Knot DNS via the `~/dns` repo, Rocky 9.8 `ipa9` (FreeIPA 4.13.1) as the replication master.

**Design spec:** `docs/superpowers/specs/2026-09-04-freeipa-freebsd-replica-design.md`

---

## Conventions (read before starting)

All commands are run **from the control host** (where `~/dns` lives and SSH works), unless a step says "inside the jail".

- SSH to the jail host: `ssh cloud@beastyboi.starnix.net` (privileged commands need `sudo`).
- SSH to the master: `ssh cloud@ipa9.starnix.net` (privileged commands need `sudo`).
- Run a command **inside the jail** from the host: `sudo bastille cmd ipa10 <command>`.
- An interactive jail shell: `sudo bastille console ipa10`.
- The FreeBSD SSH is `-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null` friendly; a reusable prefix:
  `SSH="ssh -o ConnectTimeout=8 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o LogLevel=QUIET"`
- **Admin password:** several steps need the IPA `admin` password. Export it locally for the session, never commit it:
  `read -rs ADMIN_PW` (then it is available as `$ADMIN_PW`). Pass it into the jail commands inline; do not write it to any file.

**Fixed parameters** (from the spec):

| Name | Value |
|---|---|
| Jail / FQDN | `ipa10` / `ipa10.starnix.net` |
| Jail IP / gw / mask | `192.168.1.60` / `192.168.1.1` / `/24` |
| Resolver | `192.168.1.53` (the `unbound` jail) |
| Bridge / release | `jailbridge` / `15.0-RELEASE` |
| Realm / domain / basedn | `STARNIX.NET` / `starnix.net` / `dc=starnix,dc=net` |
| Master | `ipa9.starnix.net` (192.168.1.26), FreeIPA 4.13.1, domain level 1, integrated CA, no integrated DNS |
| CA scope | **CA-less** (no `--setup-ca`) |

---

## Phase 0: Pre-flight & production safety

### Task 0.1: Capture ipa9 baseline and take a backup

**Files:** none (operational). Save all captured output under `docs/superpowers/notes/ipa10-spike-<date>.md` as you go.

- [ ] **Step 1: Get an admin Kerberos ticket on ipa9**

Run:
```bash
ssh cloud@ipa9.starnix.net "echo '$ADMIN_PW' | kinit admin"
```
Expected: no output, exit 0. Verify: `ssh cloud@ipa9.starnix.net klist` shows a `krbtgt/STARNIX.NET@STARNIX.NET` ticket.

- [ ] **Step 2: Record the current topology (baseline for rollback diffing)**

Run:
```bash
ssh cloud@ipa9.starnix.net "sudo ipa server-find; \
  sudo ipa-replica-manage list; \
  sudo ipa-replica-manage list-ruv"
```
Expected: `ipa9.starnix.net` is the only server; note every RUV id printed. Paste this into the notes file. **`ipa10.starnix.net` must NOT appear** (if it does, run the Phase 8 rollback first).

- [ ] **Step 3: Take a full backup of ipa9**

Run:
```bash
ssh cloud@ipa9.starnix.net "sudo ipa-backup"
```
Expected: ends with `The ipa-backup command was successful`; note the printed backup path under `/var/lib/ipa/backup/`.

- [ ] **Step 4: Confirm clocks agree (Kerberos tolerates +/-5 min)**

Run:
```bash
ssh cloud@ipa9.starnix.net date -u; ssh cloud@beastyboi.starnix.net date -u
```
Expected: the two UTC times are within a few seconds. If not, STOP and fix NTP on beastyboi before continuing (see Task 4.2).

- [ ] **Step 5: Commit the notes file**

```bash
git add docs/superpowers/notes/ipa10-spike-*.md
git commit -m "docs(ipa): record ipa9 baseline before ipa10 replica spike"
```

---

## Phase 1: DNS (must land before install)

### Task 1.1: Add the ipa10 A record to the ~/dns repo

**Files:**
- Modify: `~/dns/starnix.net.zone` (add A record, bump SOA serial)

- [ ] **Step 1: Confirm 192.168.1.60 is genuinely unused**

Run:
```bash
grep -n "192.168.1.60" ~/dns/starnix.net.zone || echo "not in zone (good)"
ssh cloud@beastyboi.starnix.net "sudo bastille list | grep 192.168.1.60" || echo "no jail on .60 (good)"
```
Expected: both report the address is unused. If either matches, pick the next free IP and update the spec before continuing.

- [ ] **Step 2: Add the A record**

Edit `~/dns/starnix.net.zone`. Under the "Host records" block (next to the existing `ipa9` / `beastyboi` lines) add:
```
ipa10                   IN  A     192.168.1.60
```

- [ ] **Step 3: Bump the SOA serial**

In the SOA block at the top of `~/dns/starnix.net.zone`, increment the serial (format `YYYYMMDDnn`). Example: change `2026072501` to today's date-based serial, e.g. `2026090401`. (Reverse zone needs no edit -- Knot synthesises PTRs from A records.)

- [ ] **Step 4: Commit and push**

```bash
cd ~/dns && git add starnix.net.zone && \
  git commit -m "Add ipa10 A record for FreeBSD FreeIPA replica" && git push
```
Expected: push succeeds. (Knot is fed via ArgoCD from this repo; allow a minute or two to propagate.)

- [ ] **Step 5: Verify forward + reverse resolution via the resolver**

Run (retry until both succeed):
```bash
host ipa10.starnix.net 192.168.1.53
host 192.168.1.60 192.168.1.53
```
Expected: forward returns `192.168.1.60`; reverse returns `ipa10.starnix.net`. **Do not proceed to install until both resolve** -- FreeIPA install fails without forward and reverse records.

---

## Phase 2: Create the jail

### Task 2.1: Create and network the ipa10 VNET jail

**Files:** none (host state). Record commands in the notes file.

- [ ] **Step 1: Confirm host prerequisites**

Run:
```bash
ssh cloud@beastyboi.starnix.net "uname -r; which bastille; ifconfig jailbridge >/dev/null && echo jailbridge-ok; \
  ls -d /usr/local/bastille/releases/15.0-RELEASE 2>/dev/null || echo 'release missing'"
```
Expected: `15.0-RELEASE-p11`, a bastille path, `jailbridge-ok`. If the release is missing, run `ssh cloud@beastyboi.starnix.net "sudo bastille bootstrap 15.0-RELEASE"` first.

- [ ] **Step 2: Create the VNET jail on jailbridge**

Run:
```bash
ssh cloud@beastyboi.starnix.net \
  "sudo bastille create -B -g 192.168.1.1 ipa10 15.0-RELEASE 192.168.1.60/24 jailbridge"
```
Expected: ends with the jail being created; `sudo bastille list` shows `ipa10` Up at `192.168.1.60`.
(`-B` attaches VNET to the existing bridge; `-g` sets the default route so pkg has network.)

- [ ] **Step 3: Seed resolver and default route inside the jail**

Run:
```bash
ssh cloud@beastyboi.starnix.net "bash -s" <<'EOF'
JR=/usr/local/bastille/jails/ipa10/root
printf 'search starnix.net\nnameserver 192.168.1.53\n' | sudo tee $JR/etc/resolv.conf
sudo sysrc -f $JR/etc/rc.conf defaultrouter=192.168.1.1
sudo bastille restart ipa10
EOF
```
Expected: `resolv.conf` written, `defaultrouter` set, jail restarts.

- [ ] **Step 4: Verify jail networking and DNS**

Run:
```bash
ssh cloud@beastyboi.starnix.net "sudo bastille cmd ipa10 sh -c '\
  ping -c1 192.168.1.1 >/dev/null && echo gw-ok; \
  host ipa9.starnix.net 192.168.1.53 | head -1; \
  host ipa10.starnix.net 192.168.1.53 | head -1'"
```
Expected: `gw-ok`, ipa9 resolves to `192.168.1.26`, ipa10 resolves to `192.168.1.60`.

- [ ] **Step 5: Verify the jail can reach ipa9 on the IPA ports (pf check)**

Run:
```bash
ssh cloud@beastyboi.starnix.net "sudo bastille cmd ipa10 sh -c '\
  for p in 389 88 443 464; do nc -z -w3 ipa9.starnix.net \$p && echo port-\$p-open || echo port-\$p-BLOCKED; done'"
```
Expected: `389`, `88`, `443`, `464` all `open`. If any is `BLOCKED`, fix `beastyboi`'s hand-maintained `pf.conf` to allow the jail before continuing.

---

## Phase 3: Packaging -- get freeipa-server with MIT Kerberos

### Task 3.1: Attempt the stock package and inspect GSSAPI flavor

**Files:** none. Record findings in the notes file (this determines the codification path later).

- [ ] **Step 1: Try installing the stock package**

Run:
```bash
ssh cloud@beastyboi.starnix.net \
  "sudo bastille cmd ipa10 env ASSUME_ALWAYS_YES=yes pkg install -y freeipa-server"
```
Expected: either it installs, or it errors. Capture the full output either way.

- [ ] **Step 2: Verify MIT Kerberos (not base Heimdal) was pulled in**

Run:
```bash
ssh cloud@beastyboi.starnix.net "sudo bastille cmd ipa10 sh -c '\
  pkg info -e krb5 && echo MIT-krb5-present || echo MIT-krb5-MISSING; \
  pkg info -e cyrus-sasl && echo sasl-present; \
  pkg info freeipa-server 2>/dev/null | head -3'"
```
Expected (success path): `MIT-krb5-present` and `freeipa-server` shown. **If `MIT-krb5-MISSING`** or the package is absent/misbuilt, the stock package is unusable -- proceed to Task 3.2. Otherwise **skip Task 3.2**.

### Task 3.2 (conditional): Build freeipa-server with GSSAPI_MIT from ports

Do this **only if Task 3.1 showed the stock package lacks MIT GSSAPI or is unavailable.** Build with poudriere on the host so the toolchain stays out of the IPA jail.

**Files:**
- Create on beastyboi: `/usr/local/etc/poudriere.d/make.conf` additions

- [ ] **Step 1: Ensure poudriere and a ports tree/jail exist**

Run:
```bash
ssh cloud@beastyboi.starnix.net "which poudriere || sudo pkg install -y poudriere-devel; \
  sudo poudriere jail -l; sudo poudriere ports -l"
```
Expected: a `15.0-RELEASE` build jail and a ports tree are listed. If missing, create them:
`sudo poudriere jail -c -j fbsd150 -v 15.0-RELEASE` and `sudo poudriere ports -c -p default`.

- [ ] **Step 2: Force MIT GSSAPI in the poudriere make.conf**

Append to `/usr/local/etc/poudriere.d/make.conf` on beastyboi:
```make
# FreeIPA needs MIT Kerberos, not base Heimdal.
DEFAULT_VERSIONS+=  krb5=krb5
security_cyrus-sasl2_SET=  GSSAPI_MIT
security_cyrus-sasl2_UNSET=  GSSAPI_BASE
security_py-gssapi_SET=  GSSAPI_MIT
net_freeipa-server_SET=  GSSAPI_MIT
```

- [ ] **Step 3: Build the package set**

Run:
```bash
ssh cloud@beastyboi.starnix.net \
  "sudo poudriere bulk -j fbsd150 -p default net/freeipa-server"
```
Expected: build completes; packages land under `/usr/local/poudriere/data/packages/fbsd150-default/`.

- [ ] **Step 4: Point the jail at the local repo and install**

Run:
```bash
ssh cloud@beastyboi.starnix.net "bash -s" <<'EOF'
JR=/usr/local/bastille/jails/ipa10/root
sudo mkdir -p $JR/usr/local/etc/pkg/repos
printf 'poudriere: { url: "file:///packages", enabled: yes }\nFreeBSD: { enabled: no }\n' \
  | sudo tee $JR/usr/local/etc/pkg/repos/poudriere.conf
sudo bastille mount ipa10 /usr/local/poudriere/data/packages/fbsd150-default /packages nullfs ro 0 0
sudo bastille cmd ipa10 env ASSUME_ALWAYS_YES=yes pkg install -y freeipa-server
EOF
```
Expected: `freeipa-server` installs from the local `poudriere` repo. Re-run Task 3.1 Step 2 to confirm `MIT-krb5-present`.

### Task 3.3: Feasibility gate -- confirm ipa-replica-install works BEFORE touching production

**Files:** none.

- [ ] **Step 1: Confirm the replica installer exists and runs**

Run:
```bash
ssh cloud@beastyboi.starnix.net "sudo bastille cmd ipa10 sh -c '\
  which ipa-replica-install && ipa-replica-install --help >/dev/null 2>&1 && echo replica-install-ok'"
```
Expected: a path plus `replica-install-ok`. **If this fails, STOP** -- the port does not support replica promotion; do not proceed to Phase 5. Record the failure; the spike's conclusion is "replica unsupported on this port build" and the master remains untouched.

---

## Phase 4: Base jail configuration for FreeIPA

### Task 4.1: Set identity and required services

**Files:** none (jail state).

- [ ] **Step 1: Set the FQDN hostname and /etc/hosts**

Run:
```bash
ssh cloud@beastyboi.starnix.net "bash -s" <<'EOF'
JR=/usr/local/bastille/jails/ipa10/root
sudo sysrc -f $JR/etc/rc.conf hostname=ipa10.starnix.net
printf '127.0.0.1 localhost\n192.168.1.60 ipa10.starnix.net ipa10\n' | sudo tee $JR/etc/hosts
sudo bastille cmd ipa10 hostname ipa10.starnix.net
EOF
```
Expected: `sudo bastille cmd ipa10 hostname` prints `ipa10.starnix.net`.

- [ ] **Step 2: Enable and start dbus and gssproxy (required by the port)**

Run:
```bash
ssh cloud@beastyboi.starnix.net "bash -s" <<'EOF'
sudo bastille cmd ipa10 sysrc dbus_enable=YES
sudo bastille cmd ipa10 sysrc gssproxy_enable=YES
sudo bastille cmd ipa10 service dbus start
sudo bastille cmd ipa10 service gssproxy start
sudo bastille cmd ipa10 sh -c 'service dbus status && service gssproxy status'
EOF
```
Expected: both services report running. (If `gssproxy` rc script is absent, note it -- the port README says the server uses a direct MIT-krb5 path; continue.)

### Task 4.2: Confirm time sync (host clock -- the jail inherits it)

**Files:** none.

- [ ] **Step 1: Verify beastyboi's clock is NTP-synced**

Run:
```bash
ssh cloud@beastyboi.starnix.net "service ntpd status 2>/dev/null; ntpq -p 2>/dev/null | head -5; date -u"
```
Expected: `ntpd` running with a synced peer (`*` prefix on a peer line). A VNET jail shares the host kernel clock, so no NTP runs inside the jail. If `ntpd` is not running, enable it: `sudo sysrc ntpd_enable=YES ntpd_sync_on_start=YES && sudo service ntpd start`, then re-check.

- [ ] **Step 2: Re-confirm jail time matches ipa9**

Run:
```bash
ssh cloud@beastyboi.starnix.net "sudo bastille cmd ipa10 date -u"; ssh cloud@ipa9.starnix.net date -u
```
Expected: within a few seconds. **Do not proceed past +/-5 min.**

---

## Phase 5: Enroll and promote to a CA-less replica (touches production)

### Task 5.1: Enroll ipa10 as an IPA client of ipa9

**Files:** none.

- [ ] **Step 1: Run the client install unattended**

Run (substitute the admin password via `$ADMIN_PW`; it is not written to disk):
```bash
ssh cloud@beastyboi.starnix.net \
  "sudo bastille cmd ipa10 ipa-client-install -U \
     --server=ipa9.starnix.net --domain=starnix.net --realm=STARNIX.NET \
     --principal=admin --password='$ADMIN_PW' --no-ntp --force-join"
```
Expected: ends with `Client configuration complete.`

- [ ] **Step 2: Verify enrollment (get a ticket and query the directory)**

Run:
```bash
ssh cloud@beastyboi.starnix.net "sudo bastille cmd ipa10 sh -c \"echo '$ADMIN_PW' | kinit admin && ipa user-find admin | head -3\""
```
Expected: `kinit` succeeds and `ipa user-find` returns the `admin` user (proves Kerberos + LDAP against ipa9 work from the jail).

### Task 5.2: Promote ipa10 to a CA-less replica

**Files:** none. **This step writes into ipa9's directory** -- the Phase 0 backup is your safety net.

- [ ] **Step 1: Run the replica install without a CA**

Run:
```bash
ssh cloud@beastyboi.starnix.net \
  "sudo bastille cmd ipa10 ipa-replica-install -U \
     --principal=admin --admin-password='$ADMIN_PW' --no-ntp"
```
Expected: ends with `The ipa-replica-install command was successful`. (Omitting `--setup-ca` makes this a CA-less replica; certs are served by ipa9's CA.)

- [ ] **Step 2: Enable FreeIPA at boot inside the jail**

Run:
```bash
ssh cloud@beastyboi.starnix.net "sudo bastille cmd ipa10 sh -c 'sysrc freeipa_server_enable=YES dbus_enable=YES gssproxy_enable=YES'"
```
Expected: rc.conf updated.

- [ ] **Step 3: Confirm all services are up in the jail**

Run:
```bash
ssh cloud@beastyboi.starnix.net "sudo bastille cmd ipa10 ipactl status"
```
Expected: `Directory Service`, `krb5kdc`, `kadmin`, `httpd` (and `ipa-otpd`, `ipa-custodia`) all `RUNNING`. (No `pki-tomcatd` -- this is CA-less.)

---

## Phase 6: Verification (definition of done)

### Task 6.1: Confirm the master sees a healthy replica

**Files:** none.

- [ ] **Step 1: ipa9 lists ipa10 as a server with a replication agreement**

Run:
```bash
ssh cloud@ipa9.starnix.net "sudo ipa server-find | grep -i ipa10; \
  sudo ipa-replica-manage list; sudo ipa-replica-manage list-ruv"
```
Expected: `ipa10.starnix.net` appears in `server-find`; `ipa-replica-manage list` shows the ipa9<->ipa10 agreement; RUVs list a new ipa10 replica id and no `{cleanallruv}` cruft.

### Task 6.2: Two-way replication test

**Files:** none.

- [ ] **Step 1: Create a user on ipa9, confirm it appears on ipa10**

Run:
```bash
ssh cloud@ipa9.starnix.net "echo '$ADMIN_PW' | kinit admin && \
  sudo ipa user-add repltest1 --first=Repl --last=Test1"
sleep 5
ssh cloud@beastyboi.starnix.net "sudo bastille cmd ipa10 sh -c \"echo '$ADMIN_PW' | kinit admin && ipa user-show repltest1\""
```
Expected: `ipa user-show repltest1` on ipa10 returns the user (proves ipa9 -> ipa10 replication).

- [ ] **Step 2: Create a user on ipa10, confirm it appears on ipa9**

Run:
```bash
ssh cloud@beastyboi.starnix.net "sudo bastille cmd ipa10 sh -c \"echo '$ADMIN_PW' | kinit admin && ipa user-add repltest2 --first=Repl --last=Test2\""
sleep 5
ssh cloud@ipa9.starnix.net "echo '$ADMIN_PW' | kinit admin && sudo ipa user-show repltest2"
```
Expected: `ipa user-show repltest2` on ipa9 returns the user (proves ipa10 -> ipa9 replication).

- [ ] **Step 3: Clean up the test users**

Run:
```bash
ssh cloud@ipa9.starnix.net "echo '$ADMIN_PW' | kinit admin && sudo ipa user-del repltest1 repltest2"
```
Expected: both deleted (and the deletion replicates back to ipa10 -- optional to re-verify).

### Task 6.3: Authenticate directly against ipa10

**Files:** none.

- [ ] **Step 1: Point a kinit explicitly at ipa10 and query**

Run:
```bash
ssh cloud@beastyboi.starnix.net "sudo bastille cmd ipa10 sh -c \"echo '$ADMIN_PW' | kinit admin && ipa -s ipa10.starnix.net user-find admin | head -3\""
```
Expected: returns the `admin` user, served by ipa10 specifically (proves ipa10 answers IPA API requests on its own).

- [ ] **Step 2: Record the spike outcome**

Update `docs/superpowers/notes/ipa10-spike-<date>.md` with: which packaging path was used (stock pkg vs poudriere build), the exact working `ipa-client-install` / `ipa-replica-install` invocations, any deviations, and the final `ipactl status`. Commit:
```bash
git add docs/superpowers/notes/ipa10-spike-*.md
git commit -m "docs(ipa): record successful ipa10 CA-less replica spike"
```
This notes file is the input to the follow-up codification plan.

---

## Phase 7: Post-spike decision

### Task 7.1: Keep or tear down

- [ ] **Step 1: Decide, with the user, whether ipa10 stays running or is torn down.**

If keeping it as a live replica, consider (as a follow-up, out of scope here) adding SRV / a second `ipa-ca` A record in `~/dns` so clients can discover it, and writing the codification plan (`roles/bastille_freeipa`, `playbooks/freeipa_jail.yml`, `inventory/host_vars/beastyboi.starnix.net.yml`).

If tearing down, run the Phase 8 rollback in full so no orphaned agreement or RUV remains on ipa9.

---

## Phase 8: Rollback (run on failure, or to decommission)

### Task 8.1: Remove the replica cleanly from production

**Files:**
- Modify: `~/dns/starnix.net.zone` (remove the A record)

- [ ] **Step 1: Delete the replication agreement and server entry on ipa9**

Run:
```bash
ssh cloud@ipa9.starnix.net "echo '$ADMIN_PW' | kinit admin && \
  sudo ipa-replica-manage del ipa10.starnix.net --force; \
  sudo ipa server-del ipa10.starnix.net"
```
Expected: agreement and server entry removed. (`--force` covers the case where ipa10 is already down.)

- [ ] **Step 2: Clean any leftover RUV for ipa10**

Run:
```bash
ssh cloud@ipa9.starnix.net "sudo ipa-replica-manage list-ruv"
```
If an ipa10 RUV id remains, clean it:
```bash
ssh cloud@ipa9.starnix.net "sudo ipa-replica-manage clean-ruv <RUV_ID>"
```
Expected: `list-ruv` no longer shows an ipa10 entry. Compare against the Phase 0 baseline -- RUVs should match the pre-spike list.

- [ ] **Step 3: Destroy the jail**

Run:
```bash
ssh cloud@beastyboi.starnix.net "sudo bastille stop ipa10; sudo bastille destroy -f ipa10"
```
Expected: jail removed from `sudo bastille list`.

- [ ] **Step 4: Remove the DNS record**

Edit `~/dns/starnix.net.zone`, delete the `ipa10 IN A 192.168.1.60` line, bump the SOA serial, then:
```bash
cd ~/dns && git add starnix.net.zone && git commit -m "Remove ipa10 A record (replica decommissioned)" && git push
```
Expected: push succeeds; `host ipa10.starnix.net 192.168.1.53` stops resolving after propagation.

---

## Notes for the executor

- **Never write `$ADMIN_PW` to a file or commit it.** It is only ever passed inline into `kinit`/`ipa-*-install`.
- The two hard gates that protect production are **Task 3.3** (prove `ipa-replica-install` works before touching ipa9) and **Task 0.1 Step 3** (backup). Do not skip either.
- If any Phase 5 step fails partway, go straight to Phase 8 before retrying -- a half-created agreement left in place will make the next attempt fail confusingly.
- Bite-sized commits: commit the notes file after each phase so progress and the captured commands survive an interrupted session.
