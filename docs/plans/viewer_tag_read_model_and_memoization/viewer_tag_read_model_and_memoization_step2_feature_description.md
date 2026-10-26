> **Feature plan:** [Step 1](./viewer_tag_read_model_and_memoization_step1_solution_assessment.md) · [Step 2](./viewer_tag_read_model_and_memoization_step2_feature_description.md) · [Step 3](./viewer_tag_read_model_and_memoization_step3_development_plan.md) · [Step 4](./viewer_tag_read_model_and_memoization_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/viewer_tag_read_model_and_memoization`; [ledger](../effort_estimation_trial.md), standing enrollment P2.

Extended estimates: **enabled** (inherited — both predecessor cycles ran with extended estimates on). Per-stage stage-work estimates begin at Step 3. Step 1 per-option estimates are retrospective (see Step 1). Option estimates are not summed or normalized into the recommended-cycle total.

**Cycle estimate: 1.15**

## Lineage and scope note

This **combined** cycle merges the two previously separate, B-then-A-sequenced cycles into one coherent delivery, at the user's request (2026-10-24), because the read model (B) and the memo over it (A) are tightly coupled — B exposes the SQL seam and A wraps it, and the `viewerHasThreadTag()` bypass must be cleaned up in the same pass.

- Supersedes the separate [`viewer_tag_read_model`](../viewer_tag_read_model/) (B) and [`viewer_tag_lookup_memoization`](../viewer_tag_lookup_memoization/) (A) cycles for execution. Their Step 1/Step 2 artifacts are retained as history and are not erased.
- **Approval reset:** combining is a scope change, so the individual `Approved Step 1` marks on B and A no longer carry. Step 1 for this combined cycle is the merge of the two existing (individually approved) Step 1 assessments; this Step 2 still requires a fresh **`Approved Step 2`** before Step 3.
- **Cycle-size guardrail:** combined projected total is **1.15** (B 0.9 + the memo layer 0.35, minus overlap in call-site rewiring and verification). This is **above the 1.0 "prompt a split discussion" threshold**. The user has explicitly chosen to combine despite that; the split option is the two original cycles, which remain available if Step 3 staging shows the combined scope is unwieldy. Recorded as a deliberate over-threshold decision, not an oversight.

## Problem

For a logged-in viewer, rendering a thread or board answers viewer-tag questions (like/flag on posts; like/upvote/downvote/labels on threads) by globbing and parsing the **entire** `records/post-reactions/` and `records/thread-labels/` directories — in `ViewerTagLookup::postTags()`/`threadTags()` and, bypassing those, in `ThreadAndPostPageController::viewerHasThreadTag()`. These scans run 5+ times per thread render (once per tag on qdb), so per-request cost scales with the whole site's reaction/label corpus rather than with the posts on the page.

## User stories

- As a logged-in reader opening a thread, I want my like/flag/vote state to display without re-reading every reaction on the site, so the page stays fast as the forum accumulates reactions.
- As a logged-in reader browsing the board (classic, Forte, or qdb), I want my reaction/label state shown without repeated full-directory scans, so navigation does not slow with corpus size.
- As an operator on a busy instance, I want per-request work to scale with the page, not the archive, and to scale with the read model (indexed) rather than the filesystem, so logged-in traffic stays responsive at scale.

## Core requirements

**Read-model projection (the B half):**
- `ReadModelBuilder` projects reaction/label records into new read-model table(s) keyed and indexed by `author_identity_id` (plus target id and tag), written during the same pass that already iterates `records/thread-labels` and `records/post-reactions`.
- `ViewerTagLookup::postTags()`/`threadTags()` answer via an indexed `WHERE author_identity_id = ? AND tag = ? AND <target_id> IN (…)` query instead of a filesystem glob+parse.
- The `thread-labels` scan in `ThreadAndPostPageController::viewerHasThreadTag()` is folded into the same `ViewerTagLookup` SQL path; no caller globs the records directories for viewer-tag state.
- This is a read-model **schema change** handled under the full `CONTRIBUTING.md` checklist: `SCHEMA_VERSION` bump (currently 26 after `read_model_hot_indexes`), spec update, prior-version rebuild regression test exercised through normal startup, and `./v3 rebuild` success.

**Request-scoped memo (the A half), layered on the SQL path:**
- Each per-(identity, record family) lookup result is memoized for the duration of a single request, so the multiple per-family queries per page collapse to at most one query per (family, identity) — including the qdb per-tag loop.
- The memo is request-scoped: it never persists across requests and never serves stale data within or between requests.

**Invariant across both halves:**
- Viewer reaction/label results are byte-for-byte identical to today's file-scan output for every caller and every case (viewer with reactions, no reactions, anonymous, empty/missing records).
- No canonical record-format change; reaction/label write paths are untouched.

## Delivery scope

- Work type: **application change + read-model schema change** (builder projection, new indexed table(s), schema version/spec, `ViewerTagLookup` rewire to SQL, request-scoped memo, and the one controller-helper bypass).
- Not documentation-only.

## Completion boundary

- Normal entry: a logged-in viewer opens a thread permalink or a board (classic / Forte / qdb) that shows their reaction or label state.
- End-to-end outcome: identical reaction/label state renders, answered by indexed per-identity read-model queries (no filesystem scans) and served from a single query per (family, identity) per request (no repeated queries).
- Recovery: (1) a viewer with no reactions, an anonymous viewer, or an empty/missing records directory behaves exactly as today — no tags, no errors; (2) a prior-version (pre-bump) read model is detected by the existing `schema_identity_mismatch` gate and rebuilt on normal startup before the new query runs; parse errors on individual records are skipped during projection as they are today.
- Release condition: results confirmed unchanged across all affected call sites; a per-request trace shows zero records-directory globs for viewer-tag state and at most one query per (family, identity); the schema-change checklist is complete; `./v3 rebuild` succeeds; affected focused tests pass.

## Known user or environment

No specific end user, machine, or environment is named in the request; omitted. (Sandbox PHP note carried from `read_model_hot_indexes`: the mise `php` shim drops `LD_LIBRARY_PATH`; run the real binary with inner-container libs. Environment-only, no repository change.)

## Risks

- **High risk:** Results drift / identity leak — a mis-keyed projection, query, or memo could show another identity's tags or miss some. Earliest validation: before/after result comparison against the current file-scan path across classic board, Forte, qdb, and single-thread for a viewer with known reactions, plus no-reaction and anonymous cases. Mitigation: project at the per-(identity, target, tag) grain; query with explicit `author_identity_id` + tag predicates; key the memo by repository root + record family + identity, mirroring current filter logic exactly.
- **High risk:** Rebuild/migration correctness — projecting reactions/labels is a new derived surface; a prior-version model must rebuild before the new query runs or lookups hit missing/empty tables. Earliest validation: prior-version rebuild regression test driven through `Application::handle()` normal startup (mirroring the corrected `read_model_hot_indexes` test), asserting `rebuild_reason = schema_identity_mismatch` and correct projected rows afterward.
- Stale memo within a request — a cached result reused after a reaction write in the same request could be wrong. Earliest validation: confirm these render paths are read-only (no reaction writes between lookups in one request). Mitigation: request-scoped cache only; no cross-request persistence.
- Missed call site — `viewerHasThreadTag()` scans independently today; if not folded in, a filesystem scan remains after the migration. Earliest validation: grep/trace all call sites (done in planning; the five sites are inventoried below). Mitigation: route every viewer-tag read through the SQL-backed, memoized `ViewerTagLookup`.
- Query shape / N+1 — a naive per-call query could reintroduce per-tag round-trips on qdb. Earliest validation: per-request query-count trace on a qdb thread render. Mitigation: batch target ids in one `IN (…)` query per (family, identity) and let the memo absorb repeats.

## Shared component inventory

- `ReadModel/ReadModelBuilder.php` — already iterates `records/thread-labels` (≈L360) and `records/post-reactions` (≈L558) during rebuild; the new projection writes rows in the same pass. Extended, not forked.
- `ReadModel/ReadModelSchema.php` + `ReadModel/ReadModelMetadata.php` — schema statements (new table(s) + indexes) and `SCHEMA_VERSION` bump (26 → next); the identity fingerprint changes accordingly, as in `read_model_hot_indexes`.
- `ReadModel/ViewerTagLookup.php` — canonical owner of bulk viewer lookups (`postTags`, `threadTags`); rewired from glob+parse to indexed SQL, and the request-scoped memo lives here so all callers benefit without new surfaces. Currently static and PDO-free — it will need the read-model PDO handle passed in; this signature change is tracked as a call-site touch below.
- `Http/BoardPageController.php` (`viewerReactionStateForThreads`) — classic board; `threadTags` (upvote/downvote) + `postTags` (flag). Reuses the rewired lookup; updated for the new signature.
- `Http/ForteBoardController.php` — Forte board; `threadTags`/`postTags` for like/flag. Reuses; signature update.
- `Qdb/QdbBoardPolicy.php` — qdb per-tag vote loop + flag; the per-tag loop is the main N+1 the memo collapses. Reuses; signature update.
- `Http/ThreadAndPostPageController.php` — single thread/post; `viewerPostTagsForPosts` (delegates to `postTags`) and the qdb per-tag `threadTags` loop; **and `viewerHasThreadTag()` (L280), which globs `thread-labels` directly** — redirected through `ViewerTagLookup`.
- `docs/specs/read_model_schema_v1.md` — schema spec: new table(s)/index notes and a `### … (schema version N)` changelog entry.
- No new user-facing component or API surface is introduced.

## Simple user flow

1. A logged-in viewer opens a thread or board page that shows reaction/label state.
2. `ViewerTagLookup` issues one indexed query per (record family, identity) — `WHERE author_identity_id = ? AND tag = ? AND target IN (…)` — against the read-model table(s) the last rebuild projected.
3. The result is memoized for the request; every subsequent lookup in the same request (other tags, other call sites, qdb per-tag loop) reads the memo instead of re-querying.
4. The page renders the same like/flag/vote/label state as before — no records-directory globbing at any point.

## Success criteria

- A per-request trace on a logged-in qdb thread render shows **zero** `records/post-reactions` / `records/thread-labels` globs for viewer-tag state (versus 5+ today) and **at most one query per (family, identity)** (versus one per tag).
- Reaction/label state is byte-identical to pre-change output on classic board, Forte, qdb, and single-thread for a viewer with known reactions, and for the no-reaction and anonymous cases.
- No caller scans a records directory for viewer-tag state, including `viewerHasThreadTag`.
- Schema-change checklist complete: version bump, spec updated, prior-version rebuild regression test added and exercised through normal startup, `./v3 rebuild` succeeds.
- Affected focused tests pass (read-model schema/metadata/rebuild + board/thread/qdb viewer-state tests).

## Effort snapshot and approval

**Cycle estimate: 1.15** — Step 2 handoff.

- Cycle `v3/viewer_tag_read_model_and_memoization`, scope v1 (combined); anticipated starting revision: current `main` at this cycle's branch time; reference R1 / protocol P2; single exposed, non-blind rater; UTC 2026-10-24.
- Intake: combined from the two predecessor cycles after reading `ViewerTagLookup.php`, the five call sites, and the `ReadModelBuilder` projection seams; prior exposure to both predecessor plans disclosed. No pristine pre-inspection estimate claimed.
- Step 2 handoff projected total (combined scope): **1.15** — read-model schema change + builder projection/indexing + `ViewerTagLookup` SQL rewrite + request-scoped memo + five call-site signature touches + the full schema-change checklist, net of shared verification between the two halves. Independently judged, **not** the sum of predecessor cycle totals (B 0.9, A memo 0.35) and not the sum of any option estimates. Above the 1.0 split-discussion threshold by deliberate user choice; see the scope note.
- Preserved predecessor snapshots (history, not re-rated): see the table below.
- Per-stage stage-work estimates begin at Step 3.
- Documentation boundary: this cycle's plans/debug log and ledger bookkeeping; predecessor artifacts retained unchanged. Planning writes remain uncommitted.
- Await **`Approved Step 2`** before Step 3; requirements may be revised without advancing. If Step 3 staging shows the combined 1.15 scope is unwieldy, raise a split back into the two original cycles before advancing.

### Historical estimates — unchanged

These are predecessor estimates, not additional estimates for the combined cycle.

| Predecessor item | Estimate | Context |
| --- | ---: | --- |
| Read model (B), recommended cycle | 0.9 | Preserved snapshot |
| Read model (B), option A | 0.9 | Preserved option estimate |
| Read model (B), option B | 0.8 | Preserved option estimate |
| Read model (B), option C | 0.35 | Preserved option estimate |
| Memoization (A), recommended cycle, scope v2 | 0.35 | Current predecessor scope |
| Memoization (A), recommended cycle, scope v1 | 0.45 | Earlier scope retained |
| Memoization (A), option A | 0.45 | Retrospective option estimate |
| Memoization (A), option B | 0.9 | Retrospective option estimate |
| Memoization (A), option C | 0.25 | Retrospective option estimate |

### Presentation revision — 2026-10-24

User requested a preview with clearly separated estimate values. Added standalone estimate lines and separated supporting detail; numbers, scope, uncertainty and approval state are unchanged. This is a formatting revision, not a new rating or approval. Railspike instruction changes are deferred until the user reviews this preview.
