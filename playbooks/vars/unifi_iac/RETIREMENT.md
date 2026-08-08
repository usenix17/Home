# Retiring the v2 firewall reconciler

The tag-based v2 reconciler (`roles/unifi_firewall`, `playbooks/unifi_firewall.yml`,
and the `unifi-semaphore.yml` GitHub Action) has been removed. The UniFi firewall,
networks, and DHCP are now managed by `playbooks/unifi_iac.yml` via the
[`starnix.unifi`](https://github.com/usenix17/starnix.unifi) collection. The old
code remains in git history if you ever need it.

Why the switch was safe: `unifi_iac.yml` was reverse-engineered from the live
controller and verified as an exact no-op (`changed=0`). The 62 duplicate-named
policies were renamed uniquely (revert map at `~/unifi_policy_rename_revert.json`),
which is why the reconciler had to go -- it enforced the old names by `iac:` tag
and would rename them back.

## Finish the cutover (one-time)

1. **Make the collection installable on the Semaphore runner.** It is currently
   a private repo. Either publish it (make `usenix17/starnix.unifi` public, or
   push to Ansible Galaxy) so `ansible-galaxy install -r requirements.yml` works,
   or vendor it into the runner. `requirements.yml` already lists the git source.

2. **Create a Semaphore template** (project 1) that runs
   `playbooks/unifi_iac.yml`, installs `requirements.yml`, and has `UNIFI_API_KEY`
   in its environment (reuse the reconciler's key). Note its template id.

3. **Wire the GitHub Action:** set the repo variable `UNIFI_IAC_TEMPLATE_ID` to
   that template id. `.github/workflows/unifi-iac.yml` then enforces on every
   change to the playbook or vars.

4. **Delete the old Semaphore template** (the reconciler's template 3) so it can
   no longer be run manually.

Until step 2-3 are done there is no auto-enforcement, which is fine: the firewall
is already in the desired state and does not drift on its own. Run manually with
`UNIFI_API_KEY=... ansible-playbook playbooks/unifi_iac.yml` any time.
