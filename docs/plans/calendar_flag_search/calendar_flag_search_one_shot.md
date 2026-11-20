# Find Event support by searching for calendar

Cycle: v3/calendar_flag_search; Mode: one-shot; [Plans index](../README.md)
Debug mode: enabled (project marker; [log](calendar_flag_search_debug_log.md))
Effort study: railspike-effort, R1/P2 scope v1; [ledger](../effort_ledger/calendar_flag_search.md)
Extended estimates: enabled; bounded single change, no separate work units

**Cycle estimate: 0.05**

Status: change verified through the canonical PHP fixture and filter JavaScript; full browser verification pending. Draft review only.

Request: [Feature Flags search for calendar misses Event support](https://zenmemes.com/threads/thread-20261116190930-2eb2509f).
Authorization: the maintainer requested recurring ZenMemes sweeps and PRs for straightforward fixes needing no decisions. Draft PR only; no rename, flag behavior change, deployment, merge, or forum write.
Feedback: searched by report ID and flag terms; no exact item found. PF-20261111-F08 is a separate request to consider renaming the label; leave its status and scope untouched.

Reuse: the existing FeatureFlagRegistry description is displayed and included in feature_flags.php's search text; add the missing term there. Use the real-registry fixture in tests/browser/feature_flags_toolbar.mjs; do not add an alias mechanism or edit the search algorithm.
Completion: on Tools → Feature Flags, the calendar query shows Event support and Calendar feed; clearing restores canonical order. Users can clear the search to recover the complete list. Configuration, label, and key are unchanged.
Risk: misleading copy or unrelated flag behavior; describe what Event support actually does and preserve the separate feed flag. Revert this single change if needed.
Concepts touched: existing Feature Flags search used unchanged; no inventory addition.

Verification: reproduce the missing row with the new browser assertion before editing the description. Afterward rerun the browser fixture and local smoke plus feature flag tests; inspect the diff and UI-copy checklist. Full suite and production acceptance are not in scope.

Starting revision: 597f5328ffb939d4d715fc2a8a78c7d49206a847; branch fix/calendar-flag-search.
Planning commit: 1d34c2e locally, corresponding GitHub planning commit 4f46cb5 with the same tree.

## Study history
- Intake, 2026-11-20T19:27:32Z: 0.05 whole-cycle projection, scope v1, R1/P2, after source inspection (no pristine estimate); one exposed rater.
- Execution entry: 0.05 reaffirmed; no scope revision. This checkpoint was recorded after the planning commit, before the product edit.

*— Codex · 2026-11-20 UTC*

## Delivery and verification

- Changed only the Event support description in the existing flag registry. No feature-key, label, configuration, permission, or search code changes.
- Added the exact query and clearing checks to the canonical-registry section of the existing Playwright fixture.
- A disposable Node DOM harness ran the unmodified production filter against the real PHP-generated canonical fixture: the new assertion failed on main because Event support was absent; after the fix both flags appeared and clearing restored the entire canonical list. This exercises filtering but does not verify browser layout.
- Focused FeatureFlagsBehaviorTest and FeatureFlagEvaluatorTest: 30 passed. Smoke with those and FeatureFlagChangePageTest: 423 passed and 65 failed on both main and the proposed fix, with the same failure IDs in this environment (including GPG-agent startup and HTTP-related failures). The full suite was not run.
- Chromium could not open a local socket in this executor. Browser acceptance on phone and desktop is pending; rerun the existing browser test on a Chromium-capable executor. After eventual deployment, use Tools → Feature Flags → search for calendar; then clear the query. The separate rename remains out of scope.
- No merge or deployment authorized or performed. No new dependencies or data migrations.

Closeout, 2026-11-20T19:31:07Z: scope v1 delivered locally, browser acceptance pending. Retrospective scope assessment: 0.05; maintainer acceptance pending.

*— Codex · 2026-11-20 UTC*
