#!/bin/sh
# Wazuh Active Response: YARA-scan a FIM-changed file and quarantine on match.
# Triggered by the manager on syscheck (added/modified) events, location=local.
# POSIX sh (runs on FreeBSD agents too -- no bashisms). No timeout, so this is a
# fire-once "add" AR; it never needs the check_keys/continue handshake.
LOG="/var/ossec/logs/active-responses.log"
QUAR="/var/ossec/quarantine"
RULES="/var/ossec/ruleset/yara/rules/wazuh_rules.yar"
YARA="$(command -v yara 2>/dev/null || echo /usr/local/bin/yara)"

read -r INPUT
CMD=$(printf '%s' "$INPUT" | sed -n 's/.*"command":"\([a-z_]*\)".*/\1/p')
[ "$CMD" = "add" ] || exit 0

# The changed file path from the FIM alert (parameters.alert.syscheck.path).
FILE=$(printf '%s' "$INPUT" | sed -n 's/.*"path":"\([^"]*\)".*/\1/p')
[ -n "$FILE" ] && [ -f "$FILE" ] || exit 0
[ -x "$YARA" ] || exit 0

MATCH=$("$YARA" -w -r "$RULES" "$FILE" 2>/dev/null | awk '{print $1}' | tr '\n' ',' | sed 's/,$//')
[ -n "$MATCH" ] || exit 0

TS=$(date '+%a %b %d %H:%M:%S %Z %Y' 2>/dev/null)
mkdir -p "$QUAR" 2>/dev/null
DEST="$QUAR/$(basename "$FILE").$(date +%s 2>/dev/null)"
if mv "$FILE" "$DEST" 2>/dev/null; then
  chmod 000 "$DEST" 2>/dev/null
  echo "$TS wazuh-yara: ALERT - [$MATCH] matched $FILE -> quarantined $DEST" >> "$LOG"
else
  echo "$TS wazuh-yara: ALERT - [$MATCH] matched $FILE (quarantine failed, check perms)" >> "$LOG"
fi
exit 0
