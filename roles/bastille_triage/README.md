# bastille_triage

Read-only AI alert-triage jail: a Bastille VNET jail `triage` on beastyboi
(192.168.1.61) running the **Linux** Claude Code build under linuxulator.
Alertmanager and the watcher's Gatus will post alerts to a small receiver in
here, which runs `claude -p` with read-only credentials and posts the
diagnosis to Discord. Kept apart from the `watcher` jail so a wedged or
compromised triage jail can't take the dead man's switch with it. Design:
`docs/superpowers/specs/2026-11-23-triage-agent-design.md`.

## What the role installs

| What | Where (jail path) | Source |
|---|---|---|
| Linux userland | `/compat/linux` | `linux_base-rl9` pkg |
| kubectl (FreeBSD) | `/usr/local/bin/kubectl` | `kubectl` pkg |
| Python | `/usr/local/bin/python3.11`, link `python3` | `python311` pkg |
| Claude Code (Linux) | `/usr/local/lib/triage/claude/claude-<ver>`, link `claude` | pinned, sha256 |
| talosctl (FreeBSD) | `/usr/local/bin/talosctl` | pinned, release sha256 |
| omnictl (Linux) | `/usr/local/lib/triage/linux/omnictl` | pinned, release sha256 |
| receiver package | `/usr/local/lib/triage/triage/` | `files/triage` |
| query wrappers | `/usr/local/lib/triage/bin/triage-*` | `files/bin` |

The wrappers import the package from their grandparent directory, so
`lib/triage/{triage,bin}` must stay siblings: put `lib/triage/bin` on PATH
rather than symlinking the wrappers elsewhere.

User `triage` (uid/gid 1061, home `/var/db/triage`, nologin) owns only
`/var/db/triage/{work,state,.claude}` (0700) and `/var/log/triage` (0750).
`/usr/local/etc/triage` is `0750 root:triage`. Everything else is root's.

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

Known host issue (not fixed here): beastyboi's `[bastille_bpf=13]` only
does `include $devfsrules_jail`, and with `vfs.devfs.rule_depth=1` the
nested includes inside ruleset 4 are skipped, so every Bastille jail's
`/dev` shows all host devices. Jail root can read raw disks; the `triage`
user can't (they are `root:operator 0640`).

## The shell Claude Code spawns

Claude Code is a Linux process, so its Bash tool starts a Linux bash
(`/bin/bash` resolves to `/compat/linux/usr/bin/bash`, 5.1). From there
FreeBSD binaries exec natively (the kernel picks the ABI per binary) and any
path missing under `/compat/linux` falls back to the jail's own. With

    PATH=/usr/local/lib/triage/bin:/usr/local/bin:/usr/bin:/bin

the allowed tools resolve to `/usr/local/bin/kubectl`,
`/usr/local/bin/talosctl` and `/usr/local/lib/triage/bin/triage-{promql,gatus,graylog}`.
omnictl is not on PATH; call it by full path. The user's login shell is
nologin, so whoever starts Claude Code must set `SHELL=/bin/bash`.

## Claude Code managed settings

Claude Code's lockdown lives in its **managed settings** file, which
outranks user and project settings and can't be overridden by them. The
Linux build reads `/etc/claude-code/managed-settings.json`; under
linuxulator that resolves to the jail path
`/compat/linux/etc/claude-code/managed-settings.json` (root:wheel 0644,
dir 0755). It is root-owned on purpose: `triage` owns `~/.claude`, so
anything there could be rewritten by the agent itself.

The file is rendered from `triage_claude_managed_settings`: `env` (no
auto-update or `claude update`, no telemetry, error reports or
nonessential traffic) and the `permissions` (allow/deny lists,
`defaultMode: dontAsk`, bypass mode disabled). With
`allowManagedPermissionRulesOnly` and `allowManagedHooksOnly`, rules or
hooks in the agent's `~/.claude/settings.json` are ignored, and the role
keeps that file absent. Anything not allowed is denied without a prompt.
The only Bash allows are the wrappers (`triage-kubectl`, `triage-promql`,
`triage-gatus`, `triage-graylog`); raw `kubectl`/`talosctl` are denied.
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
  runbook (`files/runbook.md`) as a root-owned `CLAUDE.md`, and
  `/usr/local/etc/triage-agent/kubeconfig` (0400 triage-agent), used only by
  `triage-kubectl`. It cannot read triage's files.
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

## triage-kubectl

Verbs `get`, `describe`, `logs`, `top`, `events` only. Allowed flags:
`-n/--namespace`, `-A`, `-l/--selector`, `--field-selector`,
`-o wide|json|yaml|name`, `--tail`, `--since`, `--previous`,
`-c/--container`, `--sort-by`, in separate, `=` or joined form; values are
always re-emitted joined (`--flag=value`) so one can't become a flag.
Everything else is refused (files, templates, `-s/--server`, credentials,
contexts, impersonation, `--raw`, `--cache-dir`), as is the Secrets
resource in any case or form. It runs `/usr/local/bin/kubectl` with
`--kubeconfig=` the fixed agent kubeconfig and a clean environment.

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
  192.168.1.60:8080, Graylog 192.168.1.151:9000;
- out, triage: tcp 443 anywhere (the Discord webhook);
- out, root: tcp 80/443 (pkg).
Watch drops with `jexec triage tcpdump -n -e -ttt -i pflog0`.

**Open (2026-11-23):** pf refuses `DIOCADDRULE`/`DIOCSTART` with EPERM at
the jail's `securelevel = 2` (the Nebula jails run pf at securelevel 1).
Until that is decided, the "Start pf" task fails, which stops the play
before the receiver is started: no egress allowlist, no receiver.

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
`triagePushToken`, `discordWebhookUrl`), `vault_triage_kube_token` and
`vault_triage_kube_ca` (`cluster/triage-agent` `KUBE_TOKEN`, `KUBE_CA`),
and `triage_token_created`: the date part of `vault kv metadata get
secret/cluster/triage-claude` `created_time`. The role asserts they are
all set.

Connect by IP to avoid the `*.starnix.net` cert-first ssh wildcard tripping
the Wazuh sshd ban. A dry run against a host without the jail stops right
after the (skipped) `bastille create`.

## Upgrading a pin

Bump the version and the sha256 together in `defaults/main.yml`
(`triage_claude_*`, `triage_talosctl_*`, `triage_omnictl_*`). talosctl and
omnictl sums come from each release's `sha256sum.txt`; Claude Code's from
the downloaded binary (also bump `triage_claude_size`). The old Claude
binary stays beside the new one until removed by hand.
