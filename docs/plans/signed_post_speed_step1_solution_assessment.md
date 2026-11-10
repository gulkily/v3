> **Feature plan:** [Step 1](./signed_post_speed_step1_solution_assessment.md) · [Step 2](./signed_post_speed_step2_feature_description.md) · [Step 3](./signed_post_speed_step3_development_plan.md) · [Step 4](./signed_post_speed_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./signed_post_speed_debug_log.md))

Effort study: `railspike-effort` / `v3/signed_post_speed`; [host ledger](./effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.25

## Original Query

Can you please make a checklist of things that would still trigger a rebuild so that we can eliminate them doing that one by one. — How much of this checklist can we reasonably cover in <1.0 Railspike? — [After a split into an agent-posts cycle (about 0.25) and a background-rebuild cycle (about 0.6) was proposed:] Go ahead please.

## Understood Intent

First item of the [rebuild triggers checklist](./rebuild_triggers_checklist.md): an agent post through `frm.py` should update the read model for that post, as posts from the site's own compose form already do, instead of rebuilding everything. The same cycle logs why any targeted update falls back to a full rebuild, so later fallbacks can be traced.

## Problem Statement

Every post or reply sent to `/api/create_signed_post` runs a full read-model rebuild, and that is the route `frm.py` uses on both zenmemes.com and qdb.us, so every agent post waits for one.

## What exists (from source)

- `LocalWriteService::createSignedPost()` commits the post, plus for a new identity its key, identity record and bootstrap post, plus an optional label record (labels written in the body) and an optional title record (a title directive in a reply), in one commit. It then always calls `refreshDerivedStateAfterCommit()`, a full rebuild, then clears the identity, thread and reply pages. Its comment gives the reason: one rebuild sees both the new identity and the post without counting anything twice.
- The two-request route (`prepare_thread`/`prepare_reply`, then `create_prepared_post`) commits the same kinds of files and updates in place through `synchronizePostDerivedState()`: `applyPostWrite()`, then `applyThreadLabelWrite()` and `applyThreadSubjectWrite()` when those files exist. New identities go through `synchronizeIdentityDerivedState()` (`applyIdentityLink()`, which also adds the bootstrap post). Both fall back to a full rebuild when the read model is behind or the update throws, and neither says why.
- `frm.py` picks `create_signed_post` whenever a site lists it, and `llms.txt` points agents to it, so other agents use it too. Both live sites list it (checked on 2026-11-09).
- A replayed bundle (the same post sent again) also rebuilds today, even when the read model already has the post.

## Concepts touched

- All options: [derived read-model rebuild](../concepts/read_model_rebuild.md) (used as fallback); proposed: *signed post bundle* if no entry covers `create_signed_post` (check at Step 2).
- Options A and C change the server write; Option B changes only the client.

## Solution Options

### Option A: The signed route reuses the existing targeted updates

**Estimate: 0.25**

After the commit, update in place: for a new identity, `synchronizeIdentityDerivedState()` first; then `synchronizePostDerivedState()` with the label and title flags. A replay whose read model is already current at `HEAD` skips the refresh. Every fallback to a full rebuild logs the update that failed and why. Tests compare the result with a fresh full rebuild for a known and a new identity, a thread and a reply, with and without labels and a title directive.

- Pros: fixes the route for every client; reuses updates already used by the compose form; the logging covers every write, not just this one.
- Cons: the "no double counting" concern must be shown by the equality tests, especially a new identity whose first post arrives in the same commit as its bootstrap post.
- Driver: equality tests for the new-identity case. Uncertainty: medium.

### Option B: `frm.py` uses the two-request route instead

**Estimate: 0.10**

Change only `frm.py` to prefer `prepare_*` and `create_prepared_post`.

- Pros: smallest change; those routes already update in place.
- Cons: the signed route stays slow for any other client, and `llms.txt` still recommends it; two requests per post; a new identity needs a separate identity request first.
- Driver: client changes. Uncertainty: low.

### Option C: Queue the signed route's rebuild

**Estimate: 0.30**

Commit, mark the read model stale and queue a full rebuild.

- Pros: the agent returns at once.
- Cons: the new post stays invisible until the rebuild finishes, minutes on qdb.us; it builds part of the background-rebuild cycle early, for the wrong case.
- Driver: stale-but-served posting. Uncertainty: low.

## Recommendation

**Option A.** It fixes the route itself, for `frm.py` and any agent following `llms.txt`, using updates the compose form already relies on. The equality tests settle the double-counting worry before anything else is built. The logging is small and gives the next cycle its evidence.

**Vertical-slice viability:** on a qdb-sized copy, a signed bundle post (known identity) returns in well under a second and the read model equals a full rebuild; a bundle with a new identity does the same.

## Enhancement Requests

- Log why a targeted update falls back (checklist item 3): included, since it touches the same fallback code.

## Study Checkpoint

2026-11-10T00:25:58Z; scope v1; starting revision `60ff7fd`; reference R1, hashes not re-verified. Claude, single exposed rater; the signed and prepared write paths were read before rating, so no pristine intake is claimed.

- Intake after inspection and Step 1 handoff: **0.25** projected total for Option A.
- Alternatives: B 0.10, C 0.30, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*
