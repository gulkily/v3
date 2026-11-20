# Find Event support by searching for calendar

Cycle: v3/calendar_flag_search; Mode: one-shot; [Plans index](../README.md)
Debug mode: enabled (project marker)
Effort study: railspike-effort, R1/P2 scope v1; [ledger](../effort_ledger/calendar_flag_search.md)
Extended estimates: enabled; bounded single change, no separate work units

**Cycle estimate: 0.05**

Status: planning; next action is baseline reproduction after the planning commit.

Request: [Feature Flags search for calendar misses Event support](https://zenmemes.com/threads/thread-20261116190930-2eb2509f).
Authorization: the maintainer requested recurring ZenMemes sweeps and PRs for straightforward fixes needing no decisions. Draft PR only; no rename, flag behavior change, deployment, merge, or forum write.
Feedback: searched by report ID and flag terms; no exact item found. PF-20261111-F08 is a separate request to consider renaming the label; leave its status and scope untouched.

Reuse: the existing FeatureFlagRegistry description is displayed and included in feature_flags.php's search text; add the missing term there. Use the real-registry fixture in tests/browser/feature_flags_toolbar.mjs; do not add an alias mechanism or edit the search algorithm.
Completion: on Tools → Feature Flags, the calendar query shows Event support and Calendar feed; clearing restores canonical order. Users can clear the search to recover the complete list. Configuration, label, and key are unchanged.
Risk: misleading copy or unrelated flag behavior; describe what Event support actually does and preserve the separate feed flag. Revert this single change if needed.
Concepts touched: existing Feature Flags search used unchanged; no inventory addition.

Verification: reproduce the missing row with the new browser assertion before editing the description. Afterward rerun the browser fixture and local smoke plus feature flag tests; inspect the diff and UI-copy checklist. Full suite and production acceptance are not in scope.

Starting revision: 597f5328ffb939d4d715fc2a8a78c7d49206a847; branch fix/calendar-flag-search.
Planning commit: pending.

## Study history
- Intake, 2026-11-20T19:27:32Z: 0.05 whole-cycle projection, scope v1, R1/P2, after source inspection (no pristine estimate); one exposed rater.

*— Codex · 2026-11-20 UTC*
