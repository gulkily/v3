> **Feature plan:** [Step 1](./read_model_schema_version_integrity_step1_solution_assessment.md) · [Step 2](./read_model_schema_version_integrity_step2_feature_description.md) · [Step 3](./read_model_schema_version_integrity_step3_development_plan.md) · [Step 4](./read_model_schema_version_integrity_step4_implementation_summary.md)

## Original Query

This has not been the case in the past. We have a schema version setting just for this. Please dig deeper, and write a Step 1 of @docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md to resolve this potential issue for good.

## Understood Intent

- You're right — there is an automated self-healing system, and I missed it in my first answer. `Application::ensureReadModel()` runs at the top of **every request** and compares the live read-model database's stored `schema_version`/`repository_root`/`repository_head` metadata against the running code's expectations; on any mismatch it rebuilds automatically under an exclusive lock, with zero manual action needed. In the past, this has worked exactly as intended.
- `repository_head` tracks the **content** repository (canonical post records), not this application's own source code — so it only ever reacts to new canonical records, by design, never to a PHP-level schema change on its own.
- That leaves `ReadModelMetadata::SCHEMA_VERSION` (`src/ForumRewrite/ReadModel/ReadModelMetadata.php:11`) as the **only** signal meant to catch "the code's expected read-model shape changed." It's a hand-maintained string literal (`'14'`) with no automated link to the actual `CREATE TABLE` statements in `ReadModelBuilder::createSchema()`. This session's events-experience feature added three columns to `threads` and never bumped it — the string matched on both sides, `ensureReadModel()` legitimately found nothing to repair, and the request 503'd on a raw "no such column" error instead.
- This isn't the first time this exact class of mistake has surfaced: `read_model_failure_experience` (`docs/plans/read_model_failure_experience_step1_solution_assessment.md`) was built specifically to give a missing-`vote_count`-column failure a friendly recovery page instead of a raw error — treating a symptom of the same root cause (a forgotten version bump) rather than the root cause itself.
- This Step 1 is about the general fragility, not the one rebuild I already ran manually — I'm proposing to make the version-bump step impossible to forget, not to add another place to remember it.

## Problem Statement

`ReadModelMetadata::SCHEMA_VERSION` is a hand-maintained constant with no automated connection to the actual schema it's meant to describe, so the existing per-request self-healing rebuild silently does nothing whenever a developer changes the read-model schema without remembering to bump it.

## Solution Options

- **Option A: Document the convention.** Add a docblock next to `createSchema()` and the `SCHEMA_VERSION` constant reminding whoever edits one to bump the other.
  - Pros: trivial, zero risk, no behavior change.
  - Cons: still depends entirely on a human noticing and remembering — the exact failure mode that just happened; doesn't "resolve for good."
- **Option B: Add an automated guard, keep the manual constant.** Add a test that computes a fingerprint of the current schema and fails if it doesn't match a fixture tied to today's `SCHEMA_VERSION`, forcing a developer to update both together before a change can land.
  - Pros: catches a forgotten bump before merge, cheap, no runtime behavior change, no risk to the live self-healing mechanism as it exists today.
  - Cons: just moves the forgettable step earlier (now there are two places to keep in sync — the constant and the fixture — instead of one); a developer under time pressure can "fix" the failing test by updating the fixture without actually bumping the meaningful version.
- **Option C (recommended): Derive `schema_version` automatically from the schema itself.** Replace the hand-maintained string with a value computed from `ReadModelBuilder::createSchema()`'s own table/column definitions (e.g., a short hash), used as a drop-in replacement everywhere `schema_version` is written or compared today (`Application::ensureReadModel()`, `ReadModelBuilder`, `ReadModelCandidateBuilder`, `IncrementalReadModelUpdater`, `LocalWriteService`, `CodebaseStateController`, `OperatorStatusCollector`). Any schema edit automatically produces a different value — there is no bump to forget.
  - Pros: removes the human-memory dependency at its root, not just adds a second place to fail; every existing consumer keeps the same shape (an opaque string stored in `metadata` and compared for equality), so the change is mechanical and low-risk; makes this entire class of incident structurally impossible going forward.
  - Cons: the version becomes an opaque hash instead of a friendly increasing number on the one operator diagnostics page that displays it (`CodebaseStateController`) — minor, and that page already shows raw git hashes right next to it; a purely cosmetic SQL-formatting edit to `createSchema()` would also change the hash and trigger a harmless-but-unnecessary rebuild, a low-cost false positive compared to today's higher-cost false negative.

## Recommendation

**Option C**, optionally layered with a small piece of Option B (a sanity test confirming the computed fingerprint actually changes when the schema does) as cheap insurance on the hashing mechanism itself — not as the primary defense. "Resolve for good" means removing the possibility of forgetting, not giving the same mistake a second chance to happen somewhere else.

**Vertical-slice viability:** Yes. Entry is any future code change to the read-model schema (in this repo or any environment running it); outcome is the very next request to that environment automatically detecting the drift and rebuilding, with no developer action, no manual script run, and no raw error — closing exactly the gap this session's events-experience feature fell into.

Waiting for "Approved Step 1" before drafting Step 2.
