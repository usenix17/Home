# IPA Enrollment Role Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make an IPA server/realm/domain change a single edit, and put the host-enrollment dance in one reusable role, without merging the genuinely OS-specific roles.

**Architecture:** Identity values move to `group_vars/all/ipa.yml`; templates reference `{{ ipa_realm }}`/`{{ ipa_domain }}`/`{{ ipa_basedn }}`. The kinit/host-add/getkeytab/copy-keytab dance becomes `roles/ipa-enroll`, imported by the OS roles, which pass per-OS keytab dest/group as import vars. Pure refactor: rendered output must be unchanged.

**Tech Stack:** Ansible (roles, `group_vars`, `import_role`, Jinja2 templates). Repo: `/kronos/IaC`.

---

## Conventions

**Spec:** `docs/superpowers/specs/2026-06-28-ipa-enroll-role-refactor-design.md`.

**Render-check (host-free verification).** Phase 1 is behavior-preserving, so a parameterized template rendered with the real values must equal its pre-change version rendered the same way. Use this helper (undefined non-IPA vars render empty in both versions, so they cancel):

```bash
render() {  # render() <template-file>  — prints the rendered template
  python3 - "$1" <<'PY'
import sys, jinja2
v = dict(ipa_server='ipa9.starnix.net', ipa_realm='STARNIX.NET',
         ipa_domain='starnix.net', ipa_basedn='dc=starnix,dc=net')
src = open(sys.argv[1]).read()
print(jinja2.Environment(undefined=jinja2.Undefined).from_string(src).render(**v))
PY
}
```

**Per-template verify pattern** (used in every Phase 1 template task):
```bash
git -C /kronos/IaC show HEAD:<path> > /tmp/orig.j2     # pre-change version
diff <(render /tmp/orig.j2) <(render <path>) && echo "IDENTICAL"   # MUST be empty + print IDENTICAL
```

All commands run from `/kronos/IaC` unless noted.

---

## Phase 1 -- Single source of IPA values (no behavior change)

### Task 1: Create the shared values file

**Files:**
- Create: `group_vars/all/ipa.yml`

- [ ] **Step 1: Write the file**

```yaml
---
# Single source of FreeIPA identity for every role/playbook.
# A server or realm/domain/base change is a one-line edit here.
ipa_server: ipa9.starnix.net
ipa_realm:  STARNIX.NET
ipa_domain: starnix.net
ipa_basedn: dc=starnix,dc=net
```

- [ ] **Step 2: Verify YAML loads**

Run: `python3 -c "import yaml; print(yaml.safe_load(open('group_vars/all/ipa.yml')))"`
Expected: prints the dict with all four keys.

- [ ] **Step 3: Commit**

```bash
git add group_vars/all/ipa.yml
git commit -m "feat(ipa): add group_vars/all/ipa.yml as single source of IPA identity"
```

---

### Task 2: Parameterize the `freebsd` templates

**Files:**
- Modify: `roles/freebsd/templates/krb5.conf.j2`, `roles/freebsd/templates/sssd.conf.j2`, `roles/freebsd/templates/ldap.conf.j2`

- [ ] **Step 1: Apply the three substitutions to each template**

Order matters only in that base DN is replaced before domain (they do not overlap, but this is safest):

```bash
for f in roles/freebsd/templates/krb5.conf.j2 \
         roles/freebsd/templates/sssd.conf.j2 \
         roles/freebsd/templates/ldap.conf.j2; do
  sed -i \
    -e 's#dc=starnix,dc=net#{{ ipa_basedn }}#g' \
    -e 's#STARNIX\.NET#{{ ipa_realm }}#g' \
    -e 's#starnix\.net#{{ ipa_domain }}#g' \
    "$f"
done
```

- [ ] **Step 2: Verify each render is byte-identical to pre-change**

Paste the `render()` helper from Conventions, then:

```bash
for f in roles/freebsd/templates/krb5.conf.j2 \
         roles/freebsd/templates/sssd.conf.j2 \
         roles/freebsd/templates/ldap.conf.j2; do
  git show HEAD:"$f" > /tmp/orig.j2
  echo "== $f =="; diff <(render /tmp/orig.j2) <(render "$f") && echo IDENTICAL
done
```
Expected: `IDENTICAL` for all three, no diff output.

- [ ] **Step 3: Sanity-check the literals are gone**

Run: `grep -nE "STARNIX\.NET|starnix\.net|dc=starnix" roles/freebsd/templates/*.j2`
Expected: no output (all now `{{ ipa_* }}`).

- [ ] **Step 4: Commit**

```bash
git add roles/freebsd/templates/
git commit -m "refactor(freebsd): template IPA realm/domain/basedn from group_vars"
```

---

### Task 3: Parameterize the `arch` templates

**Files:**
- Modify: `roles/arch/templates/krb5.conf.j2`, `roles/arch/templates/sssd.conf.j2`

- [ ] **Step 1: Apply substitutions**

```bash
for f in roles/arch/templates/krb5.conf.j2 roles/arch/templates/sssd.conf.j2; do
  sed -i \
    -e 's#dc=starnix,dc=net#{{ ipa_basedn }}#g' \
    -e 's#STARNIX\.NET#{{ ipa_realm }}#g' \
    -e 's#starnix\.net#{{ ipa_domain }}#g' \
    "$f"
done
```

- [ ] **Step 2: Verify renders identical** (render() helper loaded)

```bash
for f in roles/arch/templates/krb5.conf.j2 roles/arch/templates/sssd.conf.j2; do
  git show HEAD:"$f" > /tmp/orig.j2
  echo "== $f =="; diff <(render /tmp/orig.j2) <(render "$f") && echo IDENTICAL
done
```
Expected: `IDENTICAL` both, no diff.

- [ ] **Step 3: Confirm literals gone**

Run: `grep -nE "STARNIX\.NET|starnix\.net|dc=starnix" roles/arch/templates/*.j2`
Expected: no output.

- [ ] **Step 4: Commit**

```bash
git add roles/arch/templates/
git commit -m "refactor(arch): template IPA realm/domain/basedn from group_vars"
```

---

### Task 4: Parameterize the `omnios` templates

**Files:**
- Modify: `roles/omnios/templates/krb5.conf.j2`

- [ ] **Step 1: Apply substitutions**

```bash
sed -i \
  -e 's#dc=starnix,dc=net#{{ ipa_basedn }}#g' \
  -e 's#STARNIX\.NET#{{ ipa_realm }}#g' \
  -e 's#starnix\.net#{{ ipa_domain }}#g' \
  roles/omnios/templates/krb5.conf.j2
```

- [ ] **Step 2: Verify render identical** (render() helper loaded)

```bash
f=roles/omnios/templates/krb5.conf.j2
git show HEAD:"$f" > /tmp/orig.j2
diff <(render /tmp/orig.j2) <(render "$f") && echo IDENTICAL
```
Expected: `IDENTICAL`, no diff.

- [ ] **Step 3: Confirm literals gone**

Run: `grep -nE "STARNIX\.NET|starnix\.net|dc=starnix" roles/omnios/templates/*.j2`
Expected: no output.

- [ ] **Step 4: Commit**

```bash
git add roles/omnios/templates/
git commit -m "refactor(omnios): template IPA realm/domain/basedn from group_vars"
```

---

### Task 5: Parameterize the `ipa-client` templates

**Files:**
- Modify: `roles/ipa-client/templates/krb5.conf.j2`, `roles/ipa-client/templates/sssd.conf.j2`, `roles/ipa-client/templates/ldap.conf.j2`

- [ ] **Step 1: Apply substitutions**

```bash
for f in roles/ipa-client/templates/krb5.conf.j2 \
         roles/ipa-client/templates/sssd.conf.j2 \
         roles/ipa-client/templates/ldap.conf.j2; do
  sed -i \
    -e 's#dc=starnix,dc=net#{{ ipa_basedn }}#g' \
    -e 's#STARNIX\.NET#{{ ipa_realm }}#g' \
    -e 's#starnix\.net#{{ ipa_domain }}#g' \
    "$f"
done
```

- [ ] **Step 2: Verify renders identical** (render() helper loaded)

```bash
for f in roles/ipa-client/templates/krb5.conf.j2 \
         roles/ipa-client/templates/sssd.conf.j2 \
         roles/ipa-client/templates/ldap.conf.j2; do
  git show HEAD:"$f" > /tmp/orig.j2
  echo "== $f =="; diff <(render /tmp/orig.j2) <(render "$f") && echo IDENTICAL
done
```
Expected: `IDENTICAL` for all three.

- [ ] **Step 3: Confirm literals gone**

Run: `grep -nE "STARNIX\.NET|starnix\.net|dc=starnix" roles/ipa-client/templates/*.j2`
Expected: no output.

- [ ] **Step 4: Commit**

```bash
git add roles/ipa-client/templates/
git commit -m "refactor(ipa-client): template IPA realm/domain/basedn from group_vars"
```

---

### Task 6: Remove the four per-role `ipa_server` defaults

`group_vars/all/ipa.yml` now provides `ipa_server` (higher precedence than role defaults), so the per-role copies are dead duplication.

**Files:**
- Modify: `roles/freebsd/defaults/main.yml`, `roles/arch/defaults/main.yml`, `roles/omnios/defaults/main.yml`, `roles/ipa-client/defaults/main.yml`

- [ ] **Step 1: Delete the `ipa_server:` line from each defaults file**

```bash
for f in roles/freebsd/defaults/main.yml roles/arch/defaults/main.yml \
         roles/omnios/defaults/main.yml roles/ipa-client/defaults/main.yml; do
  sed -i '/^ipa_server:/d' "$f"
done
```

Then open each file and delete any now-orphaned IPA-server comment lines (e.g. `# FreeIPA master this host enrolls against...`) left dangling above where `ipa_server` was -- cosmetic, but keeps the defaults clean. arch's defaults keeps its other (non-IPA) vars untouched.

- [ ] **Step 2: Confirm no `ipa_server` remains in any role defaults**

Run: `grep -rn "ipa_server:" roles/*/defaults/`
Expected: no output.

- [ ] **Step 3: Confirm group_vars still resolves it (lookup test)**

Run: `grep -n "ipa_server" group_vars/all/ipa.yml`
Expected: `ipa_server: ipa9.starnix.net` (the single remaining definition).

- [ ] **Step 4: Commit**

```bash
git add roles/*/defaults/main.yml
git commit -m "refactor(ipa): drop per-role ipa_server defaults (now in group_vars/all)"
```

---

### Task 7 (Phase 1 gate): Canary check -- no config drift

This proves the refactor changed nothing on a real host. Pick one reachable host per OS family (substitute `<host>` and `<playbook>`).

- [ ] **Step 1: Syntax check all four playbooks**

```bash
for p in playbooks/freebsd.yml playbooks/omnios.yml playbooks/arch.yaml playbooks/ipa.yaml; do
  echo "== $p =="; ansible-playbook "$p" --syntax-check
done
```
Expected: `playbook: <p>` with no errors for each.

- [ ] **Step 2: Check-mode diff against a canary (per OS you can reach)**

```bash
ansible-playbook playbooks/freebsd.yml --limit <freebsd-host> --check --diff --ask-become-pass
```
Expected: the krb5.conf / sssd.conf / ldap.conf template tasks report **ok / no changes** (rendered output matches what is already on the host). If any of those files shows a diff, a substitution was wrong -- fix and re-verify Task 2-5.

> No commit -- this is a read-only gate.

---

## Phase 2 -- Extract `roles/ipa-enroll`, adopt in freebsd

### Task 8: Create the shared `ipa-enroll` role

**Files:**
- Create: `roles/ipa-enroll/defaults/main.yml`
- Create: `roles/ipa-enroll/tasks/main.yml`

- [ ] **Step 1: Write `roles/ipa-enroll/defaults/main.yml`**

```yaml
---
# Keytab placement on the target host. Override per caller:
#   omnios -> ipa_keytab_dest: /etc/krb5/krb5.keytab, ipa_keytab_group: sys
#   freebsd -> ipa_keytab_group: wheel
ipa_keytab_dest: /etc/krb5.keytab
ipa_keytab_group: root
```

- [ ] **Step 2: Write `roles/ipa-enroll/tasks/main.yml`**

```yaml
---
# Shared FreeIPA host enrollment dance. Runs the kinit/host-add/getkeytab on the
# control node, then installs the keytab on the target. OS-agnostic: identity
# comes from group_vars/all/ipa.yml; keytab dest/group are caller-overridable.

- name: "Get kerberos ticket"
  ansible.builtin.expect:
    command: kinit admin
    responses:
      'Password for admin@{{ ipa_realm }}:': "{{ become_pass }}\n"
  delegate_to: localhost

- name: "Ensure host is present in IPA"
  ansible.builtin.shell: "ipa host-add {{ inventory_hostname }}"
  register: ipa_result
  failed_when: ipa_result.rc != 0 and "already exists" not in ipa_result.stderr
  changed_when: ipa_result.rc == 0
  delegate_to: localhost

- name: "Get kerberos keytab"
  ansible.builtin.shell: "ipa-getkeytab -s {{ ipa_server }} -p host/{{ inventory_hostname }} -k /dev/shm/{{ inventory_hostname }}.keytab"
  delegate_to: localhost

- name: "Set keytab permissions on localhost"
  ansible.builtin.file:
    path: "/dev/shm/{{ inventory_hostname }}.keytab"
    mode: "0600"
    owner: root
    group: root
  delegate_to: localhost

- name: "Copy keytab to host"
  ansible.builtin.copy:
    src: "/dev/shm/{{ inventory_hostname }}.keytab"
    dest: "{{ ipa_keytab_dest }}"
    mode: "0600"
    owner: root
    group: "{{ ipa_keytab_group }}"

- name: "Clean up keytab from localhost"
  ansible.builtin.file:
    path: "/dev/shm/{{ inventory_hostname }}.keytab"
    state: absent
  delegate_to: localhost
```

- [ ] **Step 3: Verify it parses verbatim against the freebsd original**

The new tasks must equal freebsd's first six enrollment tasks except `STARNIX.NET`→`{{ ipa_realm }}`, `/etc/krb5.keytab`→`{{ ipa_keytab_dest }}`, `wheel`→`{{ ipa_keytab_group }}`:

```bash
python3 -c "import yaml; yaml.safe_load(open('roles/ipa-enroll/tasks/main.yml')); yaml.safe_load(open('roles/ipa-enroll/defaults/main.yml')); print('YAML OK')"
```
Expected: `YAML OK`.

- [ ] **Step 4: Commit**

```bash
git add roles/ipa-enroll/
git commit -m "feat(ipa-enroll): shared FreeIPA host-enrollment role"
```

---

### Task 9: Adopt `ipa-enroll` in the freebsd role

**Files:**
- Modify: `roles/freebsd/tasks/main.yml` (replace the first six enrollment tasks -- "Get kerberos ticket" through "Clean up keytab from localhost" -- with one `import_role`)

- [ ] **Step 1: Open `roles/freebsd/tasks/main.yml` and delete the six enrollment tasks**

Delete from the `- name: "Get kerberos ticket"` block through the `- name: "Clean up keytab from localhost"` block (the contiguous run shown by `sed -n '1,45p'`). Keep the `# --- IPA enrollment ---` comment if you like.

- [ ] **Step 2: Insert the import in their place** (right after the leading `---`/comment, before "Create pkg repos config directory")

```yaml
- name: "Enroll host in FreeIPA"
  ansible.builtin.import_role:
    name: ipa-enroll
  vars:
    ipa_keytab_group: wheel
```

- [ ] **Step 3: Verify no enrollment tasks remain inline + YAML parses**

```bash
grep -nE "getkeytab|kinit admin|host-add" roles/freebsd/tasks/main.yml   # expect: no output
python3 -c "import yaml; yaml.safe_load(open('roles/freebsd/tasks/main.yml')); print('YAML OK')"
ansible-playbook playbooks/freebsd.yml --syntax-check
```
Expected: no grep output; `YAML OK`; clean syntax-check.

- [ ] **Step 4: Functional test against a freebsd canary**

```bash
ansible-playbook playbooks/freebsd.yml --limit <freebsd-host> --ask-become-pass
```
Expected: the "Enroll host in FreeIPA" import runs the six dance tasks; keytab lands at `/etc/krb5.keytab` (root:wheel 0600); play completes. Then confirm login on that host still works (`ssh <ipa-user>@<host>`).

- [ ] **Step 5: Commit**

```bash
git add roles/freebsd/tasks/main.yml
git commit -m "refactor(freebsd): use shared ipa-enroll role for enrollment"
```

---

## Phase 3 -- Adopt in omnios + ipa-client

### Task 10: Adopt `ipa-enroll` in the omnios role

**Files:**
- Modify: `roles/omnios/tasks/main.yml` (replace lines 14-57, the six enrollment tasks, with one `import_role`)

- [ ] **Step 1: Delete the six enrollment tasks** ("Get kerberos ticket" through "Clean up keytab from localhost", currently starting at line 14, ending before "Configure kerberos" at line 59).

- [ ] **Step 2: Insert the import in their place**

```yaml
- name: "Enroll host in FreeIPA"
  ansible.builtin.import_role:
    name: ipa-enroll
  vars:
    ipa_keytab_dest: /etc/krb5/krb5.keytab
    ipa_keytab_group: sys
```

- [ ] **Step 3: Verify**

```bash
grep -nE "getkeytab|kinit admin|host-add" roles/omnios/tasks/main.yml   # expect: no output
python3 -c "import yaml; yaml.safe_load(open('roles/omnios/tasks/main.yml')); print('YAML OK')"
ansible-playbook playbooks/omnios.yml --syntax-check
```
Expected: no grep output; `YAML OK`; clean syntax-check.

- [ ] **Step 4: Functional test against an omnios canary**

```bash
ansible-playbook playbooks/omnios.yml --limit <omnios-host> --ask-become-pass
```
Expected: keytab lands at `/etc/krb5/krb5.keytab` (root:sys 0600); play completes; login still works.

- [ ] **Step 5: Commit**

```bash
git add roles/omnios/tasks/main.yml
git commit -m "refactor(omnios): use shared ipa-enroll role for enrollment"
```

---

### Task 11: Adopt `ipa-enroll` in the ipa-client role

**Files:**
- Modify: `roles/ipa-client/tasks/main.yaml` (replace lines 3-42, the six enrollment tasks, with one `import_role`)

- [ ] **Step 1: Delete the six enrollment tasks** ("Get kerberos ticket" through "Clean up keytab from localhost", currently lines 3-42, ending before "Create /var/lib/ipa-client/pki/ directory" at line 43).

> Note: ipa-client's dest is `/etc/krb5.keytab` group `root` -- the `ipa-enroll` defaults -- so no override vars are needed.

- [ ] **Step 2: Insert the import in their place**

```yaml
- name: "Enroll host in FreeIPA"
  ansible.builtin.import_role:
    name: ipa-enroll
```

- [ ] **Step 3: Verify**

```bash
grep -nE "getkeytab|kinit admin|host-add" roles/ipa-client/tasks/main.yaml   # expect: no output
python3 -c "import yaml; yaml.safe_load(open('roles/ipa-client/tasks/main.yaml')); print('YAML OK')"
ansible-playbook playbooks/ipa.yaml --syntax-check
```
Expected: no grep output; `YAML OK`; clean syntax-check.

- [ ] **Step 4: Functional test against an ipa-client canary**

```bash
ansible-playbook playbooks/ipa.yaml --limit <linux-host> --ask-become-pass
```
Expected: keytab lands at `/etc/krb5.keytab` (root:root 0600); play completes; login still works.

- [ ] **Step 5: Commit + push**

```bash
git add roles/ipa-client/tasks/main.yaml
git commit -m "refactor(ipa-client): use shared ipa-enroll role for enrollment"
git push
```

---

## Done -- acceptance

- [ ] `group_vars/all/ipa.yml` is the only place `ipa_server`/realm/domain/basedn are defined (`grep -rn "ipa_server:\|STARNIX.NET\|dc=starnix" roles/` shows only `{{ ipa_* }}` references, no literals).
- [ ] The enrollment dance exists only in `roles/ipa-enroll/` (`grep -rn "getkeytab" roles/` -> only `roles/ipa-enroll`).
- [ ] freebsd / omnios / ipa-client each enrolled a canary and login still works.
- [ ] A future migration is one edit to `group_vars/all/ipa.yml`.
