"""Parse Alertmanager and Gatus webhook payloads into triggers."""
import dataclasses
import json
import re
from typing import Any

_UNPARSABLE = "[unparsable, dropped]"
# The fixed start of the watcher's body; error text only comes later.
_GATUS_HEAD = re.compile(
    rb'\s{0,16}\{\s{0,16}"endpoint"\s{0,16}:\s{0,16}'
    rb'"([^"\\\x00-\x1f]{1,200})"\s{0,16},\s{0,16}"status"\s{0,16}:'
    rb'\s{0,16}"(TRIGGERED|RESOLVED)"\s{0,16},')


@dataclasses.dataclass(frozen=True)
class Trigger:
    """One alert group or endpoint failure to triage.

    Attributes:
        source: "alertmanager" or "gatus".
        key: Alertmanager groupKey, or "gatus:<group>/<name>".
        title: Alertname, or "<group>/<name>".
        severity: Severity label; "critical" for gatus.
        detail: Sanitized original fields.
    """
    source: str
    key: str
    title: str
    severity: str
    detail: dict


def _dict(value: Any) -> dict:
    """Returns value if it is a dict, else an empty dict."""
    return value if isinstance(value, dict) else {}


def parse_alertmanager(body: dict[str, Any]) -> list[Trigger]:
    """Parses an Alertmanager v4 webhook body.

    Args:
        body: Decoded webhook JSON.

    Returns:
        One trigger for a firing group, else an empty list.
    """
    if body.get("status") != "firing":
        return []
    firing = [a for a in body.get("alerts") or []
              if isinstance(a, dict) and a.get("status") == "firing"]
    if not firing:
        return []
    labels = _dict(firing[0].get("labels"))
    common = _dict(body.get("commonLabels"))
    title = str(common.get("alertname") or labels.get("alertname")
                or "unknown")
    alerts = [{"labels": _dict(a.get("labels")),
               "annotations": _dict(a.get("annotations")),
               "startsAt": a.get("startsAt", "")} for a in firing]
    key = str(body.get("groupKey") or f"alertmanager:{title}")
    return [Trigger("alertmanager", key, title,
                    str(labels.get("severity", "")),
                    {"alerts": alerts, "commonLabels": common})]


def parse_gatus(body: dict[str, Any]) -> list[Trigger]:
    """Parses a Gatus custom-alert body.

    Args:
        body: Decoded JSON with endpoint, status, description, errors.

    Returns:
        One trigger when status is TRIGGERED, else an empty list.
    """
    endpoint = body.get("endpoint")
    if body.get("status") != "TRIGGERED" or not endpoint:
        return []
    endpoint = str(endpoint)
    return [Trigger("gatus", f"gatus:{endpoint}", endpoint, "critical",
                    {"description": body.get("description", ""),
                     "errors": body.get("errors", "")})]


def load_gatus(raw: bytes) -> dict[str, Any]:
    """Decodes a Gatus body, tolerating error text that breaks its JSON.

    Gatus pastes errors into the body escaping only double quotes, so a
    control character or a backslash there must not lose the alert.

    Args:
        raw: The request body.

    Returns:
        The decoded body, or the endpoint and status with the rest dropped.

    Raises:
        ValueError: If not even those can be recovered.
    """
    try:
        body = json.loads(raw)
    except (ValueError, RecursionError) as err:
        found = _GATUS_HEAD.match(raw)
        if not found:
            raise ValueError("unrecoverable gatus body") from err
        return {"endpoint": found.group(1).decode("utf-8", "replace"),
                "status": found.group(2).decode(),
                "description": _UNPARSABLE, "errors": _UNPARSABLE}
    if not isinstance(body, dict):
        raise ValueError("gatus body is not an object")
    return body
