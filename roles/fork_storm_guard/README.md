# fork_storm_guard

Stop a single hung operation from compounding into a system-wide outage on the
FreeBSD storage host (**mimir**).

## Why this exists

On ~2026-08-14 21:10 a `zfs snapshot` against `Store` wedged in the kernel
(`tq_drain`, D-state, unkillable -- `SIGKILL` had no effect). `sanoid --cron`
runs every minute from `/etc/crontab`; with nothing serialising them, each
invocation blocked forever on its wedged child and the next one stacked beside
it. Over ~73 hours that produced ~4,413 stacked cron forks, ~8,824 perl, ~4,412
unkillable `zfs`, **17,787 processes**, ~45 GB of anonymous memory, an exhausted
2 GB swap, and a pagedaemon scan treadmill that collapsed NFS and MinIO. Netdata's
`apps.plugin` amplified it by walking `kern.proc.all` (O(n) under the allproc
lock) every cycle.

The ZFS taskqueue wedge is a **separate, still-undiagnosed** problem. This role
does **not** fix it. It fixes the thing that turned one hung operation into a
three-day outage with no alarm: nothing bounded the compounding, and nothing
alerted. Three deliberately layered controls:

1. **Part 1 -- lockf wrapper** around `sanoid --cron`: a hung run *blocks* the
   next one instead of stacking (addresses this specific cause).
2. **Part 2 -- rctl(8) process ceilings**: any fork storm, from any source,
   hits a wall long before it exhausts the process table (addresses the class).
3. **Part 3 -- netdata detection**: alarms on D-state count, total processes,
   and swap -- the alarm that was missing.

Controls 1 and 2 are redundant on purpose.

## What takes effect when

| Change | When it takes effect |
| --- | --- |
| Part 1: wrapper script + `/etc/crontab` entry | **Immediately** (next cron minute) |
| Part 3: netdata health alarms | **Immediately** (health reload / SIGUSR2) |
| Part 2: `rctl.conf`, `rc.conf` `rctl_enable` | Written immediately; **rules enforce only once RACCT is active** |
| Part 2: `kern.racct.enable=1` in `/boot/loader.conf` | **Requires a reboot** |

If RACCT is not yet active (`sysctl kern.racct.enable` = 0), the play sets
`loader.conf`, stages everything else, and **surfaces a pending-reboot message
as a task result. It does not reboot and does not fail.** When RACCT is already
active, the rules are also applied live with `rctl -a` so a reboot is not
required to start enforcing.

## ⚠️ SAFETY -- a root `maxproc deny` can lock you out

The `deny` action on `user:root:maxproc` is a hard cap on root's process count.
**Once reached, root cannot `fork(2)`** -- no new SSH session, no `su`, no
`sudo`, and no new shell from an existing session. If you are already logged in,
you cannot spawn a single child process. Recovery requires the console or a
reboot.

Guardrails in this role:

* The play **baselines the live process count** and **refuses (fails with an
  explanation)** to write a `deny` that is less than `fork_storm_guard_baseline_multiple`×
  (default 4×) the baseline, or not `< kern.maxproc`, or not ordered
  `log < devctl < deny`. If the baseline itself is abnormally high, the host
  isn't healthy -- fix that first (do not raise `deny` to get past the gate).
* The default `deny=2000` sits far below `kern.maxproc` (99999 on mimir) and far
  above a healthy steady state, but **above** where the fork storm needed to be
  stopped.

### Console-recovery procedure (root `maxproc` denial)

If root has hit the `maxproc` deny and you are locked out over the network:

1. Get to the **physical/IPMI console** (mimir's BMC). SSH will not work -- the
   login shell cannot fork.
2. At the console `login:` prompt, logging in still needs to fork a shell, so it
   may also be blocked. If so, **reboot** from the console/IPMI:
   * The single-user path: interrupt the loader (`boot -s`) to come up in
     single-user mode, where nothing else is competing for the process table.
3. Once you have a shell, drop or raise the limit live:
   ```sh
   rctl -r user:root:maxproc:deny          # remove the deny entirely
   # or raise it:
   rctl -a user:root:maxproc:deny=8000
   ```
4. To prevent it re-arming on the next boot, edit `/etc/rctl.conf` (or set
   `fork_storm_guard_root_maxproc_deny` higher and re-run the role) before you
   re-enable it. Removing `rctl_rules`/`rctl_enable` from `rc.conf` disables all
   rctl rules at the next boot as a bigger hammer.
5. If even single-user is wedged by the *original* fork storm (not the deny),
   this is the exact scenario the `log`/`devctl` layers and the netdata alarms
   are meant to catch *first* -- but the ultimate backstop is an IPMI power
   cycle.

### loginclass vs user:root -- why user:root

A `loginclass:` subject would be narrower and safer than `user:root`. It is
**not achievable for cron-invoked work here**: cron and its children run as root
under the `default` login class (`/etc/login.conf`); there is no dedicated class
for cron. Scoping just cron would mean either changing root's login class
globally (affecting every root session) or wrapping each cron entry in
`su -l -c <class>` (fragile, and the incident's sanoid runs as root regardless).
So the role uses `user:root` with the layered `log`/`devctl`/`deny` actions, the
safety gate above, and per-jail ceilings. This is stated explicitly rather than
silently defaulted -- see `roles/fork_storm_guard/tasks/rctl.yml`.

## Part 1 details -- the lockf wrapper

`/usr/local/sbin/sanoid-cron-wrapper.sh` (root:wheel 0755):

* `lockf -t 0 -k /var/run/sanoid-cron.lock` -- non-blocking exclusive lock,
  keeping the lock file across runs.
* Lock free → `exec sanoid --cron`, preserving its exit status.
* Lock held → **no sanoid, no stdout/stderr** (cron mails on any output; the
  incident would have sent ~4,300 mails), **exit 0**, and a `logger -t
  sanoid-cron -p daemon.warning` line. After
  `fork_storm_guard_skip_escalation_threshold` (default 15) consecutive skips it
  logs at `daemon.err` instead, tripping the Wazuh agent / syslog alerting path.
  The consecutive count lives in `/var/run/sanoid-cron.skipped` and resets on any
  successful run.
* Best-effort Prometheus Pushgateway metrics on every invocation
  (`sanoid_cron_skipped_consecutive`, `sanoid_cron_last_success_timestamp_seconds`).
  The push is non-fatal: a Pushgateway outage never blocks snapshots or causes a
  non-zero exit, and it is skipped silently if `curl` is absent.

**No stale-lock breaking.** `lockf` uses `flock(2)`, so the lock releases
automatically when the holder dies. A *held* lock is a true signal that a prior
run is still alive -- the hung `zfs` children were unkillable, but their perl
parents held the lock legitimately for as long as they existed. Breaking the
lock by age or PID would re-enable stacking and defeat the entire purpose.

## Requirements / caveats

* **Pushgateway URL**: mimir is **outside** the Omega k8s cluster, so the
  default `prometheus-pushgateway.monitoring.svc.cluster.local:9091` is *not*
  reachable from it. Set `fork_storm_guard_pushgateway_url` to an
  externally-reachable route (ingress/LB) for the metrics to land, or set it to
  `""` to disable the push. The wrapper degrades silently either way.
* **netdata chart IDs**: the Part 3 alarms watch `system.processes` (`blocked`
  dim), `system.active_processes`, and `system.swap`. Verify these on the
  healthy host and override the role vars if this FreeBSD build names them
  differently:
  ```sh
  curl -s localhost:19999/api/v1/charts | tr ',' '\n' | grep -Ei 'process|swap'
  ```
* **PagerDuty routing**: delivery rides netdata's `health_alarm_notify.conf`.
  The `pagerduty` role writes `/etc/netdata` (Linux); on FreeBSD the file lives
  at `/usr/local/etc/netdata/health_alarm_notify.conf`. If `SEND_PD="YES"` is not
  present there, the play warns (does not fail) -- alarms still fire locally and
  via the Wazuh syslog path, but will not page PagerDuty until that file is in
  place.

## Running it

```sh
# dry run (safe; the read-only baseline/detect tasks run under --check too):
ansible-playbook playbooks/fork_storm_guard.yml --check --diff

# apply:
ansible-playbook playbooks/fork_storm_guard.yml

# one part at a time:
ansible-playbook playbooks/fork_storm_guard.yml --tags sanoid_lock
ansible-playbook playbooks/fork_storm_guard.yml --tags rctl
ansible-playbook playbooks/fork_storm_guard.yml --tags detection
```

Running the play twice produces **no changes** on the second run.

Nothing here modifies sanoid's snapshot policy, ZFS properties, swap
configuration, or the `Store` pool.

## Acceptance tests

```sh
# Part 1 -- lock contention is silent and non-fatal:
lockf -k /var/run/sanoid-cron.lock sleep 300 &     # hold the lock
/usr/local/sbin/sanoid-cron-wrapper.sh; echo "exit=$?"   # -> exit=0, no output
logger_check: tail /var/log/messages | grep sanoid-cron  # -> daemon.warning line
# Release the lock, run again -> sanoid runs, /var/run/sanoid-cron.skipped gone.

# Part 2 -- rules present once RACCT is active (after reboot):
sysctl kern.racct.enable        # -> 1
rctl -l user:root               # -> the log/devctl/deny lines

# Part 2 -- jail ceiling holds. TEST IN A THROWAWAY SCRATCH JAIL ONLY, never on
# the host. Create a scratch jail, apply a low ceiling, and fork-bomb INSIDE it:
rctl -a jail:scratch:maxproc:deny=50
jexec scratch sh -c ':(){ :|: & };:'    # denied at 50; host process table intact
rctl -r jail:scratch:maxproc:deny=50    # clean up
```

Do **not** run a fork bomb on the host -- only inside a disposable jail with its
own ceiling.
