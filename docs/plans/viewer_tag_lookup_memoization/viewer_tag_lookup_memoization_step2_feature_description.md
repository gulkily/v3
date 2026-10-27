> **Feature plan:** [Step 1](./viewer_tag_lookup_memoization_step1_solution_assessment.md) · [Step 2](./viewer_tag_lookup_memoization_step2_feature_description.md) · [Step 3](./viewer_tag_lookup_memoization_step3_development_plan.md) · [Step 4](./viewer_tag_lookup_memoization_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/viewer_tag_lookup_memoization`; [ledger](../effort_estimation_trial.md), standing enrollment P2.

Extended estimates: enabled (late activation 2026-10-24). Per-stage stage-work estimates begin at Step 3; Step 1 per-option estimates are retrospective (see Step 1).

## Problem

For a logged-in viewer, rendering a thread or board scans and parses the entire `records/post-reactions/` and `records/thread-labels/` directories several times per request (including once per tag on qdb), so request cost scales with the whole site's reaction/label corpus instead of the posts on the page.

## User stories

- As a logged-in reader opening a thread, I want my like/flag/vote state to display without re-reading every reaction on the site, so the page stays fast as the forum accumulates reactions.
- As a logged-in reader browsing the board (classic, Forte, or qdb), I want my reaction/label state shown without repeated full-directory scans, so navigation does not slow down with corpus size.
- As an operator on a busy instance, I want per-request work to scale with the page, not the archive, so logged-in traffic stays responsive.

## Core requirements

- Each canonical reaction/label directory is read and parsed at most once per request, regardless of how many tags, posts, threads, or call sites need it.
- Viewer reaction/label results (like/flag on posts; like/upvote/downvote/labels on threads) are byte-for-byte the same as today for every caller.
- Fold the independent `thread-labels` scan in `ThreadAndPostPageController::viewerHasThreadTag()` into the same single-parse path so no caller bypasses the memo.
- The memo is request-scoped: it never persists across requests and never serves stale data within or between requests.
- No read-model schema change and no canonical record-format change.

## Delivery scope

- Work type: **application change** (read-path memoization in `ReadModel/ViewerTagLookup.php` and the one controller helper that bypasses it).
- Not documentation-only.

## Completion boundary

- Normal entry: a logged-in viewer opens a thread permalink or a board (classic / Forte / qdb) that shows their reaction or label state.
- End-to-end outcome: identical reaction/label state renders, produced by a single parse of each relevant records directory per request instead of repeated full scans.
- Recovery: a viewer with no reactions, an anonymous viewer, or an empty/missing records directory behaves exactly as today (no tags shown, no errors). Parse errors on individual records are skipped as they are now.
- Release condition: results confirmed unchanged across the affected call sites, a per-request scan-count check shows one parse per family, and affected focused tests pass.

## Known user or environment

No specific end user, machine, or environment is named in the request; this item is omitted.

## Risks

- **High risk:** Results drift — a mis-keyed or over-shared memo could leak another identity's tags or miss some. Earliest validation: before/after result comparison across classic board, Forte, qdb, and single-thread for a viewer with known reactions. Mitigation: key the memo by repository root + record family and filter per identity/tag in memory, mirroring current filter logic exactly.
- Stale memo within a request — a cached parse could miss records if reused after a write in the same request. Earliest validation: confirm these render paths are read-only (no reaction writes between lookups in one request). Mitigation: request-scoped cache only; no cross-request persistence.
- Missed call site — `viewerHasThreadTag()` scans independently today; if not folded in, a scan remains. Earliest validation: grep/trace all call sites (done in planning). Mitigation: route every viewer-tag read through the memoized path.

## Shared component inventory

- `ReadModel/ViewerTagLookup.php` — canonical owner of bulk viewer reaction/label lookups (`postTags`, `threadTags`); the memo lives here so all callers benefit without new surfaces. Extended, not forked.
- `Http/BoardPageController.php` (`viewerReactionStateForThreads`) — classic board; calls `threadTags` (upvote, downvote) + `postTags` (flag). Reuses the memoized lookup unchanged in shape.
- `Http/ForteBoardController.php` — Forte board; `threadTags`/`postTags` for like/flag. Reuses.
- `Qdb/QdbBoardPolicy.php` — qdb per-tag vote loop + flag. Reuses.
- `Http/ThreadAndPostPageController.php` — single thread/post; `viewerPostTagsForPosts` (delegates to `postTags`) and the qdb per-tag `threadTags` loop. Also `viewerHasThreadTag()`, which currently globs `thread-labels` directly — to be redirected through `ViewerTagLookup`.
- No new component or API surface is introduced.

## Simple user flow

1. A logged-in viewer opens a thread or board page that shows reaction/label state.
2. The first viewer-tag lookup for a record family parses that directory once and indexes it by author identity.
3. Every subsequent lookup in the same request (other tags, other call sites) reads the in-memory index instead of re-scanning.
4. The page renders the same like/flag/vote/label state as before.

## Success criteria

- A per-request trace shows each of `post-reactions` and `thread-labels` parsed at most once, versus the current 5+ scans on a logged-in qdb thread render.
- Reaction/label state is identical to pre-change output on classic board, Forte, qdb, and single-thread for a viewer with known reactions, and for the no-reaction and anonymous cases.
- No records directory is scanned by any caller outside the memoized path (including `viewerHasThreadTag`).
- Affected focused tests pass; no read-model or record-format change.

## Effort snapshot and approval

- Cycle `v3/viewer_tag_lookup_memoization`, scope v1; starting revision `611bcbb5` (current `main`); reference R1 / protocol P2; single exposed, non-blind rater; UTC 2026-10-24.
- Step 2 handoff projected total: **≈0.45 reaffirmed** (unchanged from Step 1). Scope unchanged — request-scoped memoization across the existing call sites plus folding in the one bypassing scan; no new requirements surfaced. Not measured effort, an approval, or a completed outcome.
- Documentation boundary includes this cycle's plans/debug log and ledger bookkeeping. Planning writes remain uncommitted.
- Await `Approved Step 2` before Step 3; requirements may be revised without advancing.
