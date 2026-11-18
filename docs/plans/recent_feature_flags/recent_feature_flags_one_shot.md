# Recently released feature flags — one-shot

Cycle: `v3/recent_feature_flags`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](recent_feature_flags_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/recent_feature_flags`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/recent_feature_flags.md))
Extended estimates: enabled (no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.1**

Status: technically complete on `feat/recent_feature_flags`; pushed for review, not merged or deployed. Next action: maintainer review (including the window reading), merge and deployment.

## Request and authorization

Original request, 2026-11-17 (maintainer, [thread](https://zenmemes.com/threads/thread-20261117184223-e6485eca)): "feature request: for the feature flags page to have a recently released features section that's easy to find".

Thread so far: Claude found that git already dates each flag (28 of 47 then were under two weeks old) but a shallow clone can't read that at page load, and suggested a `releasedOn` date on `FeatureFlagDefinition`. The maintainer: "Recent is probably 1-2 days. Yes to release dates. Please make a PR?" Claude then pointed out that a 1–2 day window counted from today leaves the section empty after a quiet weekend or on an install that pulls weekly; no answer yet.

Authorization: the maintainer, 2026-11-18 (Claude session): "Can you please scan Zen memes for anything that's asking for a PR? And if the task seems appropriate for a one-shot, make the PR and reply in the thread." With "Please make a PR?" in the thread, this authorizes a one-shot through an open PR and a thread reply. Exclusions: merge, deployment.

Feedback item: none matched in `docs/feedback/items/` (searched the thread ID, "recent", "release"). Created [PF-20261118-F06](../../feedback/items/PF-20261118-F06.txt) in the completion commit.

## Outcome and work type

Application change. Tools → Feature Flags opens with a "Recently released" list at the top: the flags released on the newest release date and the day before, each linking to its row, with its date.

## Approach and risks

- `FeatureFlagDefinition` gains `releasedOn` (`YYYY-MM-DD`), set in the registry when a flag is added. Backfilled from git: the first commit that put the flag's name in `FeatureFlagRegistry.php` (`git log -S`). Flags that predate the registry (2026-06-27) get that date; the list only cares about recent ones.
- The window is two days (the maintainer's "1-2 days") counted back from the newest release date rather than from today. Reading of the open question: this keeps the section from going empty after a quiet stretch, and each entry shows its date, so an old "newest" batch is visibly old. Reversible in one method if he prefers today-based.
- A registry test requires every flag to have a valid date, so new flags carry one. Open PR #329 adds `FORUM_OPEN_BESIDE_ENABLED`; whichever merges second needs that flag's date added. Noted in the PR.
- The list is links, not copies of the rows, so search, status filters, forms and `id="flag-…"` anchors stay single. Following a link to a row hidden by a filter clears the filter first.

## Reuse decisions

- `FeatureFlagRegistry` already owns presentation (`PRESENTATION_ORDER`, `presentationGroups()`); add `recentlyReleased()` beside it.
- Heading reuses `.feature-flag-group-heading`; the box reuses the look of `.feature-flag-list` (border, panel). The section is deliberately not a `.feature-flag-group`, which `feature_flags.js` hides when it has no visible rows.
- Row anchors `#flag-<KEY>` already exist.
- `CONTRIBUTING.md`'s feature-flag section gets one sentence on setting `releasedOn`.

## Concepts touched

None in the concept inventory.

## Completion conditions and verification plan

- Normal entry: Tools → Feature Flags shows "Recently released" first; a link scrolls to its flag's row, also while a filter hides it.
- Tests: registry test for dates on every flag and for the window; page test that the list renders in order with links. Find the existing feature-flag page tests and extend them.
- `./v3 test --smoke` plus those tests. Full suite not run.
- Browser check of the page in a couple of themes, including the filter case.

Starting revision and branch: `31e16b066774dbdf8f4d3709ae4ad021bfff9516` on `main`; branch `feat/recent_feature_flags`.

Planning commit: `19980370` (2026-11-18T07:31Z), containing only this record and its index link.

## Dated execution history

- 2026-11-18T07:30Z: plan written after reading `FeatureFlagDefinition`, `FeatureFlagRegistry`, `feature_flags.php`, `feature_flags.js`, the flag CSS in `tool-details.css` and `CONTRIBUTING.md`; release dates computed with `git log -S` on a full clone.
- 2026-11-18T07:32Z: planning commit `19980370` confirmed. Added `releasedOn` to the definition and all 56 registry entries (scripted insertion; a first pass mis-parsed a double-quoted description and dated only 30, caught by count and redone), `recentlyReleased()`, the template section, CSS and the filter-clearing link handler.
- 2026-11-18T07:38Z: tests. `FeatureFlagsBehaviorTest` gains a date-on-every-flag check and a window test; the toolbar fixture flags get dates (one undated, one old); `tests/browser/feature_flags_toolbar.mjs` checks the list, its order, the filter-clearing click, and that every link on the real registry page hits a row. Its group-heading assertion now scopes to `.feature-flag-group` headings, since the new section reuses the heading class. Correction to an existing test's selector, not to its intent.
- 2026-11-18T07:40Z: `CONTRIBUTING.md` feature-flag section: set `releasedOn` when adding a flag.
- 2026-11-18T11:20Z: merged `main` to clear conflicts. Main had added `FORUM_OPEN_BESIDE_ENABLED` (first in the registry in `84e6062d`, 2026-11-17), so it gets `releasedOn: '2026-11-17'`; 57 of 57 flags dated. Reran `./v3 test --smoke FeatureFlagsBehaviorTest` (484 passed) and `tests/browser/feature_flags_toolbar.mjs` (pass); full suite not run. The feedback item was renumbered PF-20261118-F04 → PF-20261118-F06 because main had already used F04 (and #330 uses F01).
- 2026-11-18T11:50Z: review fixes (independent review agent). A Recently released link now lands its row below the sticky toolbar (scroll margin measured from the toolbar on click, so nothing changes after paint), and modified or middle clicks no longer clear the filter. `feature_flags_toolbar.mjs` checks every real-registry link lands below the toolbar at 375 and 1150px; it fails without the fix. Left as nits: dates show without a year; "No flags match." sits above the Recently released list.

## Study history

- Intake, 2026-11-18T07:30Z: **0.1** whole-cycle projection, scope v1, R1/P2. Recorded after inspecting the files above (not a blind intake). Single exposed rater (Claude).
- Execution entry, 2026-11-18T07:32Z: 0.1 reaffirmed.
- Closeout, 2026-11-18T07:42Z: delivered scope v1. Retrospective **0.1**. Maintainer fit and acceptance pending.

## Outcome

Delivered: every flag has `releasedOn`; Feature Flags shows "Recently released" at the top (newest release date and the day before, with dates, linking to rows; a click on a filtered-out row clears the filter). Open: whether the window should count from today instead; one-line change in `recentlyReleased()`.

## Verification

- `./v3 test --smoke FeatureFlagsBehaviorTest FeatureFlagEvaluatorTest`: 484 of 484.
- `node tests/browser/feature_flags_toolbar.mjs`: all three passes, including nine themes at 375 and 1150 px and the real registry; screenshots of Gloss and Word97 reviewed.
- `./v3 feedback check`: 0 problems; `./v3 feedback index` regenerated.
- Not run: the full suite. Release dates for flags older than the registry (2026-06-27) are the registry's first commit, not the flag's true first day.

## Final review

Diff limited to `FeatureFlagDefinition.php`, `FeatureFlagRegistry.php` (dates plus one method), `feature_flags.php`, `tool-details.css`, `feature_flags.js`, `CONTRIBUTING.md`, the two tests and the fixture, this record and its index line, the feedback item and index, ledger and debug log. No new dependency or route. PR #329 adds `FORUM_OPEN_BESIDE_ENABLED`, which will need a `releasedOn` when both are merged.
