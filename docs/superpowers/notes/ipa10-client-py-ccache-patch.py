#!/usr/local/bin/python3.12
"""Patch ipaclient client.py: obtain host TGT into the schema-RPC ccache.

FreeBSD-port workaround for the enrollment host-TGT step not populating the
default credentials cache before api.finalize() fetches the RPC schema.
"""
import os
import shutil

F = "/usr/local/lib/python3.12/site-packages/ipaclient/install/client.py"

anchor = (
    "        api.finalize()\n"
    "\n"
    "        # Now, let's try to connect to the server's RPC interface"
)
block = (
    "        # FreeBSD port workaround: (re)obtain the host TGT into the\n"
    "        # ccache the schema RPC uses, via the real krb5.conf, and pin\n"
    "        # KRB5CCNAME with an explicit FILE: prefix.\n"
    "        kinit_keytab(host_principal, paths.KRB5_KEYTAB, CCACHE_FILE,\n"
    "                     attempts=options.kinit_attempts)\n"
    "        env['KRB5CCNAME'] = os.environ['KRB5CCNAME'] = 'FILE:' + CCACHE_FILE\n"
    "        api.finalize()\n"
    "\n"
    "        # Now, let's try to connect to the server's RPC interface"
)

src = open(F).read()
if "FreeBSD port workaround: (re)obtain the host TGT" in src:
    print("already patched")
    raise SystemExit(0)

count = src.count(anchor)
if count != 1:
    print("ERROR anchor count = %d, aborting" % count)
    raise SystemExit(1)

shutil.copy(F, F + ".ipabkp")
open(F, "w").write(src.replace(anchor, block, 1))

pdir = os.path.join(os.path.dirname(F), "__pycache__")
if os.path.isdir(pdir):
    for fn in os.listdir(pdir):
        if fn.startswith("client."):
            os.remove(os.path.join(pdir, fn))

print("patched OK; backup at %s.ipabkp" % F)
