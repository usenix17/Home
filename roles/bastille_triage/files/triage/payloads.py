"""Parse Alertmanager and Gatus webhook payloads into triggers."""
import dataclasses
from typing import Any


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
    labels = firing[0].get("labels") or {}
    common = body.get("commonLabels") or {}
    title = common.get("alertname") or labels.get("alertname") or "unknown"
    alerts = [{"labels": a.get("labels") or {},
               "annotations": a.get("annotations") or {},
               "startsAt": a.get("startsAt", "")} for a in firing]
    key = body.get("groupKey") or f"alertmanager:{title}"
    return [Trigger("alertmanager", key, title,
                    labels.get("severity", ""),
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
    return [Trigger("gatus", f"gatus:{endpoint}", endpoint, "critical",
                    {"description": body.get("description", ""),
                     "errors": body.get("errors", "")})]
