> **Feature plan:** [Step 1](./read_model_schema_version_integrity_step1_solution_assessment.md) · [Step 2](./read_model_schema_version_integrity_step2_feature_description.md) · [Step 3](./read_model_schema_version_integrity_step3_development_plan.md) · [Step 4](./read_model_schema_version_integrity_step4_implementation_summary.md)

## Original Query

This has not been the case in the past. We have a schema version setting just for this. Please dig deeper, and write a Step 1 of @docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md to resolve this potential issue for good.

## Understood Intent

- You're right — there is an automated self-healing system, and I missed it in my first answer. `Application::ensureReadModel()` runs at the top of **every request** and compares the live read-model database's stored `schema_version`/`repository_root`/`repository_head` metadata against the running code's expectations; on any mismatch it rebuilds automatically under an exclusive lock, with zero manual action needed. In the past, this has worked exactly as intended.
- `repository_head` tracks the **content** repository (canonical post records), not this application's own source code — so it only ever reacts to new canonical records, by design, never to a PHP-level schema change on its own.
- That leaves `ReadModelMetadata::SCHEMA_VERSION` (`src/ForumRewrite/ReadModel/ReadModelMetadata.php:11`) as the **only** signal meant to catch "the code's expected read-model shape changed." It is a hand-maintained string literal (`'14'`) with no automated link to the actual `CREATE TABLE` statements in `ReadModelBuilder::createSchema()`. This session's events-experience feature added three columns to `threads` and never bumped it — the string matched on both sides, `ensureReadModel()` legitimately found nothing to repair, and the request 503'd on a raw "no such column" error instead.
- This isn't the first time this exact class of mistake has surfaced: `read_model_failure_experience` (`docs/plans/read_model_failure_experience_step1_solution_assessment.md`) was built specifically to give a missing-`vote_count`-column failure a friendly recovery page instead of a raw error — treating a symptom of the same root cause (a forgotten version bump) rather than the root cause itself.
- This Step 1 is about the general fragility, not the one rebuild I already ran manually. It should preserve the meaningful, human-readable schema generation number while making a forgotten generation bump safe rather than an outage.

## Problem Statement

`ReadModelMetadata::SCHEMA_VERSION` is a hand-maintained generation number with no automated connection to the actual schema it describes. The existing per-request self-healing rebuild therefore silently does nothing whenever a developer changes the read-model schema without remembering to bump it.

## Solution Options

- **Option A: Document the convention.** Add a docblock next to `createSchema()` and the `SCHEMA_VERSION` constant reminding whoever edits one to bump the other.
  - Pros: trivial, zero risk, no behavior change.
  - Cons: still depends entirely on a human noticing and remembering — the exact failure mode that just happened; doesn't "resolve for good."
- **Option B: Add an automated guard, keep the manual constant.** Add a test that computes a fingerprint of the current schema and fails if it doesn't match a fixture tied to today's `SCHEMA_VERSION`, forcing a developer to update both together before a change can land.
  - Pros: catches a forgotten bump before merge, cheap, no runtime behavior change, no risk to the live self-healing mechanism as it exists today.
  - Cons: just moves the forgettable step earlier (now there are two places to keep in sync — the constant and the fixture — instead of one); a developer under time pressure can "fix" the failing test by updating the fixture without actually bumping the meaningful version.
- **Option C (recommended): Keep the readable version and add a derived schema fingerprint.** Retain `SCHEMA_VERSION` as the intentionally increasing, human-facing generation number. Define the read-model DDL once in a canonical schema-definition method or data structure, execute that definition when creating the database, and derive `schema_fingerprint` from it. Write and compare both `schema_version` and `schema_fingerprint` everywhere metadata is validated today (`Application::ensureReadModel()`, `ReadModelBuilder`, `ReadModelCandidateBuilder`, `IncrementalReadModelUpdater`, `LocalWriteService`, `CodebaseStateController`, `OperatorStatusCollector`). A schema edit automatically changes the fingerprint even when someone forgets to increase the generation number.
  - Pros: preserves useful operator language such as “schema version 14” while making the automatic fingerprint the safety-critical drift detector; the canonical definition avoids fingerprinting PHP or SQL formatting and gives schema creation and fingerprinting one source of truth; an absent fingerprint in an existing database safely produces a one-time rebuild.
  - Cons: adds one metadata field and comparison instead of replacing one string; a developer can still forget to increase the human-facing version, though that is now a release-discipline/documentation lapse rather than a stale-schema outage; schema-shape detection does not itself cover rebuilds required solely by changed indexing or derivation semantics, which need their own explicit rebuild signal if applicable.

## Recommendation

**Option C**, optionally layered with a small piece of Option B: a test showing that a representative DDL change changes the fingerprint and that old metadata without a fingerprint is stale. The read model's identity becomes a pair: the readable `schema_version` for communication and intentional release generations, plus the derived `schema_fingerprint` for integrity. Readiness requires both to match. "Resolve for good" means a forgotten version increment cannot leave a database with a changed schema treated as current.

**Vertical-slice viability:** Yes. Entry is any future code change to the read-model DDL (in this repo or any environment running it); outcome is the very next request to that environment automatically detecting the fingerprint drift and rebuilding, with no developer action, no manual script run, and no raw error — closing exactly the gap this session's events-experience feature fell into.

Waiting for "Approved Step 1" before drafting Step 2.
