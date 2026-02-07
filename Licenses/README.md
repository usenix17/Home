# Licenes

Software and service may require a license in the form of a token or file.
These can be captured here. Ansible Vault can be used to encrypt these
licences either plain text or binary.

## Encryption / Decryption

Ansible Vault is easy to use once setup.

### Encryption

```bash
cd ~/awesomesauce/IaC/Infrastructure/
ansible-vault encrypt ../Licenses/example.vendor.product.lic
Encryption successful
```

### Decryption aka view

```bash
cd ~/awesomesauce/IaC/Infrastructure/
ansible-vault view ../Licenses/example.vendor.product.lic
Product: XYZ
Key: TJaMIG0MRQwEgYDVQQKEwtFbnRydXN0Lm5ldDFA

```

### Binary Files

In Ansible roles and playbooks binary files can be encrypted also. This can be
done on the command line interface with the pattern above.

```bash
ansible-vault encrypt ../Licenses/example.vendor.product.lic.tar.gz 
Encryption successful
```

The file can not be viewed, it must instead be decrypted

```bash
ansible-vault decrypt ../Licenses/example.vendor.product.lic.tar.gz 
Decryption successful
```

Be careful not to check in the decrypted version by accident.
