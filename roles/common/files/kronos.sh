# shellcheck shell=sh

# Expand $PATH to include the directory where kronos applications go.
kronos_bin_path="/kronos/usr/bin"
if [ -n "${PATH##*${kronos_bin_path}}" ] && [ -n "${PATH##*${kronos_bin_path}:*}" ]; then
    export PATH="$PATH:${kronos_bin_path}"
fi

