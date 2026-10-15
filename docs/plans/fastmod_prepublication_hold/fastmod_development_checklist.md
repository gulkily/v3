# Fastmod and QDB Engagement — Development Checklist

Cross-cycle tracker for the approved graded-content-visibility roadmap and related requests. Checked items record actual decisions or completed work, not planned outcomes. Each application slice needs its own Railspike approvals; adding an item here does not authorize implementation or revise an approved plan.

## Current status

- [x] Assess Fastmod hold approaches and approve Step 1.
- [x] Define and approve graded visibility: neutral unapproved authors → approved viewers; negative → operator-level approved viewers; positive → public. Approved authors bypass restrictions.
- [x] Confirm only operators can approve negative-standing authors' posts; ordinary approved users can approve posts they are authorized to review.
- [x] Approve a three-slice delivery split.
- [x] Approve an independent default-off visibility feature flag: off restores current visibility, including held posts; on reapplies restrictions; scores, approvals and audit history persist. Existing instance access rules remain in force.
- [x] Approve the initial slice-1 Step 3 plan.
- [x] Create `feature/fastmod-neutral-intake` and commit organized plans (`f6e33768`).
- [x] Complete and commit slice-1 Stage 1 investigation (`8974e4e0`).
- [ ] Resolve the failed readiness gate: remaining slice-1 work is estimated at 14–21 hour-sized units; revise the budget/plan or narrow the approved scope.
- [ ] Resolve canonical Git/public-artifact boundaries for flag-off → flag-on transitions. Local visibility changes cannot recall distributed history or downloaded copies.
- [ ] Implement application changes. **None have been made for this roadmap.**

Evidence and current artifacts: [Step 1](./fastmod_prepublication_hold_step1_solution_assessment.md), [Step 2](./fastmod_prepublication_hold_step2_feature_description.md), [Step 3](./fastmod_prepublication_hold_step3_development_plan.md), [Step 4 investigation summary](./fastmod_prepublication_hold_step4_implementation_summary.md).

## Slice 1 — Neutral intake and human release

- [ ] Approve a feasible revised stage breakdown before proceeding beyond Stage 1.
- [ ] Settle author self-view, imported-content coverage and explicit human-post-approval precedence.
- [ ] Receive normal/signed local unapproved threads and replies without exposing them beyond the authorized audience.
- [ ] Show neutral contributions in approved users' ordinary reading views; provide author receipt/status.
- [ ] Support authenticated, auditable individual post approval without approving the author; preserve signed bytes.
- [ ] Preserve approved-author public behavior and define account-approval transitions for existing held content.
- [ ] Cover source/history, images, APIs, aggregates, exports, static output and offline artifacts.
- [ ] Verify flag off → on → off → on with existing/new content, retries, concurrent changes, restart and rebuild.
- [ ] Complete local verification and external hosting checks; close the Railspike cycle with stage commits and summary.

## Slice 2 — Negative standing and operator review

- [ ] Create and approve this slice's own Railspike artifacts; retain the overall Step 2 policy.
- [ ] Add operator-only negative-standing content and operator-only post approval using canonical operator authority.
- [ ] Support auditable manual standing changes and retroactive visibility changes without an age cutoff.
- [ ] Cover all reading/distribution surfaces, approved-author transitions, human overrides and recovery.
- [ ] Verify the viewer matrix and flag transitions; close the cycle. Automatic Fastmod decisions remain off in this slice.

## Slice 3 — Calibrated automatic standing and release

- [ ] Create and approve this slice's own Railspike artifacts.
- [ ] Define a human-labeled calibration set and measurable acceptance limits.
- [ ] Define author-history aggregation, neutral band, positive/negative triggers and release criteria. Author standing is separate from the existing 0–1 hold probability.
- [ ] Score private submissions and apply validated standing transitions to earlier and later content; no timeout-based widening.
- [ ] Keep failed, missing, excluded and stale results from granting wider access; support audit, retry and rollback.
- [ ] Verify full audience/flag behavior and measured calibration before enabling automation; close the cycle.

## New request — QDB fresh-identity voting engagement

**Requested outcome:** a new QDB user with a fresh identity, possibly auto-created, sees an item's displayed score increase on upvote or decrease on downvote. Immediate, meaningful feedback should encourage engagement. Investigate accepting vote contributions according to a scheme similar to post visibility. Identity auto-creation and the precise weighting policy are proposals, not approved implementation scope.

- [x] Capture the request in this cross-cycle tracker.
- [ ] Run a separate Step 1 brainstorm/assessment, reconciling the existing [anonymous reaction scoring assessment](../anonymous_reaction_scoring/anonymous_reaction_scoring_step1_solution_assessment.md) and [guest identity/proof-of-work feature note](../guest_identity_proof_of_work_voting.md).
- [ ] Choose what the immediate score change represents: a public accepted contribution, an audience-specific aggregate, or the voter's own provisional contribution.
- [ ] Define fresh/neutral, positive, negative and approved voter treatment; distinguish identity approval, content standing, vote eligibility and numerical weight. Do not automatically give a fresh identity account approval.
- [ ] Define both score numerator and vote-count denominator consistently, including imported seed totals, vote direction changes, duplicate clicks and rejected writes.
- [ ] Define durable identity/vote persistence and server reconciliation: reload, lost response, retry, multiple tabs and identity reset must not duplicate or silently lose votes.
- [ ] Decide whether standing changes recalculate earlier votes, whether manual overrides apply and which audiences see pending contributions.
- [ ] Assess abuse/cost controls: mass fresh identities, repeat voting, rate limits, optional proof of work, canonical file/commit growth and rebuild cost. Proof of work is an existing idea, not a decided prerequisite.
- [ ] Choose a QDB-specific flag and its off behavior independently; the content-visibility flag's fallback does not implicitly authorize a voting-weight change.
- [ ] Decide sequencing/dependencies and obtain separate Railspike approvals. No voting implementation is added to slice 1 by this checklist.
- [ ] Verify upvote +1/downvote −1 feedback (or clearly defined weighted feedback), truthful pending/rejected state, persistence and cross-view score consistency.

### Brainstorm candidates — not decisions

| Approach | Fresh voter experience | Shared score treatment | Main tradeoff |
| --- | --- | --- | --- |
| Count QDB votes immediately | Accepted up/down vote immediately changes the shared item score | Fresh identities count without account approval, with explicit abuse controls | Strongest shared engagement; mass identities can manipulate totals |
| Tier vote contributions like posts | Voter sees an acknowledged own contribution; other viewers see only contributions authorized for their audience | Positive/approved contributions reach public totals, neutral contributions approved-viewer totals, negative contributions operator-only totals | Closest to graded visibility, but audience-specific scores/counts/ranking and caches are substantially more complex |
| Separate provisional feedback from public weight | Immediate pending movement for the voter, then server-confirmed own contribution that survives reload | Public weight follows the selected trust policy; own pending contribution remains distinct until eligible | Delivers immediate feedback without automatically granting public influence; must label provisional status and reconcile failures honestly |

**Suggested direction for assessment:** combine immediate, truthful voter feedback with a separately defined contribution policy. A purely cosmetic increment that vanishes on reload is insufficient. Start by comparing immediate globally accepted QDB votes with tiered contributions plus a persistent own-vote projection; do not assume the complexity of audience-specific totals is justified. Fastmod text quality is not by itself proof that an identity's votes are trustworthy, particularly for new users who have never posted.

### Relevant current evidence

- `src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php`: `deriveThreadLabelState` distinguishes vote counts from approved score contributions; full rebuild and incremental updates must agree under any new policy.
- `src/ForumRewrite/TagScore.php`: canonical score/tag semantics must remain consistent; Like/Flag behavior should not change implicitly through a QDB up/down feature.
- `public/assets/thread_reactions.js`: `setOptimisticThreadReactionState` currently increments only Like; QDB direction, denominator and server reconciliation need deliberate treatment.
- [QDB todo item 3](../backlog/qdb_todo.txt) records the earlier anonymous-voting problem and sequencing note. Reconcile old proposals during assessment rather than treating them as final decisions.
