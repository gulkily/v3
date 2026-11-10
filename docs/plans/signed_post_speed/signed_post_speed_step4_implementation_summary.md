> **Feature plan:** [Step 1](./signed_post_speed_step1_solution_assessment.md) · [Step 2](./signed_post_speed_step2_feature_description.md) · [Step 3](./signed_post_speed_step3_development_plan.md) · [Step 4](./signed_post_speed_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./signed_post_speed_debug_log.md))

Effort study: `railspike-effort` / `v3/signed_post_speed`; [host ledger](../effort_estimation_trial.md#cycle-v3signed_post_speed). Standing P2 enrollment.

Extended estimates: enabled

Status: complete; merged as #134 and checked live on zenmemes.com.

**Cycle estimate: 0.25** (Step 3, reaffirmed at entry)

## Step 4 Entry

- Branch `feat/signed-post-speed` from `origin/main` at `a651718` (`main` has moved past the Step 1 starting revision `60ff7fd`; nothing in between touches the signed route). Planning commit `282c480` holds approved Steps 1–3 and the debug log in this folder, links repaired, plus the plan index entry.
- The Step 1–3 drafts were earlier pushed to `plans/signed_post_speed` at the session stop hook's request; this branch supersedes it.
- Whole-cycle **0.25** reaffirmed, 2026-11-10T07:09:37Z. Stage originals copied from Step 3: 0.04/0.15/0.06, non-additive. Claude, single exposed rater; copying is not a new rating.

## Stage 1 - Fallbacks to a full rebuild log why

**Original estimate: 0.04**
**Retrospective: 0.04**

- Changes:
  - `LocalWriteService::logRebuildFallback()` writes one `error_log()` line: `Read model: <update> update fell back to a full rebuild: <reason>`. `readModelNotCurrentReason()` names why the read model could not take a targeted update (no read model, stale marker, schema or root differs, metadata unreadable).
  - Called from the not-current branch and the error branch of all six `synchronize*DerivedState()` methods (post, thread label, thread title, post reaction, identity link, approval) and from the retraction fallback (cycle 2).
  - The line names the update rather than the write that called it (the plan said both); each update maps to one kind of write, and passing the write name through six methods added nothing.
  - New test `WriteApiSmokeTest::testFallbacksToAFullRebuildAreLogged`: a write with a stale marker and a write whose update throws each log exactly one line with the reason.
- Verification: `./v3 test --smoke WriteApiSmokeTest SingleRequestPostingTest`: 662 run, 662 passed. Full suite not run.
- Notes: fallback lines now also appear in test runs, where many tests deliberately force fallbacks.
- Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*

## Stage 2 - Signed posts update in place

**Original estimate: 0.15**
**Retrospective: 0.13**

- Changes:
  - `createSignedPost()` calls the new `refreshAfterSignedPost()` instead of a full rebuild. For a new identity it runs `synchronizeIdentityDerivedState()` first (its link adds the bootstrap post); then `synchronizePostDerivedState()` with flags for the label and title records actually written. If the identity step itself fell back to a full rebuild, that rebuild already contains the post, so the post step is skipped instead of adding the post twice.
  - A replay changes nothing when the read model is current at `HEAD` and has the post (`RemovalUpdateContext::forPost()`); otherwise it rebuilds as before.
  - The response gains `read_model_update`: `targeted`, `rebuilt` or `current`. `refresh_pending`, page clearing and the rest of the response are unchanged.
  - Bug found and fixed: `SignedPostTransaction` refused `records/thread-subjects/` paths, so on `main` a signed reply containing a `title:` line failed with "Unsafe signed post path". It is now allowed, like the two-request route.
  - New tests in `SingleRequestPostingTest`: `testSignedPostsMatchAFullRebuild` (a thread and a reply from a new identity and from a known one, a thread with a label in its body, a reply with a title directive; each updated in place and equal to a fresh full rebuild, and the label and title records confirmed written) and `testReplayWithACurrentReadModelDoesNotRefresh`.
  - `testReplyReusesIdentityAndRefreshFailureIsAccepted` injects a failure into the full rebuild; it now marks the read model stale first so the write takes that path.
- Verification:
  - `./v3 test --smoke WriteApiSmokeTest AgentPostingApiTest FrmClientTest LlmsTxtRendererTest SignedPostBundleValidatorTest SignedPostIdentityTest SignedPostTransactionTest SingleRequestHttpTest SingleRequestPostingTest`: 687 run, 687 passed. Full suite not run.
  - Order check: running the post update before the identity link makes the post update fail (no profile yet) and fall back, which the equality test catches.
- Notes: the double-counting worry from the code comment did not materialize in any of the six cases.
- Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*

## Stage 3 - Timing, docs and the live check

**Original estimate: 0.06**
**Retrospective: 0.06**

- Changes: new concept entry [signed post bundle](../../concepts/signed_post_bundle.md); the [rebuild triggers checklist](../rebuild_triggers_checklist.md) ticks agent posts and logged fallbacks, and adds one item found here (linking a new identity reads every post).
- Verification:
  - Timing on the 30,000-quote local copy (61 MB read model), through `createSignedPost()` with real signatures: known-key reply 0.46s, replay 0.18s, new-key thread about 1.0s (three runs: 1.03s, 1.01s; the first run of the session took 3.5s, and one run took 9.3s because a timing script of mine had left a stale marker, which correctly forced a full rebuild). A full rebuild on the same copy takes about 7s. Target under 1 second: met for known keys; a new key is at about 1 second, because `applyIdentityLink()` reads all 30,000 posts.
  - Smoke set: 442 run, 442 passed. Full suite not run.
  - Live check on zenmemes.com, after #134 merged (`f5f02c5`) and was deployed: "claude" posted [a thread and a reply](https://zenmemes.com/threads/thread-20261110074443-42f7275b902e1cbaa4a2cd61ce285a5c) through `frm.py` 2026-11-10. Both were accepted and appeared at once (`Reply-Count: 1` right after the reply). End to end from the workspace: 2.6s for the thread, 3.1s for the reply, including signing and at least two requests at about 0.4s each from there, so roughly a second of server time per post. `frm.py` does not print `read_model_update`, so the in-place path is inferred from timing, not read from the response.
- Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*

## Closeout

**Cycle estimate at entry: 0.25**
**Retrospective scope assessment: 0.24**

- Technical status: all stages complete; merged as #134 (`f5f02c5`) and checked live on zenmemes.com.
- Commits: planning `282c480`; stages `8468e20` (1), `3bb410b` (2), `467790c` (3).
- Checks: smoke set 442/442; write and signed-post test classes 687/687; equality with a full rebuild for six signed-post cases, with an order check. Full suite not run.
- Found on the way: signed replies with a `title:` line were refused on `main`; fixed in Stage 2.
- Stage retrospectives 0.04/0.13/0.06, non-additive. Maintainer comfortable-fit judgment and study acceptance: pending.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*
