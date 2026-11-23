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

## Run

    ansible-playbook -i inventory/inventory.yml playbooks/triage.yml \
      -e ansible_host=192.168.1.99 --check --diff
    ansible-playbook -i inventory/inventory.yml playbooks/triage.yml \
      -e ansible_host=192.168.1.99

Connect by IP to avoid the `*.starnix.net` cert-first ssh wildcard tripping
the Wazuh sshd ban. A dry run against a host without the jail stops right
after the (skipped) `bastille create`.

## Upgrading a pin

Bump the version and the sha256 together in `defaults/main.yml`
(`triage_claude_*`, `triage_talosctl_*`, `triage_omnictl_*`). talosctl and
omnictl sums come from each release's `sha256sum.txt`; Claude Code's from
the downloaded binary (also bump `triage_claude_size`). The old Claude
binary stays beside the new one until removed by hand.
