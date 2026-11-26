"""Masks secrets in text. Prefers hiding too much."""
import re

MASK = "[redacted]"
# Longer lines are hidden whole: the rules cost up to a line squared, and
# cutting a line could part a value from its key.
MAX_LINE = 8192
TOO_LONG = f"[line over {MAX_LINE} characters not shown]"

_CONTROL = re.compile(r"\x1b\[[0-9;?]*[ -/]*[@-~]|[\x00-\x08\x0b-\x1f\x7f]")
_QUOTE = r"[\"'\\]{0,16}"
_PSK = r"(?:psk|pre[ _-]?shared[ _-]?key)"
_KEY = (r"(?:tokens?|secrets?|passwords?|passwd|passphrase|pwd|[_-]pass"
        r"|requirepass|masterauth|credentials?|" + _PSK +
        r"s?|(?:api|access|private|secret|encryption|signing|master|auth"
        r"|session|client|shared|jwt|ssh|tls|[_-])[_-]?keys?)[_-]?\d*")
_ASSIGN = r"[ \t]*(?:[=:]>|:=|[=:]+|%(?:25)*3[ad])[ \t]*"
_FLAG = r"(?<![\w-])-{1,2}[\w.-]{0,40}?" + _KEY
# A quoted string (any escaping depth), an array, an object, a JSON
# literal, or a run without spaces.
_VALUE = (
    r"(?:(?P<q>\\{0,8}[\"'])(?:(?!(?<!\\)(?P=q))(?:\\.|[^\n]))*(?:(?P=q)|$)"
    r"|\[(?:\"(?:\\.|[^\"\\\n])*\"|[^\]\n\"])*(?:\]|[^\n]*)"
    r"|\{[^\n]*\}|\{[^\n]*"
    r"|(?:null|true|false|-?\d+(?:\.\d+)?)(?=[,}\]])"
    r"|(?:(?:basic|bearer|digest|token)[ \t]+)?"
    r"(?:\\.|\"(?![,}\]:\s]|$)|[^\s&\"\\])+)")
_FLAGS = re.IGNORECASE | re.MULTILINE

_PEM_MARK = re.compile(r"-----(BEGIN|END) [^\n-]{1,64}-----")
_B64 = ("ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz"
        "0123456789+/=")
_BODY = re.compile(r"[A-Za-z0-9+/]{1,76}={0,2}\Z")
_PEM_HEADER = re.compile(r"[A-Za-z-]{1,40}: |\Z")
_BLOB = re.compile(r"(?<![\w+/=-])[A-Za-z0-9+/]{40,}={0,2}(?![\w+/-])")
_BLOB_LINE = re.compile(r"^[ \t]*[A-Za-z0-9+/_-]{40,}={0,2}[ \t]*$",
                        re.MULTILINE)
# Secrets known by their own shape. Each rule here and in _RULES keeps
# group 1 and masks the rest of the match.
_TOKEN_RULES = [re.compile(text, _FLAGS) for text in (
    r"()https?:(?:\\?/){2}(?:[\w-]{1,63}\.){0,3}(?:discord(?:app)?\.com"
    r"\\?/api(?:\\?/v\d{1,3})?\\?/webhooks"
    r"|hooks\.slack\.com\\?/(?:services|workflows|triggers))\\?/"
    r"[^\s\"'<>]+",
    r"(\bbearer[ \t]+)(?!token\b)[\w.~+/=-]+",
    r"(\bbasic[ \t]+)(?=[\w+/-]*(?-i:[0-9A-Z=]))[\w+/=-]{8,}",
    r"(\b)eyJ[\w-]{6,}(?:\.[\w-]*)*",
    r"(\b)(?:gh[pousr]_\w{20,}|github_pat_\w+|sk-ant-[\w-]+"
    r"|hvs\.[\w-]+|xox[a-z]-[\w-]+|AKIA[0-9A-Z]{16})",
)]
_RULES = [re.compile(text, _FLAGS) for text in (
    r"((?:authorization|cookie)" + _QUOTE + _ASSIGN + r")"
    r"(?=[\"'\\\[{])" + _VALUE,
    r"((?:authorization|cookie)" + _QUOTE + _ASSIGN + r")[^\n\"']+",
    r"((?P<o>\\{0,8}[\"'])-{0,2}[\w.-]{0,64}?" + _KEY + r"(?:=|:[ \t]))"
    r"(?:(?!(?P=o))[^\n])+",
    r"(^[ \t-]{0,64}[\"']?[\w.-]{0,64}?" + _KEY + r"[\"']?[ \t]*:[ \t]+)"
    r"(?![\"'\\\[{])[^\n]+",
    r"(" + _KEY + _QUOTE + r"(?:" + _ASSIGN + r"|[ \t,]*" + _QUOTE +
    r"value" + _QUOTE + _ASSIGN + r"|(?<=[\"'\\])[ \t]*,[ \t]*"
    r"(?=[\"'\\\[])))" + _VALUE,
    r"(" + _FLAG + r"[ \t]+)" + _VALUE,
    r"(\b(?:requirepass|masterauth|" + _PSK + r")[ \t]+)" + _VALUE,
    r"(://)[^\s\"'<>/:@]{0,256}:[^\s\"'<>]{0,256}@",
    r"(://)[^\s\"'<>/@]{1,256}@",
)]
# A key at the end of a line: its value is on the following lines.
_DANGLING = re.compile(
    r"(?:" + _KEY + _QUOTE + r"[ \t]*(?:[=:]>?|%(?:25)*3[ad])"
    r"[ \t\"'\\\[{(|>+-]*"
    r"|" + _FLAG + r"[\"']?,?[ \t\\]*"
    r"|name" + _QUOTE + r"[ \t]*[=:][ \t]*" + _QUOTE +
    r"[\w.-]{0,64}?" + _KEY + r"[\"']?,?[ \t]*)$", re.IGNORECASE)
_HEAD = re.compile(r"(?:\w+:\s+\w+: \[[^\]\n]{1,40}\]: ?)?([ \t]*)")


def split_head(line: str) -> tuple[int, int]:
    """Returns (start of the text, its indent) after any dmesg header."""
    found = _HEAD.match(line)
    return found.end(), len(found.group(1))


def _mask_following(lines: list[str]) -> list[str]:
    """Hides overlong lines and masks the values under a dangling key."""
    out: list[str] = []
    depth = None  # indent of the key whose value is being masked
    first = False  # its first value line is still to come
    for line in lines:
        start, indent = split_head(line[:MAX_LINE])
        if depth is not None and line.strip():
            if first or indent > depth:
                first = False
                out.append(line[:start] + MASK)
                continue
            depth = None
        # The end of a hidden line can hold the key of the next one.
        end = line if len(line) <= MAX_LINE else line[-512:]
        if depth is None and _DANGLING.search(end.rstrip()):
            depth, first = indent, True
        out.append(line if len(line) <= MAX_LINE else TOO_LONG)
    return out


def mask_tokens(text: str) -> str:
    """Replaces secrets that are known by their own shape.

    Args:
        text: Text of any kind.

    Returns:
        The text with tokens and webhook URLs replaced.
    """
    for rule in _TOKEN_RULES:
        text = rule.sub(lambda found: found.group(1) + MASK, text)
    return text


def _mask_pem_inline(line: str, marks: list[re.Match]) -> str:
    """Masks key bodies that share a line with PEM markers."""
    out = []
    pos, kind = 0, ""
    for mark in [*marks, None]:
        gap = line[pos:mark.start() if mark else len(line)]
        if mark and mark.group(1) == "END":
            kept = "" if kind == "BEGIN" else gap.rstrip(_B64 + "\\")
            gap = kept + MASK if len(kept) < len(gap) else gap
        elif kind == "BEGIN":
            rest = gap.lstrip(_B64 + "\\")
            gap = MASK + rest if len(rest) < len(gap) else gap
        out.append(gap)
        if mark:
            out.append(mark.group(0))
            pos, kind = mark.end(), mark.group(1)
    return "".join(out)


def _is_blob(text: str) -> bool:
    """Returns True if text mixes cases and digits like base64."""
    return (any(c.isupper() for c in text) and any(c.islower() for c in text)
            and any(c.isdigit() for c in text))


def _mask_blob(found: re.Match) -> str:
    """Masks a long run only if it looks like base64."""
    return MASK if _is_blob(found.group(0)) else found.group(0)


def _words_before(line: str, mark: re.Match) -> int:
    """Counts the words before a marker, ignoring an opening quote."""
    return len(line[:mark.start()].rstrip("\"'").split())


def _mask_pem(lines: list[str]) -> list[str]:
    """Masks PEM bodies, whole or cut at either end.

    Full-length body lines are masked wherever they are. A short body line
    is masked only next to one or to a marker, behind the same number of
    words, so log lines near a lone marker stay.
    """
    out = list(lines)
    marks = [list(_PEM_MARK.finditer(line)) for line in lines]
    words = None  # leading words of a line that may precede a short one
    for num, line in enumerate(lines):
        if marks[num]:
            out[num] = _BLOB.sub(_mask_blob,
                                 _mask_pem_inline(line, marks[num]))
            words = None
            if marks[num][-1].group(1) == "BEGIN":
                words = _words_before(line, marks[num][-1])
            continue
        full = _BLOB.sub(_mask_blob, line)
        tokens = line.split()
        if full != line:
            out[num], words = full, len(tokens) - 1
            continue
        counts = {words}
        if num + 1 < len(lines) and marks[num + 1] and (
                marks[num + 1][0].group(1) == "END"):
            counts.add(_words_before(lines[num + 1], marks[num + 1][0]))
        if tokens and len(tokens) - 1 in counts:
            last = tokens[-1].strip("\"',\\")
            if _BODY.match(last):
                head, _, tail = line.rpartition(last)
                out[num] = head + MASK + tail
        if not (words is not None and _PEM_HEADER.match(line)):
            words = None
    return out


def mask(text: str) -> str:
    """Replaces secrets in text.

    Run it on complete text, before any truncation or line filter.

    Args:
        text: Text of any kind.

    Returns:
        The text with secret values replaced, control codes dropped and
        overlong lines hidden.
    """
    text = _CONTROL.sub("", text)
    text = "\n".join(_mask_pem(_mask_following(text.split("\n"))))
    for rule in _RULES:
        text = rule.sub(lambda found: found.group(1) + MASK, text)
    return _BLOB_LINE.sub(MASK, mask_tokens(text))


def drop_partial(text: str) -> str:
    """Drops a line that was cut short, or marks it if overlong.

    Args:
        text: Text cut at any character.

    Returns:
        The text's whole lines.
    """
    head, sep, last = text.rpartition("\n")
    return head + sep + (TOO_LONG + "\n" if len(last) > MAX_LINE else "")


def drop_orphans(text: str) -> str:
    """Drops first lines that could be a value whose key was cut off.

    Args:
        text: Unmasked log text whose start may be missing.

    Returns:
        The text from the first line that stands on its own.
    """
    lines = text.splitlines(keepends=True)
    while lines:
        start, indent = split_head(lines[0])
        body = lines[0][start:].strip()
        if len(lines[0]) > MAX_LINE or (
                not indent and (" " in body or body.startswith("{"))):
            break
        lines.pop(0)
    return "".join(lines)
