> **Feature plan:** [Step 1](./fastmod_meta_score_step1_solution_assessment.md) · [Step 2](./fastmod_meta_score_step2_feature_description.md) · [Step 3](./fastmod_meta_score_step3_development_plan.md) · [Step 4](./fastmod_meta_score_step4_implementation_summary.md)

Debug mode: enabled

Implementation in progress on `feat/fastmod-meta-score`. Planning commit: `08b9b874`.

## Stage 1 - Modular rubric contract
- Changes: Added catalog/composer with deterministic stable identities, compact moderation/support templates, shared introduction, nonempty selection validation, and effective composition hashes. Configuration accepts a rubric list; production wiring follows in Stage 3.
- Verification: `php tests/run.php FastmodRubricsTest FastScoringConfigTest` — 6 passed; new PHP files lint clean. Tests cover omission, ordering, invalid sets, added templates, empty text, and revision changes. Compared moderation template against existing categories and exceptions.
- Notes: Both default rubrics are enabled when unset; sorting establishes the output order independent of operator click order. No core read-model change.

## Stage 2 - CSV evaluation and durable score sets
- Changes: FastPostScorer uses the existing text completion contract, parses complete CSV atomically, and produces a named score map. Private score storage migrates to nullable score-set JSON; legacy rows remain explicitly legacy moderation-only. Compatibility probability is moderation only, never an aggregate or first score.
- Verification: `php tests/run.php FastmodCsvTest SqliteFastScoreStoreTest FastScoreWorkflowServiceTest` — all passed. Added provider-call counting, malformed output rejection, meta-only result, prior-store migration, and restart/idempotence coverage.
- Notes: Private-store schema only; core read-model schema unchanged. Effective prompt/factory and worker wiring follows in Stage 3.

## Stage 3 - Worker and revision integration
- Changes: Factory composes enabled templates and uses text completion; enqueue/audit/backfill revision calculation now uses the same composition. Worker execution uses its captured scorer revision to reject stale work before calling the provider. Added shared persisted-selection reader for HTTP/CLI/worker; saves follow in Stage 4.
- Verification: Focused CSV/rubric/sweep/revision/audit/backfill/task-worker tests passed, including new stale-revision exclusion with zero provider calls. Existing budget/call accounting tests pass.
- Notes: Stale work is recorded as excluded with `rubric_changed_before_score`, with no implicit replacement enqueue. Explicit audit/backfill can request new-revision evaluation. Introduction defaults to the new shared text; explicit prompt paths remain supported as introduction overrides.

## Stage 4 - Signed operator rubric dialog
- Changes: Tools Fastmod page opens an accessible native dialog; options come from repository templates. Root-approved operators prepare and sign a private settings action using existing browser signing, prepared-action storage, operator authorization, write lock, and detached-signature verification. Validated selections persist atomically with private permissions; web and worker share the reader. Empty/unknown selections and changed templates reject saves without replacing valid settings.
- Verification: Signed-settings smoke plus rubric/config tests passed. Initial combined run exposed stale default-path and fingerprinted-asset assertions after the stage commit; corrected in a follow-up Stage 4 commit before Stage 5. Smoke uses a generated OpenPGP operator, verifies signature/tampering/replay handling, private persistence/reload, empty-save preservation, anonymous rejection, and authorized dialog rendering. PHP and JavaScript syntax checks passed.
- Notes: Settings changes write no canonical post, create no task, and leave content repository HEAD unchanged. Browser keyboard interaction will be checked in final verification if browser tooling is available.

## Stage 5 - Identified result surfaces
- Changes: Post detail labels each rubric independently and marks legacy/earlier revisions or non-score status. CLI recent-work output includes named score sets and exclusion signals; worker progress carries score maps; smoke identifies CSV output. Audit reports enabled rubric identities. The retired synchronous HTTP scoring endpoint remains retired (410), with no new trigger.
- Verification: Post-detail legacy/multi-score/failure smoke, retired-endpoint smoke, store, sweep, audit CLI, and worker CLI checks passed. Updated the existing worker fixture to enqueue the real effective revision instead of an arbitrary stale key.
- Notes: A broader worker-command run exposed an unrelated empty-worker test using ambient project configuration; investigate/isolate in final verification. No scalar is repurposed as an aggregate.

## Stage 6 - End-to-end verification and operations
- Changes: Added a real-browser check using generated operator keys, isolated application/private state, normal publication, the production scorer/sweep, and a deterministic local HTTP provider. Updated existing scorer tests from JSON to CSV. Hardened nested/empty selection rejection and failed-result accounting; aligned generated introduction defaults and translated the prior generated prompt path. Updated reference/runbook, configuration comments, and rollback guidance. Isolated the empty-worker command fixture from ambient application work.
- Verification: Final focused run — 92 tests passed. `node tests/browser/fastmod.mjs` passed: keyboard dialog dismissal, empty-selection prevention, signed saves, no scoring on save, normal publication enqueue, one provider call for one or two rubrics, omitted disabled text, independently recorded/displayed scores, atomic malformed-output failure, and explicit retry recovery. Changed PHP and JavaScript syntax, plan links, and `git diff --check` passed.
- Notes: Core read-model schema unchanged; schema-version/rebuild requirements do not apply. Prior private-score schema migration and restart preservation are tested. No deployment or external live-provider call performed; model accuracy requires operator evaluation. `php tests/run.php` completed: 1,226 run, 1,225 passed, one failure in the untouched private-message concurrent-sync test (`testConcurrentSyncWritesLeaveForegroundMessageStoreResponsive`). Its isolated rerun passed (1/1). The full suite was started before the final targeted guards/assertions were edited; the final 92-test focused run and browser run cover those changes. An earlier run with a global absent-secrets override was stopped because that override invalidated tests intentionally loading their own private-config fixtures.


## Handoff
- All six stages completed on `feat/fastmod-meta-score`, with a planning commit and per-stage commits; Stage 4 also has an assertion-correction follow-up commit.
- Use Tools → Fastmod → Choose rubrics. Both initial rubrics are enabled by default; at least one selection is required. No new scoring trigger or moderation action was introduced.
- Remaining verification limit: external live-model accuracy was not tested. Full-suite concurrency failure passed independently; no private-message implementation was changed.
