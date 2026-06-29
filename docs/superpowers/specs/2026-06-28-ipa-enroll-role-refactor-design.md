# IPA Enrollment Role Refactor -- Design

*Date: 2026-06-28*

## Problem

A FreeIPA server migration (`ipa1`/`192.168.1.24` -> `ipa9`/`192.168.1.26`) forced edits to
four Ansible roles (`freebsd`, `omnios`, `ipa-client`, `arch`) and their templates, because
IPA connection details and the host-enrollment logic are copy-pasted across them. Every
future migration repeats this toil and risks missing a spot -- which is exactly what happened
this time (services broke one by one as each stale reference was hit).

## Goal

Remove the two real sources of duplication so that:

1. An IPA change (server, realm, domain, base DN) is a **single edit**.
2. The host-enrollment flow lives in **one place**.

...without forcing genuinely OS-specific config into a single conditional-heavy role.

## Current state (2026-06-28)

- IPA-touching roles: `freebsd`, `omnios`, `ipa-client` each run the same enrollment flow
  (kinit -> `ipa host-add` -> `ipa-getkeytab` -> copy keytab -> write krb5/sssd/nsswitch/PAM/CA)
  for a different OS. `arch` is a 73-task desktop role where IPA is ~8 config tasks plus
  partial enrollment.
- Duplication: **(a)** connection values -- `ipa_server` (4x `defaults`), realm `STARNIX.NET`,
  domain `starnix.net`, base `dc=starnix,dc=net` (hardcoded in every template); **(b)** the
  localhost enrollment dance (near-identical); **(c)** per-OS templates (same structure,
  OS-specific paths).
- No `group_vars/` exists yet. `roles/ipa` is a misnamed PagerDuty role, so the name
  `ipa-enroll` is free.
- Keytab dest differs: `/etc/krb5.keytab` (freebsd, ipa-client) vs `/etc/krb5/krb5.keytab` (omnios).

## Design

### 1. Single source of IPA truth -- `group_vars/all/ipa.yml` (new)

```yaml
ipa_server:      ipa9.starnix.net
ipa_realm:       STARNIX.NET
ipa_domain:      starnix.net
ipa_basedn:      dc=starnix,dc=net
ipa_keytab_dest: /etc/krb5.keytab
```

- `group_vars/all` outranks role `defaults`, so this becomes the authoritative source.
- Remove the per-role `ipa_server:` defaults (freebsd, arch, omnios, ipa-client) so there is
  only one definition.
- Parameterize hardcoded values in the templates: `STARNIX.NET` -> `{{ ipa_realm }}`,
  `starnix.net` (ipa_domain / default_domain) -> `{{ ipa_domain }}`,
  `dc=starnix,dc=net` -> `{{ ipa_basedn }}`.
- `omnios` overrides the keytab dest in its role vars: `ipa_keytab_dest: /etc/krb5/krb5.keytab`.

### 2. New role -- `roles/ipa-enroll/`

The OS-agnostic enrollment dance, lifted from the OS roles:

- `Get kerberos ticket` -- `kinit admin` (`delegate_to: localhost`, password from `become_pass`).
- `Ensure host is present in IPA` -- `ipa host-add {{ inventory_hostname }}`, idempotent
  (`failed_when` "already exists" not in stderr), `delegate_to: localhost`.
- `Get kerberos keytab` -- `ipa-getkeytab -s {{ ipa_server }} -p host/{{ inventory_hostname }}
  -k /dev/shm/...`, `delegate_to: localhost`.
- `Set keytab perms on localhost` / `Copy keytab to host` (dest `{{ ipa_keytab_dest }}`) /
  `Clean up keytab from localhost`.

Consumed by each OS role with one line at the start of its IPA section:
`- import_role: { name: ipa-enroll }`.

### 3. Retained per-OS (now fed by the shared vars)

Package install (pkg / pacman / IPS), CA into the OS trust store, the
`krb5.conf` / `sssd.conf` / `nsswitch` / PAM templates (OS-specific paths, shared values),
service management, and desktop/WiFi extras. `krb5.conf.j2` and `sssd.conf.j2` stay per-OS
because the include paths, pkinit anchors, and providers genuinely differ.

### 4. arch

Adopt the shared vars immediately (drop its `ipa_server` default, parameterize its templates).
Optionally adopt `ipa-enroll` for its keytab dance in a later pass -- not required by this work.

## Rollout (incremental, low-risk)

- **Phase 1 -- shared vars (no behavior change):** add `group_vars/all/ipa.yml`; parameterize
  templates and remove per-role `ipa_server` defaults. Verify each role with
  `ansible-playbook <playbook> --check --diff` against a canary host -- rendered configs must be
  byte-identical to the pre-change output. Commit.
- **Phase 2 -- extract the role:** create `roles/ipa-enroll`; replace freebsd's inline
  enrollment tasks with `import_role`. Run `playbooks/freebsd.yml` against one host; confirm
  enrollment + login still work. Commit.
- **Phase 3 -- adopt elsewhere:** switch `omnios` and `ipa-client` to `import_role: ipa-enroll`
  (omnios sets its `ipa_keytab_dest` override). Test each. Commit.

## Scope

**In:** `group_vars/all/ipa.yml`; the `ipa-enroll` role; parameterizing freebsd / omnios /
ipa-client (and arch's vars) to the shared values; removing the duplicated defaults and
enrollment tasks.

**Out (non-goals):** collapsing the OS roles into one; sharing the per-OS krb5/sssd templates;
refactoring arch's non-IPA logic; any runtime behavior change (this is a pure refactor).

## Success criteria

- An IPA server / realm / domain change is a single edit in `group_vars/all/ipa.yml`.
- The enrollment dance exists in exactly one place (`roles/ipa-enroll`).
- `--check --diff` shows byte-identical rendered output before/after Phase 1 on every role.
- freebsd / omnios / ipa-client still enroll and authenticate after their respective phase.

## Trade-offs

Three OS roles remain (not one) -- accepted, because the OS differences are real and a single
conditional role would be harder to maintain than the duplication it removes. The win is
eliminating the *value* duplication and the *enrollment-logic* duplication, which is precisely
where the migration toil came from.
