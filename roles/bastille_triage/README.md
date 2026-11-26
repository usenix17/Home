# bastille_triage

Read-only AI alert-triage jail: a Bastille VNET jail `triage` on beastyboi
(192.168.1.61) running the **Linux** Claude Code build under linuxulator.
Alertmanager and the watcher's Gatus post alerts to a small receiver in
here, which runs `claude -p` with read-only credentials and posts the
diagnosis to Discord. Kept apart from the `watcher` jail so a wedged or
compromised triage jail can't take the dead man's switch with it. Design:
`docs/superpowers/specs/2026-11-23-triage-agent-design.md`.

## What the role installs

| What | Where (jail path) | Source |
|---|---|---|
| Linux userland | `/compat/linux` | `linux_base-rl9` pkg |
| kubectl (FreeBSD) | `/usr/local/bin/kubectl` | `kubectl` pkg |
| Python | `/usr/local/bin/python3.11` | `python311` pkg |
| Claude Code (Linux) | `/usr/local/lib/triage/claude/claude-<ver>`, link `claude` | pinned, sha256 |
| talosctl (FreeBSD) | `/usr/local/bin/talosctl` | pinned, release sha256 |
| receiver package | `/usr/local/lib/triage/triage/` | `files/triage` |
| query wrappers | `/usr/local/lib/triage/bin/triage-*` | `files/bin` |

The wrappers import the package from their grandparent directory, so
`lib/triage/{triage,bin}` must stay siblings: put `lib/triage/bin` on PATH
rather than symlinking the wrappers elsewhere.

User `triage` (uid/gid 1061, home `/var/db/triage`, nologin) owns only its
home, `state` in it (both 0700) and `/var/log/triage` (0750).
`/usr/local/etc/triage` is `0750 root:triage`. User `triage-agent` (1062)
owns its home `/var/db/triage-agent` and `.claude` in it (0700), but not
`work/` (root:triage-agent 0750). Everything else is root's.

## Linuxulator in a jail

The host already loads linux64, linprocfs, linsysfs and fdescfs
(`linux_enable=YES`); the role does not touch host config. The Linux
filesystems are mounted **from the host** through the jail's Bastille
`fstab`, so they return on every jail start and the jail needs no
`allow.mount*` parameters (none are set):

    linprocfs -> /compat/linux/proc
    linsysfs  -> /compat/linux/sys
    fdescfs   -> /compat/linux/dev/fd   (linrdlnk)
    tmpfs     -> /compat/linux/dev/shm  (mode=1777)

### Why no devfs at /compat/linux/dev

A host-side devfs mount there needs a ruleset (without one it shows every
host device). With `ruleset=4` (devfsrules_jail) `shm` is hidden and devfs
refuses `mkdir`, so `dev/shm` has no mount point (tried, 2026-11-23). It
also isn't needed: the Linux path lookup tries `/compat/linux/<path>` first
and falls back to `<path>`, so `/dev/null`, `/dev/pts` and friends resolve
to the jail's own `/dev`, while `dev/fd` and `dev/shm` hit the mounts above,
which sit on plain directories in the `linux_base` tree.

The jail's own `/dev` uses devfs ruleset 14 (`bastille_net`, see Jail
egress). It needs `vfs.devfs.rule_depth=2` on the host, set by
roles/bastille since 2026-11-23: at 1 the nested includes of ruleset 4 were
skipped and every Bastille jail's `/dev` showed all host devices.

## The shell Claude Code spawns

Claude Code is a Linux process, so its Bash tool starts a Linux bash
(`/bin/bash` resolves to `/compat/linux/usr/bin/bash`, 5.1). From there
FreeBSD binaries exec natively (the kernel picks the ABI per binary) and any
path missing under `/compat/linux` falls back to the jail's own. With

    PATH=/usr/local/lib/triage/bin:/usr/local/bin:/usr/bin:/bin

the allowed tools resolve to `/usr/local/bin/kubectl`,
`/usr/local/bin/talosctl` and
`/usr/local/lib/triage/bin/triage-{kubectl,talosctl,promql,gatus,graylog}`.
The user's login shell is nologin, so whoever starts Claude Code must set
`SHELL=/bin/bash`.

## Claude Code managed settings

Claude Code's lockdown lives in its **managed settings** file, which
outranks user and project settings and can't be overridden by them. The
Linux build reads `/etc/claude-code/managed-settings.json`; under
linuxulator that resolves to the jail path
`/compat/linux/etc/claude-code/managed-settings.json` (root:wheel 0644,
dir 0755). It is root-owned on purpose: `triage-agent` owns its
`~/.claude`, so anything there could be rewritten by the agent itself.

The file is rendered from `triage_claude_managed_settings`: `env` (no
auto-update or `claude update`, no telemetry, error reports or
nonessential traffic) and the `permissions` (allow/deny lists,
`defaultMode: dontAsk`, bypass mode disabled). With
`allowManagedPermissionRulesOnly` and `allowManagedHooksOnly`, rules or
hooks in the agent's `~/.claude/settings.json` are ignored, no MCP server
is allowed (`allowedMcpServers: []`, managed-only, plus `--strict-mcp-config`
on every run), and the role
keeps that file absent. Anything not allowed is denied without a prompt.
The wrappers run as `#!/usr/local/bin/python3.11 -I`, so PYTHONPATH and
user site-packages from the Bash tool's env can't load code into them.
The only Bash allows are the wrappers (`triage-kubectl`, `triage-promql`,
`triage-talosctl`, `triage-gatus`, `triage-graylog`); raw `kubectl` and
`talosctl` are denied, as is every command that lists environments or
processes (`ps`, `procstat`, `env`, `sysctl`, ...), since Claude's own
environment holds the OAuth token. The kernel is the second layer:
`security.bsd.unprivileged_proc_debug=0` kept `ps -axeww`, run as
triage-agent, from showing another process's environment (probed with a
canary, 2026-11-25).
Claude Code also auto-approves its own read-only utilities (`head`, `id`,
...) as pipe filters; the `Read(...)` denies fence them away from the
credential dirs, `~/.claude*` and `/proc`.

To check it is in effect, run `claude doctor` as triage with an empty
environment: it reports `Auto-updates: disabled (set by env:
DISABLE_UPDATES)` (`enabled` if the file is missing).

## Receiver and agent (two uids)

- `triage` (uid 1061) runs the receiver and holds the secrets:
  `/usr/local/etc/triage` (root:triage 0750) with `config.json` and
  `claude.token`, both 0400 triage.
- `triage-agent` (uid 1062) runs Claude Code and every tool it calls. Home
  `/var/db/triage-agent`, work dir `work/` (root:triage-agent 0750) with the
  runbook (`files/runbook.md`) as a root-owned `CLAUDE.md`, and the tool
  credentials in `/usr/local/etc/triage-agent` (0440 root:triage-agent, so
  the agent can read but never rewrite them): `kubeconfig` for
  `triage-kubectl`, `talosconfig` and `omni.key` for `triage-talosctl`,
  `graylog.token` for `triage-graylog`. It cannot read triage's files.
- The receiver starts the agent with `doas -n -u triage-agent
  /usr/local/lib/triage/libexec/triage-agent-run`, the only rule in
  `/usr/local/etc/doas.conf`. The launcher reads the OAuth token from
  stdin (never argv, never a file this uid can read, independent of what
  environment doas passes), sets a fixed environment, cds to the work dir
  and runs claude under `timeout(1)` (15s under the receiver's timeout,
  since the receiver's uid cannot signal the agent's).

`/usr/local/etc/rc.d/triage` runs `python3 -P -m triage.server --config
/usr/local/etc/triage/config.json` as `triage` under a `daemon(8)`
supervisor (restart on exit, stdout to `/var/log/triage/receiver.log`)
with `env -i`, so only PATH, HOME, LANG, SHELL=/bin/bash and PYTHONPATH
reach it, and never `ANTHROPIC_API_KEY`. Port 8090. Run logs go to
`/var/log/triage/YYYY-MM-DD/<id>.json`, pruned after 30 days by
`/etc/cron.d/triage`; newsyslog rotates the receiver log.

A state or log write error never costs a triage: it is logged, the dedupe
and cap counters carry on in memory, and `/health` is 503 ("state not
writable") until a save works again. The report is posted before the run
log is written.

## What is posted to Discord

Every field of the report, every alert title and every notice goes through
`masking.mask()`, then `redact()`, then has `://` broken to `[:]//`, and only
then is cut to length. Mentions and embeds are off, so a post can't make a
client fetch or follow a link. Cause, next step and confidence come before
the summary and the evidence, so a long report loses the evidence first. At
most 5 titles are listed, with "+N more". HTTP 429 or 5xx from Discord gets
one retry.

## Wrapper output

All five wrappers mask their output whole, before any cut (`masking.py`).
A line over 8192 characters is replaced by a marker. `triage-promql` prints
one series per line (a longer range series in parts), so that rule can't
hide the other results. `triage-kubectl` and `triage-talosctl` share
`triage/toolrun.py` (limits, decoding, log tails) over `boundedrun.py`.

## triage-kubectl

Verbs `get`, `describe`, `logs`, `top`, `events` only. Allowed flags:
`-n/--namespace`, `-A`, `-l/--selector`, `--field-selector`,
`-o wide|json|yaml|name`, `--tail`, `--since`, `--previous`,
`-c/--container`, `--sort-by`, in separate, `=` or joined form; values are
always re-emitted joined (`--flag=value`) so one can't become a flag.
Everything else is refused (files, templates, `-s/--server`, credentials,
contexts, impersonation, `--raw`, `--cache-dir`), as is the Secrets
resource in any case or form. It runs `/usr/local/bin/kubectl` with
`--kubeconfig=` the fixed agent kubeconfig and a clean environment
(`KUBERC=off`, since the agent could write a kuberc in its home).

The wrapper adds `--server` itself: the first of 192.168.7.202, .203 and
.204 that accepts a TCP connection on 6443 within 2s. TLS stays verified
against the cluster CA; each control plane's certificate names its own
address. Limits: 20s per request, 45s in all, 2 MiB of output. `logs` on one
pod keeps the last `--tail` lines (1 to 5000, default 500) and fetches 100
more, like `triage-talosctl`; with `-l` kubectl's own tail applies per pod,
so the first lines of a later pod's log are not checked for a value whose
key was cut off.

## triage-talosctl

Verbs `services [NAME]` (state only), `dmesg`, `logs SERVICE`, `get TYPE
[ID]`, `version`, `memory`, `processes`, `mounts`; `-n` must name one of
192.168.7.201-205. Flags: `--tail 1..500` (`dmesg`, `logs`; default 200),
`-o table|yaml|json` (`get`), `--sort rss|cpu` (`processes`). Resource types
are allowlisted by exact name, so machine config, the kernel command line and
every secrets type are out. No follow or watch, no file reads, no output
paths, no endpoint, context or credential flags. It runs `talosctl` with the
fixed talosconfig and a clean environment, and passes the Omni key in
`SIDERO_SERVICE_ACCOUNT_KEY`. `triage/boundedrun.py` bounds it: 45s, 2 MiB of
output, own process group, killed on any limit or signal. An outer
`timeout -k 2 47` ends talosctl if the wrapper itself is SIGKILLed.

Output is masked whole, before any cut (`triage/masking.py`; the node's dmesg
holds the SideroLink join token). A line over 8192 characters is hidden
whole, which keeps the masking fast. `logs` fetches 100 extra lines so a value's
key is seen before the tail cut, and a first line that could be the rest of a
value is dropped. `processes` prints numbers and the executable's basename,
never arguments (Kubernetes expands secrets into them), and fails on an
unknown table format. `links` is table only (WireGuard keys).

The key is an Omni service account `triage-agent` with the **Reader** role,
which is the second layer: Omni refuses file reads, sensitive resources and
every admin call (PermissionDenied).

## triage-graylog

Searches the Graylog sync API as the user `triage-agent` (role Reader, view
granted on the Default Stream only). Every stream but `Suricata (huginn)`
and the event streams also writes there. The API token (`triage-jail`)
expires 2027-11-25 (see Renewals).

## Malformed Gatus bodies

Gatus pastes error text into the JSON body escaping only `"`. If that breaks
the JSON, `/gatus` (after the token and size checks) still takes the endpoint
and status from the start of the body and drops the rest. `/alertmanager`
must be valid JSON.

## Jail egress (pf)

The jail runs its own pf (VNET), which needs `/dev/pf`: the jail uses
devfs ruleset 14 (`bastille_net`, from roles/bastille with
`bastille_devfs_net_ruleset`; the role asserts the host defines it and
re-rules the running jail). `/etc/pf.conf` blocks and logs everything
(pflog0) except:
- in: tcp 8090 from the LAN and the cluster nodes;
- out, DNS (to the jail's resolv.conf servers): root, triage, triage-agent;
- out, triage-agent: the Anthropic API ranges on 443 (from
  https://platform.claude.com/docs/en/api/ip-addresses), the k8s API
  192.168.7.202-204:6443, Prometheus 192.168.7.207:443, the watcher
  192.168.1.60:8080, Graylog 192.168.1.151:9000, Omni `triage_omni_ip`:443
  (by IP: if the SaaS address moves, Talos lookups fail closed);
- out, triage: tcp 443 anywhere (the Discord webhook);
- out, root: tcp 80/443 (pkg).
Watch drops with `jexec triage tcpdump -n -e -ttt -i pflog0`.

**Securelevel (R15, 2026-11-23).** pf refuses to load rules (EPERM) at
securelevel >= 2, so the jail starts at `securelevel = 1` (jail.conf) and
its own rc raises it to 2 (`kern_securelevel_enable`/`kern_securelevel`
in the jail rc.conf). `rc.d/securelevel` REQUIREs pf, so the rules load
first. At level 2, loading new rules and `pfctl -d` are refused, but
**`pfctl -F rules` still succeeds**, so jail root (not triage or
triage-agent: `/dev/pf` is root 0600) can drop the allowlist. A pf.conf
or securelevel change makes the role restart the jail, the only way to
reload. The "Start pf" task still fails closed if pf is not enabled.

## Run

    ansible-playbook -i inventory/inventory.yml playbooks/triage.yml \
      -e ansible_host=192.168.1.99 -e @"$VARS" --check --diff
    ansible-playbook -i inventory/inventory.yml playbooks/triage.yml \
      -e ansible_host=192.168.1.99 -e @"$VARS"
    shred -u "$VARS"

`$VARS` is a 0600 JSON file written from OpenBao by pipes (never echo
values): `vault_triage_claude_token` (`cluster/triage-claude`
`CLAUDE_CODE_OAUTH_TOKEN`), `vault_triage_push_token` and
`vault_triage_discord_webhook` (`cluster/monitoring-secrets`
`triagePushToken`, `discordWebhookUrl`), `vault_triage_kube_token`,
`vault_triage_kube_ca`, `vault_triage_omni_key` and
`vault_triage_graylog_token` (`cluster/triage-agent` `KUBE_TOKEN`,
`KUBE_CA`, `OMNI_SA_KEY`, `GRAYLOG_TOKEN`),
and `triage_token_created`: the date part of `updated_time` from `vault kv
metadata get secret/cluster/triage-claude` (its current version's date; see
Renewals). The role asserts they are all set.

Connect by IP to avoid the `*.starnix.net` cert-first ssh wildcard tripping
the Wazuh sshd ban. A dry run against a host without the jail stops right
after the (skipped) `bastille create`.

## Operations

Each run leaves two records. `/var/log/triage/YYYY-MM-DD/<id>.json` (pruned
after 30 days) holds the triggers, the prompt, the result and, in `raw`,
`session_id` and `permission_denials`. It does **not** list the tool calls
that were allowed: for those read the transcript, as jail root,
`/var/db/triage-agent/.claude/projects/-var-db-triage-agent-work/<session_id>.jsonl`.
Count only its `assistant` and `user` entries; `api-request-blob` entries
repeat the same calls. The transcripts belong to triage-agent and can hold
anything a tool printed. `/etc/cron.d/triage` deletes them after 30 days.
Claude Code has the same default (`cleanupPeriodDays`) and its sweep runs
here (`.claude/.last-cleanup`), but only when a triage runs, and it can skip
itself, so the cron job does not rely on it.

- **State**: `/var/db/triage/state/cap.json` (runs used this UTC day, and
  whether the cap note was sent: one note per day) and `dedupe.json` (keys
  triaged in the last 6 hours). Don't edit them to free up budget; raise
  `triage_daily_cap` and rerun the role.
- **Restart**: `jexec triage service triage restart`. Alerts still waiting
  in the 2-minute window or the queue are lost; first check that
  `jexec triage pgrep -u triage-agent` prints nothing.
- **Health**: `jexec triage fetch -qo - http://127.0.0.1:8090/health`. 503
  names the problem: token file missing or empty, claude not executable,
  state not writable, or the token within 30 days of expiry. The watcher
  alerts on it. A deploy tolerates only the last one.
- **Test alert**: post an Alertmanager v4 body to `/alertmanager` from inside
  the jail as `triage`, letting the process that sends it read `push_token`
  from `config.json`. Use a made-up `groupKey`, since a real one would
  suppress the real alert for 6 hours, and say it is synthetic. Never send a
  test `KubeNodeNotReady` or `KubeNodeUnreachable` through Alertmanager: it
  also pages PagerDuty.
- **Omni by IP**: pf lets triage-agent reach Omni at `triage_omni_ip` only.
  If every `triage-talosctl` call fails, `drill` the host in
  `triage_omni_endpoint`, compare it with the pin, update `triage_omni_ip`
  and rerun the role (it restarts the jail).

## Renewals

| Credential | OpenBao `secret/cluster/` | Expires |
|---|---|---|
| Claude OAuth token | `triage-claude` `CLAUDE_CODE_OAUTH_TOKEN` | 365 days after issue |
| Omni (Talos) Reader key | `triage-agent` `OMNI_SA_KEY` | 2027-11-25 |
| Graylog API token | `triage-agent` `GRAYLOG_TOKEN` | 2027-11-25 |

Only the Claude token is watched by `/health`. When the Omni key or the
Graylog token lapses, triage still runs but loses those lookups, so renew
them by hand before the date.

**Claude token** (`/health` turns 503 at 30 days left). Run
`claude setup-token`, then store it (`put` is safe here: the path has this
one key) and rerun the role:

    read -rs T && vault kv put secret/cluster/triage-claude \
      CLAUDE_CODE_OAUTH_TOKEN="$T"; unset T

`triage_token_created` must be the date of the **current** version: use
`updated_time`. The metadata's own `created_time` stays at version 1's date
across a `put` (checked 2026-11-25), so using it would leave `/health` at 503
after a renewal.

**Omni key**: make a new key for the Reader service account `triage-agent`.
**Graylog token**: make a new one for the Graylog user `triage-agent`.
Store either with `vault kv patch secret/cluster/triage-agent` (never `put`,
which would drop the other keys) and rerun the role.

## Upgrading a pin

Bump the version and the sha256 together in `defaults/main.yml`
(`triage_claude_*`, `triage_talosctl_*`). The talosctl sum comes from the
release's `sha256sum.txt`; Claude Code's from
the downloaded binary (also bump `triage_claude_size`). The old Claude
binary stays beside the new one until removed by hand.
