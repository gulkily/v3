# Import review summary one-shot record

Cycle: `v3/import_review_summary`; Mode: one-shot; Record/index: [this record](./import_review_summary_one_shot.md), [plans index](../README.md)
Debug mode: enabled (no findings beyond the parent cycle's log)
Effort study: `railspike-effort` / `v3/import_review_summary`; [host ledger](../effort_estimation_trial.md)
Extended estimates: disabled (single work unit)

**Cycle estimate: 0.08**

Status: locally complete; next permitted action: push, open the PR, reply in the thread.

Original request: a screenshot from the maintainer, 2026-11-17 UTC, of Tools → Import on zenmemes.com after a real import from https://zenmemes.com (imported 7,172; invalid 193; excluded 907). The Review row listed every root-cause path, hundreds of lines, so the page became thousands of pixels tall. Follow-up to merged PR #287. Feedback: PF-20261116-F02. Thread: https://zenmemes.com/threads/thread-20261113042243-1cbe8b8a.

Authorization: the maintainer's PR feedback on merged code; per `AGENTS.md`, a new branch from `origin/main` and a new PR. Not merging or deploying.

Outcome and work type: application change. The Review row shows one line per state and reason with a count, largest first. Paths stay under Advanced.

Approach and risks: a reason's per-record detail after the first ": " is dropped for grouping ("Missing dependency: post …" becomes "Missing dependency"). Advanced now sorts conflicts, invalid and unsupported entries before excluded ones, so the 500-entry cap hides exclusions first. No change to the runner or its report.

Reuse decisions: template-only change in `templates/pages/tools_import.php`; the existing `ImportPageTest` result test and the `tools_import.mjs` fixture gain production-shaped causes.

Completion conditions: Tools → Import after a large import shows a short Review row; Advanced still lists per-file entries.

Concepts touched: [Instance content import](../../concepts/instance_content_import.md), used unchanged.

Verification: `./v3 test --smoke ImportPageTest`: passed (smoke set included). `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/tools_import.mjs`: passed, 80 cases; the word97 1280 px result screenshot shows three grouped Review lines. Full suite not run. The production page was not re-checked; that needs a deploy.

Starting revisions and branch: `34d87c86`; `fix/import_review_summary`.

Study history: intake 0.08 after reading the screenshot and template (no pristine intake); closeout retrospective 0.08; Claude single exposed rater; R1 hashes not re-verified. Maintainer acceptance pending.

Commit/merge/publication status: committed on branch; PR pending.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
