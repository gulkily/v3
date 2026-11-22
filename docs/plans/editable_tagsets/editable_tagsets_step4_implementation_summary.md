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
