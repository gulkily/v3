> **Feature plan:** [Step 1](./retraction_effects_step1_solution_assessment.md) · [Step 2](./retraction_effects_step2_feature_description.md) · [Step 3](./retraction_effects_step3_development_plan.md) · [Step 4](./retraction_effects_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, `v3/retraction_effects`; [ledger](../effort_estimation_trial.md#cycle-v3retraction_effects).

## Entry

- Approved Step 3 v5; branch `feat/retraction-effects`; planning commit `43ed5860` verified at HEAD before this summary or implementation.
- Scope: labels only, both removal paths, historical repair, durable removal and preserved replies. Remaining effect families and parent all-effects request stay incomplete.
- 2026-10-23 UTC, Codex single exposed rater, R1/P2, original baseline `e960f666e1146d26b2f9435d98a447c2594b9883`. Step 3 numeric projection explicitly remains unavailable pending exact frozen report/diff recovery. No retrospective or measured effort; no implementation or deployment verification yet.

## Stage 1 — Source-owned label provenance

- Changes: generated hashtag label records now carry source post ID and SHA-256 in thread/reply/signed publication paths. Parser retains legacy compatibility and requires a valid complete provenance pair. Shared ownership inspector checks source content, thread and author; absent/malformed/changed sources and legacy records remain unresolved. Independent reaction-label records keep their existing signed bytes. Thread-attributes prints ownership status/evidence.
- Verification: `php tests/run.php CanonicalRecordParsersTest` 46 passed; two focused WriteApiSmokeTest hashtag thread/reply publication tests passed with actual source-binding assertions. Writer PHP syntax passed. Initial new malformed-source test exposed a parser exception; inspector now reports invalid sources as unresolved and rerun passed. No UI/deployment claim.
- Notes: legacy inference deliberately does not use same author/time or bulk-import co-commit. Removal/evidence and later stages remain pending.

## Stage 2 — Durable removal and resolution evidence

- Changes: added LabelRemoval inspection/preparation and atomic evidence storage. Evidence binds source bytes, thread structure and exact label hashes; explicit operator resolutions reject conflicting targets/source provenance. Missing sources are recovered read-only from Git history; protected authority posts are refused. Evidence is trusted only when exact bytes exist in a local explicit acceptance commit; ordinary import commits do not grant acceptance. Runtime mutation/CLI wiring follows in Stage 5.
- Verification: parser/evidence suite 47 passed, including ordinary import rejection, explicit local acceptance and tampered evidence rejection. PHP syntax passed. Initial test used the parser-only exception helper; switched to the runtime exception helper and reran successfully.
- Notes: retained evidence contains structural metadata/hashes, not removed post prose. CLI authority is the local operator's existing filesystem/Git access, not a fabricated user signature. Files-only restores without trusted acceptance history fail closed. Later import/publication stages must carry this boundary consistently.

## Stage 3 — Retraction removes effective owned labels

- Changes: full and incremental label aggregation share an inactive-label calculation based on verified source retractions or accepted content-bound removal/resolution evidence. Surviving standalone labels remain effective; withdrawn labels no longer contribute scores, votes or label activity. Existing signed retraction refresh/retry performs the normal update without new signatures or deleting replies.
- Verification: 9 focused read-model/retraction API tests passed; signed-author retraction test additionally publishes `#pinned` and verifies empty effective labels after the signed flow. Rebuild regression covers removal of the sole owned label, survival of an independent duplicate, and surviving reply prose. No schema/query change in this stage; missing-root/startup work is Stage 4.
- Notes: legacy ambiguous labels remain effective until explicit resolution, rather than being guessed. Their inspection/recovery feedback is wired in later stages.

## Stage 4 — Removed-root conversation structure

- Changes: accepted physical deletions materialize content-free structural placeholders, even if old source bytes reappear. Surviving replies keep their own content and root identity; reply-target validation accepts retained root/parent structure. Schema version 23 forces old aggregations to rebuild; spec and version-22 normal-startup regression added (no new columns).
- Verification: 8 focused startup/retraction tests passed; physical-root removal/reintroduced-source test passed, verifying empty root body, null root subject and preserved reply/thread lookup. `FORUM_STATIC_HTML_ROOT=/tmp/retraction-schema23-static ./v3 rebuild tests/fixtures/parity_minimal_v1 /tmp/retraction-schema23.sqlite3` passed. Rendered/reply-publication acceptance follows CLI integration/browser stages.
- Notes: source-removal metadata never copies a surviving reply's content or identity into the root. Production database/artifacts untouched.

## Stage 5 — Physical deletion and explicit legacy repair

- Changes: source-post `delete-record` now accepts durable evidence and deletes exact owned label files/signatures within the shared execution/publication locks, refreshes aggregation and invalidates generated presentation. Repeating accepted deletions is safe. Added `--inspect`, `--repair-labels`, `--resolve-label=<id>` and `--json`; a missing legacy source can be reconstructed from trusted history and an explicitly accepted operator resolution. Missing-source repair records physical removal and restores conversation structure. Ordinary non-post deletion retains its existing path.
- Verification: actual CLI source-root deletion/owned-pin cleanup/rendered preserved reply/repeated-command/no-new-commit test passed; legacy source-only deletion followed by read-only inspection and explicit resolution passed. CLI help and PHP syntax verified. Ordinary label-delete compatibility test remains running due its existing full-static-build path; record final outcome in closeout.
- Notes: potential unowned legacy labels are reported for inspection, not automatically assigned. Commit-boundary interruption recovery and stale publishers are verified/strengthened in Stage 7. No production writes or deployment.

## Stage 6 — Import preservation

- Changes: public archive catalog recognizes source-removal evidence; destination import accepts only already trusted exact local evidence, reports files-only/foreign authority as invalid, and excludes resurrecting owned labels. Removed incoming sources become non-importing structural duplicates so preserved replies can still satisfy thread/parent dependencies. Existing retraction suppression remains shared with aggregation.
- Verification: ContentImportPlannerTest 10 passed, including old source/owned label replay with surviving reply, and untrusted evidence on a fresh destination. Existing invalid signatures, authority exclusions, rejected dependencies and repeat import cases passed.
- Notes: transporting a JSON receipt alone does not confer operator authority; trusted repository history must accompany disaster recovery. Full archive-runner verification follows closeout. No new external writes.

## Stage 7 — Interrupted acceptance and stale publication

- Changes: source removal uses an atomic private Git-directory journal; failed commits resume exact content-bound work before ordinary preparation, including already-unlinked tracked paths. Refresh failures mark derived state stale. Publication generation includes accepted removal/resolution evidence and requires matching source-removal metadata in the read model. Stale tokens cannot publish. Reusing deleted post IDs is refused by canonical writers. Legacy retraction API results distinguish unresolved label candidates; the existing reload acknowledgment displays an actionable operator-review message without guessing ownership or retaining removed prose.
- Verification: 18 retraction-sweep/CLI tests passed. New commit-hook interruption/retry and stale-publication rejection test passed. Signed legacy-label retraction returns unresolved records and preserves an unproven label; explicit legacy repair and candidate feedback tests passed. Existing ordinary label-delete/static compatibility test failed because concurrent planning-summary edits triggered its documentation publication guard; rerun without documentation writes is reserved for closeout, not classified as a product regression or baseline pass.
- Notes: browser phone/desktop feedback and affected-theme checks remain Stage 8. No new navigation mappings. Removal journal contains hashes/structural evidence, not source prose; trusted local Git history remains necessary for restoration.


## Stage 8 — Acceptance and closeout

- Changes: documented browser/source-removal semantics, source CLI inspection/explicit resolution/retry, canonical evidence trust, schema metadata and operator recovery. Added the actual ZenMemes root/two hashtag replies/two legacy label records plus a synthetic independent nested reply as an incident fixture. Final verification corrections preserve source-removal structure/type and source-route/LLM privacy, fail closed for missing tracked evidence, suppress late content-bound labels, clear stale markers after successful retry, and retire presentation before refresh. Removal evidence is readable through existing source routes. The label-only model's generated label records are leaves; other generated-effect families remain deferred.
- Verification: final focused acceptance **98/98 passed**, covering parsers, signed retraction, source deletion, shared labels, score/vote aggregation, root preservation, Forte detail, actual ZenMemes repair, real import-runner replay, interrupted commits, stale publishers, offline snapshot/manifest and version-22 startup migration. Additional source-evidence/parser rerun **48/48 passed** after source-route wiring. Existing non-post deletion/full-static compatibility test **passed** on a stable-documents rerun (134 seconds); its earlier documentation-generation race failure is not a product regression. Version-23 `./v3 rebuild` passed. CLI help and PHP/JS syntax passed. Final default whitespace check flags only the required empty-body header separators in the two exact canonical label fixtures; checking with `core.whitespace=-blank-at-eof` passes. The canonical fixture bytes are intentionally preserved.
- Browser verification: real signed author/operator/account and partial-failure/retry flows passed; source-owned `pinned` disappears, legacy unresolved pin remains with actionable review feedback, and physical root deletion retains an independent reply. Operator-review feedback passed all 13 themes at phone 390px and desktop 1280px without page/warning overflow. Keyboard/Escape/focus and touch menu checks passed. Inspected Word97/Chicago phone and Midnight desktop screenshots; local evidence in `/tmp/retraction-browser-evidence/`. No navigation destination or icon mapping changed.
- Broader-suite limitation: full PHP run stopped incomplete after **59 passes and two image-test failures**. Both failures were reproduced on unchanged `e960f666` in an isolated worktree: `ImageUploadValidatorTest::testDecodedContentsAndLimits` and `ImageContentTest::testBackupInventoriesAndRejectsMissingOrMismatchedPairs`; PHP GD is absent. The incomplete run is not a full-suite pass. The targeted tests above ran against final feature behavior. Non-label effect completion, production deployment, actual ZenMemes repair and maintainer acceptance are not claimed.

## Release and recovery

- New source-owned labels are handled automatically by normal retraction/deletion. Historical labels without trustworthy ownership require inspection and explicit local-operator resolution; the incident fixture verifies that recovery. Preserve trusted Git history and `records/post-removals/` on backup/restore; a files-only receipt is not authority.
- Use the same repository/database/artifact roots as the site. Retry the identical source deletion command after commit/refresh failure; changed source or label bytes require inspection. Refresh prebuilt static releases with the deployment's normal `./v3 build-static` invocation. Deploy readers/writers together; rollback must preserve accepted evidence and must not restore older readers that ignore removal evidence. Prefer a forward fix.
- No production server or remote messages were changed. The user subsequently requested pushing the finished branch to origin; push/remote-tip verification is the final handoff action, not deployment.
- All eight stages delivered in order with separate summary-bearing commits. Planning: `43ed5860`; stages 1–7: `56d4d932`, `d050c4e8`, `7ebcb8ff`, `b875ad2c`, `444fb69f`, `ac8a8331`, `38fb5774`. Stage 8 is the closeout commit containing this section; the subsequent debug-only handoff records its exact hash. Starting revision `e960f666e1146d26b2f9435d98a447c2594b9883`.

## Study outcome

2026-10-23 UTC; cycle `v3/retraction_effects`, approved reduced scope v5, R1/P2, Codex single exposed rater. Eight planned/delivered stages; stage-8 corrections above close discovered acceptance gaps rather than adding the deferred effect families. Prospective projections and retrospective numeric scope assessment remain **unavailable pending exact frozen-reference recovery**; no missing scores are reconstructed and no active-work measurement is inferred. Maintainer comfortable-fit/acceptance pending. This delivered label slice does not complete the original all-effects scope or count as an independent split-child outcome; parent remaining effects stay explicitly pending.

The earlier unpin artifact is absent in this checkout, so its broken Step 1 reference was changed to a plain historical mention; no predecessor scope was reconstructed. Its unrelated pending ledger row is preserved outside this cycle's closeout commit.
