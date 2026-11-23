> **Feature plan:** [Step 1](./editable_tagsets_step1_solution_assessment.md) · [Step 2](./editable_tagsets_step2_feature_description.md) · [Step 3](./editable_tagsets_step3_development_plan.md) · Step 4

Debug mode: enabled

Effort study: `railspike-effort` / `v3/editable_tagsets`; [cycle ledger](../effort_ledger/editable_tagsets.md). Standing P2 enrollment; follow-up to `v3/tag_unlocks`.

Extended estimates: enabled

**Cycle estimate: 0.60**

## Completion Contract

- Normal entry: an operator opens Tools → Tag Unlocks, taps Edit, previews and saves, on phone and desktop.
- End-to-end outcome: after a save, a reply in a thread with the new parent tag shows the new buttons, and a tap writes a mark that counts. Reset to default brings back the shipped rules.
- Recovery: a refused save keeps the text in the editor and names the line with the error. A failed signature or commit leaves the old rules in force and says so. A bad site record falls back to the shipped rules, and the page shows the error.
- Deployment: no schema change; the new record lives in the content repository. The maintainer deploys to zenmemes and adds a tagset there.
- Release: Step 4 complete with a PR open; production replay is the maintainer's after merge.

## Key Risks

- **High risk:** **Stale post states.** States are stored in the read model. Impact: wrong folds and wrong open-work lists after a save. Early validation (Stage 3): a test that renames `issue` and checks that the states clear. Mitigation: recompute the states of marked posts inside the save; if that fails, mark the read model stale so the usual rebuild runs.
- **One loader for every caller.** `Tagsets::forApplication()` reads only the shipped file, has no repository root, and eight places call it. Impact: some views keep the old rules. Early validation (Stage 1): replace the loader and check each caller in a test. Mitigation: `Application` and `LocalWriteService` set the active repository; the loader caches by record file time.
- **High risk:** **A bad record breaks thread pages.** Mitigation: the loader checks the record and falls back to the shipped rules; Stage 1 tests it.
- **Cached pages.** Impact: generated thread pages keep old buttons. Mitigation: clear all generated presentation after a save (`invalidateAllGeneratedPresentation`). A thread page already cached offline in a browser shows old buttons until it is next fetched online; the server refuses a stale tap with its usual message. Accepted limit.

## Stage 1 - Site record loader and checks

**Estimate: 0.10**

- Goal: one loader returns the site rules when `records/instance/tagsets.txt` is valid, otherwise the shipped file, and reports errors by line.
- Dependencies: none.
- Expected changes: `Tagsets::check(string $text): list of errors` (bad names, reserved tags via `TagScore::isScoredTag`, a tag in two state groups, malformed blocks); `Tagsets::useRepository(string $root)` and a cached site-aware `forApplication()`; `Tagsets::source()` (default or site, plus the site error). `Application` and `LocalWriteService` call `useRepository`.
- Verification approach: `TagsetsTest` for each error and the fallback; `TagUnlocksTest` shows site-record buttons on a thread page and in the write check.
- Estimate details: driver: static cache across test roots; uncertainty: low.
- Risks or open questions: covered by Key Risks (one loader; bad record).
- Canonical components/API contracts touched: `Support\Tagsets` (extend), `TagScore::isScoredTag` (reuse).

## Stage 2 - Signed save and reset

**Estimate: 0.15**

- Goal: an operator's signed change writes or removes the site record in one commit.
- Dependencies: Stage 1.
- Expected changes: `prepareTagsetsChange(input, operator)` and `finalizePreparedTagsetsChange(input, operator)` in `LocalWriteService`, modelled on the feature-flag pair. A `Key: value` change record (`Operation: set` with the full text, or `Operation: reset`) at `records/instance/tagset-changes/<id>.txt` plus `.asc`. Path rules in `CanonicalPathResolver`, `SourcePathValidator`, `ArchiveRecordCatalog`; a label in `ActivityService`. Invalid text is refused at prepare and again at finalize.
- Verification approach: new `EditableTagsetsTest`: operator save, reset, non-operator refused, invalid text refused, signature mismatch refused, commit contains the three files.
- Estimate details: driver: new record kind across path validators; uncertainty: medium.
- Risks or open questions:
  - Impact: a path validator missed means imports or archives reject the new files.
  - Early warning / validation: an archive import test with a tagset change.
  - Mitigation: grep every `feature-flags.txt` rule and mirror it.
- Canonical components/API contracts touched: `prepareFeatureFlagChange` / `finalizePreparedFeatureFlagChange` (pattern), `storePreparedPost`, `commitCanonicalWrite`, `ApprovalPolicy::isOperator`.

## Stage 3 - Recompute states and clear pages

**Estimate: 0.10**

- Goal: after a save or reset, every post state and generated page follows the new rules.
- Dependencies: Stage 2.
- Expected changes: recompute `resolution` for posts with state marks, using the same derivation as `IncrementalReadModelUpdater`; then `invalidateAllGeneratedPresentation(false)`. On failure, mark the read model stale (`staleMarker`) and say so in the response.
- Verification approach: `EditableTagsetsTest`: rename `issue` to `work`, then a resolved reply unfolds and the Tools page lists no marked posts; reset restores both. A full rebuild gives the same states.
- Estimate details: driver: matching the incremental and rebuild derivations; uncertainty: medium.
- Risks or open questions: covered by Key Risks (stale post states).
- Canonical components/API contracts touched: `ReadModel\PostResolution`, `IncrementalReadModelUpdater`, `StaticArtifactInvalidator`.

## Stage 4 - Preview and save API

**Estimate: 0.08**

- Goal: the page can preview text and save it.
- Dependencies: Stages 1–3.
- Expected changes: `POST /api/preview_tagsets` returns the parsed rules, errors and affected threads (threads with marks whose tags the new text no longer unlocks there). `POST /api/prepare_tagsets_change` and `/api/finalize_tagsets_change` call Stage 2. All three are operator-only in `ToolsPageController`, listed in `ApiRouteCatalog` and the `Application` route list.
- Verification approach: `EditableTagsetsTest` for the three routes, including 403 for non-operators.
- Estimate details: driver: affected-thread query; uncertainty: low.
- Risks or open questions: none beyond Stage 2.
- Canonical components/API contracts touched: `fastmodChangeApi` pattern, `Tools\MarkedPostsQuery` (reuse for affected threads).

## Stage 5 - Edit view on the Tag Unlocks page

**Estimate: 0.15**

- Goal: operators edit, preview, save and reset from the page.
- Dependencies: Stage 4.
- Expected changes: `templates/pages/tag_unlocks.php` gains Edit, a text box with the current text, Preview, Save and Reset to default. Operators are recognised in the client before paint, as `partials/operator_grant.php` does. A new `public/assets/tag_unlocks_edit.js` reuses `ForumBrowserSigning` the way `feature_flags.js` does. The page shows "Shipped default" or "Site rules" and any site-record error. No new navigation entry.
- Verification approach: new browser test `tests/browser/editable_tagsets.mjs`: operator adds an `event` block, saves, sees Attend on an `#event` reply; a non-operator sees no Edit; an error blocks Save. Light, dark, gloss, word97, midnight and bubblegum at phone and desktop width.
- Estimate details: driver: themes and phone width; uncertainty: medium.
- Risks or open questions:
  - Impact: a long text box is awkward on phones.
  - Early warning / validation: phone-width screenshots in the browser test.
  - Mitigation: full-width monospace box; preview below it.
- Canonical components/API contracts touched: `operator_grant.php` pre-paint pattern, `feature_flags.js` signing flow, Tools page styles.

## Stage 6 - Docs and release

**Estimate: 0.06**

- Goal: the change is documented and ready to merge.
- Dependencies: Stage 5.
- Expected changes: `docs/concepts/tagset.md` (site record), new concept entry *site tagsets record*, `docs/reference/agent_api.md` and the comment in `resources/tagsets.txt` (site copy wins). Final diff review; PR with the checkout snippet.
- Verification approach: smoke set plus the cycle's tests; `git diff --check`; links resolve. Full suite not run.
- Estimate details: driver: docs only; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: concept inventory.

## Concepts touched

- [tagset](../../concepts/tagset.md): changed (site record replaces the shipped file).
- New: *site tagsets record*.
- [feature flags](../../concepts/feature_flags.md), [reaction record](../../concepts/reaction_record.md): used, unchanged.

## Study Checkpoint

2026-11-22T23:30:00Z; Step 3 handoff; scope v1; starting revision `92ef92501`; reference R1. Claude, single exposed rater. Projected total revised from **0.50** to **0.60**: the change needs a new record kind across four path validators, a site-aware loader for eight callers, and a state recompute. Stage values are stage work, not shares of the total. Not hours; no approval to advance.

Status: Approved Step 3 (maintainer, 2026-11-22).

*— Claude · 2026-11-22 UTC · Model: claude-opus-5-5*
