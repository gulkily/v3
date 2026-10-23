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
