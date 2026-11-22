# Keep Recently released in sync with Feature Flags filters

Cycle: v3/recent_feature_flag_filters; Mode: one-shot; [Plans index](../README.md)
Debug mode: enabled (project marker)
Effort study: railspike-effort R1/P2 scope v1; [ledger](../effort_ledger/recent_feature_flag_filters.md)
Extended estimates: enabled; one bounded change, no separate work units

**Cycle estimate: 0.06**

Status: planning; reproduce after the planning commit.

Request: [Recently released should follow search and filters](https://zenmemes.com/threads/thread-20261122203606-d42b89e8).
Authorization: the maintainer requested recurring ZenMemes sweeps and PRs for straightforward fixes needing no decisions. Draft PR only; no merge, deployment, or forum write.
Feedback: no exact item found by report ID or subject. PF-20261118-F06 covers the original release list, not this follow-up; leave its acceptance alone. No import/triage batch authorized.
Reuse: retain server release order and link anchors from feature_flags.php; mirror the already-computed canonical row visibility in feature_flags.js. Reuse the explicit hidden CSS selector group and existing PHP/Playwright fixture.
Completion: search and status (alone and combined) update the recent items, exhausted recent matches hide the section even if canonical matches exist, clearing restores original order, and visible links still land at their target. No changes to counts, release metadata, status controls, or no-JS rendering.
Risk: duplicated criteria and flex styles overriding hidden; consume existing row.hidden and explicitly hide list items. Revert this isolated patch if needed.
Concepts touched: Feature Flags toolbar, existing behavior link below; no new persistence.
Verification: focused PHP, same baseline and proposed smoke check, actual JS on production-rendered rows, existing Chromium fixture if the sandbox supports it. No full suite or production writes.

Starting revision: a4a4aead62d2a4965f108c9910e26a58204bb7ea; branch fix/recent-feature-flag-filters.
Planning commit: pending.

## Study history
- 2026-11-22 UTC: intake 0.06, scoped after source inspection; pristine intake missing. Execution entry 0.06 reaffirmed before implementation. One exposed rater.

*— Codex · 2026-11-22 UTC*
