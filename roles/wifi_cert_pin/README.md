# wifi_cert_pin

Join Linux / NetworkManager clients to the WPA-Enterprise (802.1X) SSID with the
RADIUS server certificate **pinned**, so the FreeRADIUS server on ipa9
(`192.168.1.26`, cert `CN=radius.starnix.net` from the FreeIPA CA) is the *only*
server a client will hand credentials to.

## What "pinning" means here

Two controls, both set in the NetworkManager keyfile:

1. **`ca-cert`** points at a dedicated copy of the FreeIPA CA (`starnix-ipa-ca.pem`),
   not the system trust store. The client trusts *only* this CA to sign the
   RADIUS server cert.
2. **`domain-suffix-match` / `altsubject-match`** require the server cert name to
   be `radius.starnix.net`.

Without both, a rogue AP presenting any otherwise-valid cert could establish the
PEAP/TTLS tunnel and capture the inner credentials. This is the anti-evil-twin
boundary described in the FreeRADIUS design spec.

## What it does

- Installs the FreeIPA CA to `/etc/NetworkManager/certs/starnix-ipa-ca.pem`.
- Writes a system connection keyfile
  `/etc/NetworkManager/system-connections/<wifi_conn_name>.nmconnection` (0600)
  for the SSID, with `key-mgmt=wpa-eap`, the EAP method, the pin, and the
  per-host identity/password.
- Reloads NetworkManager connections.

## Requirements

- NetworkManager on the target (Arch, EL9, Debian).
- `community.general` (already in `requirements.yml`).

## Variables

See `defaults/main.yml`. The ones you must set per host:

| Variable | Meaning |
|---|---|
| `wifi_ssid` | Your WPA-Enterprise SSID |
| `wifi_identity` | FreeIPA uid of a `wifi`-group member |
| `wifi_password` | that user's password (put in Ansible Vault) |

Defaults worth knowing: `wifi_eap_method` (`peap`/`ttls`), `wifi_phase2_auth`
(`mschapv2`/`pap`), `wifi_radius_server_domain` (`radius.starnix.net`).

## Usage

Per-host identity in `inventory/host_vars/selene.starnix.net.yml`:

```yaml
wifi_ssid: "Starnix-Secure"
wifi_identity: "sasha"
wifi_password: "{{ vault_wifi_password }}"
```

Keep the secret in an Ansible Vault file (the repo's `vault_` convention), e.g.
`vault_wifi_password: <secret>`.

Run with the bundled playbook (defaults to the `desktops` group):

```bash
ansible-playbook playbooks/wifi_cert_pin.yml
# or target another group/host:
ansible-playbook playbooks/wifi_cert_pin.yml -e wifi_target_hosts=selene.starnix.net
```

## Non-NetworkManager clients

macOS/iOS, Windows, and Android aren't NetworkManager hosts. For those, generate
and hand-install a profile that pins the same CA + server name
(`.mobileconfig` for Apple, WLAN XML for Windows). That is a planned extension;
this role covers the Ansible-managed Linux fleet.
