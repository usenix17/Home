# Infrastructure as Code

## Introduction

Infrastructure as Code (IaC) or the method by which written logic is used to
control a complete infrastructure. This logic or code can be captured as a
distributed set of files(repo) by tools such as Git. This repo demonstrates
the details and methods needed.

## OS Requirements Linux

* ssh-client
* sshpass - Some modules need this for password based login

## Python Requirements

See the setup file.

## Primary and DR usage depolyment tooling.

### TL;DR;

Always as a user, never as root, we all know better.

```bash
$ mkdir ~/awesomesauce && cd ~/awesomesauce
$ git clone git@lathama.net:lathama/IaC.git
$ cd IaC/Infrastructure
$ echo "yourvaultpassword" > .ansible_vault
$ ./doit
$ ansible-playbook playbook.yml
```

### Step by step:

1. Make a containing directory and move into it.
1. Clone this repo
1. Move into the Infrastructure part of this repo
1. Set the Ansible Vault password
1. Clone a very shallow copy of Anisble at a specific branch
1. Install the libraries into your user path
1. Setup the Ansible testing environment with config test
1. Run the playbook (assuming your vault and roles work)

Enable this for your roles

1. Set your own Ansible Vault up, see below
1. Configure your own inventory
1. Use provided or configure your own roles

### Configuration

Some items to configure for your environment are:

1. Ansible Vault secret aka .ansible_vault file
1. Inventory
1. Users and shared key encryption keys

Note the ansible config is defaulting some settings so the command actually looks like:

``` bash
$ ansible-playbook --vault-id .ansible_vault -e @vault.yml -i inventory.yml playbook.yml
```

This is useful to know when trouble-shooting.


## Bootstrapping Python:

Ansible is written in and relies on Python. You should have a recent version
of Python and the `pip` tool to install packages.

## Vault

In this example we use the Ansible vault which is a portable encryption
mechanisim that allows secrets to be checked in as encrypted text documents.

* https://docs.ansible.com/ansible/latest/user_guide/vault.html

### Create:

Note the --vault-id .ansible_vault is set in ansible.cfg

```bash
$ ansible-vault create inventory/group_vars/all.yml
```

### Edit:

Note the --vault-id .ansible_vault is set in ansible.cfg

```bash
$ ansible-vault edit vault.yml
```

### View

Open with 'more' or 'less' tools. Note the --vault-id .ansible_vault is set in ansible.cfg

```bash
$ ansible-vault view vault.yml
```
# Home
