"""Masks secrets in command output. Prefers hiding too much."""
import re

MASK = "[redacted]"

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
    r"|\[(?:\"(?:\\.|[^\"\\\n])*\"|[^\]\n\"])*(?:\]|$)"
    r"|\{[^\n]*\}|\{[^\n]*"
    r"|(?:null|true|false|-?\d+(?:\.\d+)?)(?=[,}\]])"
    r"|(?:(?:basic|bearer|digest|token)[ \t]+)?"
    r"(?:\\.|\"(?![,}\]:\s]|$)|[^\s&\"\\])+)")
_FLAGS = re.IGNORECASE | re.MULTILINE

_PEM = re.compile(r"-----BEGIN[^\n]{0,80}?-----.*?"
                  r"(?:-----END[^\n]{0,80}?-----|\Z)", re.DOTALL)
_PEM_TAIL = re.compile(r"\A.*-----END[^\n]{0,80}-----", re.DOTALL)
_BLOB_LINE = re.compile(r"^[ \t]*[A-Za-z0-9+/_-]{40,}={0,2}[ \t]*$",
                        re.MULTILINE)
# Each keeps group 1 and masks the rest of the match.
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
    r"(\bbearer[ \t]+)(?!token\b)[\w.~+/=-]+",
    r"(\bbasic[ \t]+)(?=[\w+/-]*(?-i:[0-9A-Z=]))[\w+/=-]{8,}",
    r"(\b)eyJ[\w-]{6,}(?:\.[\w-]*)*",
    r"(\b)(?:gh[pousr]_\w{20,}|github_pat_\w+|sk-ant-[\w-]+"
    r"|hvs\.[\w-]+|xox[a-z]-[\w-]+|AKIA[0-9A-Z]{16})",
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
    """Masks the lines holding the value of a key that ends a line."""
    out: list[str] = []
    depth = None  # indent of the key whose value is being masked
    first = False  # its first value line is still to come
    for line in lines:
        start, indent = split_head(line)
        if depth is not None and line.strip():
            if first or indent > depth:
                first = False
                out.append(line[:start] + MASK)
                continue
            depth = None
        if depth is None and _DANGLING.search(line.rstrip()):
            depth, first = indent, True
        out.append(line)
    return out


def mask(text: str) -> str:
    """Replaces secrets in text.

    Run it on complete text, before any truncation or line filter.

    Args:
        text: Text of any kind.

    Returns:
        The text with secret values replaced and control codes dropped.
    """
    text = _CONTROL.sub("", text)
    text = _PEM.sub(MASK, text)
    text = _PEM_TAIL.sub(MASK, text)
    text = "\n".join(_mask_following(text.split("\n")))
    for rule in _RULES:
        text = rule.sub(lambda found: found.group(1) + MASK, text)
    return _BLOB_LINE.sub(MASK, text)
