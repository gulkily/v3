> **Feature plan:** [Step 1](./editable_tagsets_step1_solution_assessment.md) · [Step 2](./editable_tagsets_step2_feature_description.md) · [Step 3](./editable_tagsets_step3_development_plan.md) · [Step 4](./editable_tagsets_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/editable_tagsets`; [cycle ledger](../effort_ledger/editable_tagsets.md). Standing P2 enrollment; follow-up to `v3/tag_unlocks`.

Extended estimates: enabled

**Cycle estimate: 0.60**

Branch: `editable-tagsets`. Step 1 commit `b8332d407`; `origin/main` (`e2c112ca`) merged in `b2becf5f` so the work starts from current code, including the state-button verbs from PR #363; planning commit `0e021424`.

## Step 4 entry

2026-11-22T23:20Z: Step 3 projection **0.60** reaffirmed after the planning commit. Stage identities and original stage-work estimates are copied from Step 3 below; copying is not a new rating. Single exposed Claude rater.

## Enhancement requests

None during Step 4 so far. Earlier requests stay in [Step 2](./editable_tagsets_step2_feature_description.md#enhancement-requests).

## Stage 1 - Site record loader and checks

**Original estimate: 0.10**

**Retrospective: 0.10**

- Changes:
  - `Support\Tagsets`: `check(text)` returns "Line N: …" errors. It catches unknown keys, repeated keys, a bad schema, bad tag names, Target and Exclusive values, built-in tags (`TagScore::isScoredTag`), blocks with no Parent or Unlocks, and a tag that is in a state group and also under another parent.
  - `Tagsets::useRepository()`, `forRepository()`, `stateFor()`, `active()` and `forget()`: the rules come from `records/instance/tagsets.txt` when it passes `check()`, otherwise from the shipped file, with the record's first error kept for the page. The cache key is the file time and size.
  - `Application` and `LocalWriteService` set the active repository, as `QdbModeration::useRepository` does. The write check, `ReadModelBuilder` and `IncrementalReadModelUpdater` pass `forRepository($repositoryRoot)` explicitly, so a worker rebuild never depends on the static root.
  - Callers left on `forApplication()` all run inside an `Application` request: three templates, `ToolsPageController`, `AgentCheckinController`, and `PostResolution` as a fallback.
- Verification:
  - Passed: `./v3 test --smoke TagsetsTest TagUnlocksTest ReactionSendOrderTest` (510 run, 510 passed). New: `TagsetsTest` covers each error by line, the site record taking over, and the fallback on a bad record. `TagUnlocksTest::testSiteRecordSetsTheButtonsAndTheWriteCheck` shows a Done button from a site record, the write check accepting `done`, and Claim returning after an invalid record.
  - Full suite not run (maintainer runs it).
- Notes:
  - `check()` is stricter than `parse()`. The shipped file and older text still parse as before, so a block that `parse()` skips is still skipped when it comes from the shipped file.
  - Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-22 UTC · Model: claude-opus-5-5*

## Stage 2 - Signed save and reset

**Original estimate: 0.15**

**Retrospective: 0.15**

- Changes:
  - New `Canonical\TagsetsChangeRecord` and `TagsetsChangeRecordParser` for `records/tagsets-changes/tagsets-change-<id>.txt`. The headers are Record-ID, Created-At, Operation (`set` or `reset`) and Operator-Identity-ID. The body is the full tagsets text, so the signature covers the rules themselves. A reset record has no body.
  - `LocalWriteService::prepareTagsetsChange()` and `finalizePreparedTagsetsChange()` follow the feature-flag pair. Both need an approved operator (`ApprovalPolicy::isOperator`). Both refuse text that fails `Tagsets::check()`, with every error on its own line. Finalize verifies the detached signature, then writes the record, its `.asc`, and writes or removes `records/instance/tagsets.txt`, all in one commit. Saving the same text, or resetting with no site record, writes nothing.
  - `normalizeTagsetsText()`: LF line endings, no trailing spaces, one final newline.
  - Path rules: `CanonicalPathResolver::tagsets()` and `tagsetsChange()`; `SourcePathValidator` accepts both paths and their signatures; `ArchiveRecordCatalog` accepts the site file as `instance:tagsets` (change records stay out of archives, as feature-flag changes do); `ActivityService` labels both.
- Verification:
  - Passed: `./v3 test --smoke EditableTagsetsTest TagsetsTest TagUnlocksTest CanonicalRecordParsersTest ContentImportPlannerTest CommitMapTest ApprovalAcceptanceTest` (534 run, 534 passed).
  - New `EditableTagsetsTest`: an operator sets and resets, with the commit holding the three files and the reset commit deleting the site file. Saving twice and resetting twice write nothing. Built-in tags are refused with both errors. A forged signature and a tampered record are refused with nothing written. A linked but unapproved key is refused.
  - Full suite not run.
- Notes:
  - The archive-import early check in Step 3 became a path-rule assertion: no importer code branches on `instance:feature-flags`, so the catalog entry is all an import needs.
  - Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-22 UTC · Model: claude-opus-5-5*

## Stage 3 - Recompute states and clear pages

**Original estimate: 0.10**

**Retrospective: 0.10**

- Changes:
  - `IncrementalReadModelUpdater::applyTagsetsWrite($commitSha)` recomputes every post that has a stored state or a mark that is now a state tag. It uses the same `derivePostReactionState` and `updatePostReactions` as an approval change (`refreshApprovalSensitivePostReactions`), writes the read-model metadata, and returns the posts whose state changed.
  - `finalizePreparedTagsetsChange()` runs it after the commit, or does a full rebuild when the read model can't be updated incrementally. It then clears generated pages (`invalidateAllGeneratedPresentation(false)`). If the update fails, the read model is marked stale (`tagsets_refresh_failed`), and the error says the rules were saved and the states will rebuild. The response includes `updated_posts`.
- Verification:
  - Passed: `./v3 test --smoke EditableTagsetsTest TagsetsTest TagUnlocksTest AgentCheckinTest` (523 run, 523 passed).
  - New `EditableTagsetsTest::testSavingRecomputesPostStatesAndClearsGeneratedPages`: a resolved reply opens again when the state group is removed (1 post updated), a generated thread page is deleted, the thread page has no resolved fold, reset brings the state back, and a full rebuild gives the same state as the incremental update.
  - Full suite not run.
- Notes:
  - Renaming a parent (for example `issue` to `work`) keeps existing states, because a state comes from the mark's tag and not from the thread's parent. What changes is which threads show the buttons. Stage 4's preview reports those threads, as Step 2 asks.
  - Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-22 UTC · Model: claude-opus-5-5*

## Stage 4 - Preview and save API

**Original estimate: 0.08**

**Retrospective: 0.10**

- Changes:
  - `POST /api/preview_tagsets` returns errors by line, the parsed rules, and `affected_threads`. It saves nothing.
  - `POST /api/prepare_tagsets_change` and `/api/finalize_tagsets_change` call the Stage 2 pair.
  - All three routes are in `ToolsPageController` for active operators only, use the `fastmodChangeApi` shape, and are listed in `ApiRouteCatalog` and in `Application`'s members-only allowance.
  - `MarkedPostsQuery::losingButtons($pdo, $current, $next)`: a thread is listed when one of its posts carries a tag that the current rules unlock for that post and the new rules do not. It reads thread tags and labels as the buttons do, and gives the lost tags and the number of marks.
- Verification:
  - Passed: `./v3 test --smoke EditableTagsetsTest TagUnlocksTest ApiRouteCatalogTest LlmsTxtRendererTest` (519 run, 519 passed).
  - New `EditableTagsetsTest::testPreviewAndSaveRoutesAreForOperatorsAndListThreadsThatLoseButtons`: all three routes refuse a signed-out viewer. The preview lists `root-001` with a lost `resolved` mark when `#issue` is dropped, and lists nothing when it is kept. An invalid text gives the line error and no rules. Prepare and finalize over HTTP save the site file.
  - Full suite not run.
- Notes:
  - Fixed an existing failure in `LlmsTxtRendererTest::testReferenceNamesOnlyCatalogRoutesAndRenders`, which also failed without this stage. Its route pattern stopped at the hyphen in `/api/docs-version`. That route was also missing from `ApiRouteCatalog`, and `ApiRouteCatalogTest` had the same pattern, so it missed the gap. Both patterns now accept hyphens, and the catalog lists `/api/docs-version`. This is a correction, not new scope.
  - Estimate details: the retrospective is 0.10 against the original 0.08 because of that fix.

*— Claude · 2026-11-22 UTC · Model: claude-opus-5-5*

## Stage 5 - Edit view on the Tag Unlocks page

**Original estimate: 0.15**

**Retrospective: 0.15**

- Changes:
  - `templates/pages/tag_unlocks.php`: the opening line says whether the rules are the shipped ones or were set on this site. A site record with an error shows that error. Active operators get an Edit disclosure with the text, Preview, Save, and Reset to default (only when site rules are in force).
  - Operators are recognised in the browser before paint: the page carries active operator fingerprints, and an inline script sets `data-tagsets-editor-eligible`, as `partials/operator_grant.php` does. The server HTML is the same for every viewer.
  - New `ApprovalPolicy::activeOperatorFingerprints()`. `operatorGrantActors()` now uses it, with the same result.
  - New `public/assets/tag_unlocks_edit.js`. Save stays off until the current text has previewed without errors, and any edit turns it off again. The preview shows the rules or the line errors, plus the threads whose marks would lose their buttons. Save signs through `ForumBrowserSigning` the way `feature_flags.js` does, then reloads. Reset to default loads the shipped text, previews it, and saves as a reset.
  - `ToolsPageController::tagUnlocks()` passes the text in force, its source and error, the shipped text and the operator fingerprints, and loads the signing scripts. `tools.css` styles the editor.
- Verification:
  - Passed: new `node tests/browser/editable_tagsets.mjs` (run with the environment's `PLAYWRIGHT_MODULE` and `PLAYWRIGHT_BROWSERS_PATH`).
    - Signed out, and with a key that is not an operator: no Edit.
    - As an operator: an error keeps Save off. Dropping `#issue` lists the thread with one closed mark. Adding an `event` block saves, and Attend and Interested appear on an `#event` reply, where a tap succeeds.
    - Reset to default removes the site file, the event buttons go away, and the closed reply stays closed.
    - Light, dark, gloss, word97, midnight and bubblegum at 375 and 1280 px: text box and buttons inside the viewport, no sideways scroll. Screenshots were checked by eye, and that check led to Preview and Save sitting side by side instead of full width.
  - Passed: `node tests/browser/tag_unlocks.mjs` (unchanged buttons after the loader change).
  - Passed: `./v3 test --smoke EditableTagsetsTest TagUnlocksTest TagsetsTest` (512 run, 512 passed) and `WriteApiSmokeTest::testOperatorGrantRoutesAndAccessPageButton` for the shared fingerprint helper.
  - Not applicable: navigation icons, since no navigation destination was added or renamed.
  - Full suite not run.
- Notes:
  - Without JavaScript an operator sees Edit, but its buttons do nothing. The page stays readable.
  - Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-22 UTC · Model: claude-opus-5-5*
