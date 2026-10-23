# Triage: remove “Browser identity ready”

Date: October 21, 2026. Status: implemented and locally verified; deployment and source-comment closeout pending.

Selected from [October 21 feedback, Feature 02](zenmemes_meta_feedback_2026-10-21_summary.md). Original comment: [reply-20261021175826-902c5145](https://zenmemes.com/posts/reply-20261021175826-902c5145).

## Decision

Take this as the next quick win: low severity, high confidence, very small expected scope. It removes recurring noise from posting surfaces and directly matches the repository's UI-copy guidance. The inventory's provisional 0.03-cycle estimate remains unvalidated; this triage does not select an effort-study cycle or approve implementation.

Reply focus (Feature 01) also benefits a frequent action but needs submission, navigation, and phone-keyboard checks. Composer expansion touches layout and restored drafts. Reply latency and QDB availability have greater potential impact but uncertain causes and substantially higher investigation costs. Removing this notice offers the clearest benefit for the smallest bounded change.

## Railspike estimation snapshot

| Field | Record |
| --- | --- |
| Candidate / scope | `browser_identity_ready`, v1: suppress routine preparation-ready copy and its empty space across affected shared surfaces; preserve pending, failure, recovery, and explicit Account outcomes |
| Checkpoint / UTC | Post-source triage, 2026-10-21T18:37:37Z; not a Step 1–4 approval checkpoint |
| Inherited estimate | **0.03**, from the feedback inventory; retained as the original feedback-only judgment |
| Scoped projected total | **0.10 Railspike cycles**, including inspection already performed, necessary planning, implementation, required verification, documentation, ordinary correction, and the requested source-comment closeout reply |
| Rater / exposure | Codex, single exposed rater; feedback scores, prior source inspection, this triage, the trial ledger and calibration examples were visible. No independent or pre-inspection rating is claimed |
| Starting revision | Initial triage inspected `bc42277b46f95807e1ce9966707eca0620766a9b`; estimate recorded with checkout at `e950427e44a0f64d085468dc650391f299a2f002`. Recheck affected source before implementation; no intervening-diff audit is claimed |
| Reference | R1 from the [effort-estimation ledger](effort_estimation_trial.md#frozen-reference-set-r1); report and diff-review SHA-256 hashes verified against the ledger. Frozen feedback hash verified using Git revision `4f6cfe2b436fae8d939b1349fe724a3879ee0a0e` |
| Confidence | Moderate for total effort; high that the reported string remains in source. Runtime reproduction and shared Account interaction remain unverified |
| Study / approval | Estimation data only; not selected into the six-outcome trial. No implementation approval or completed outcome is recorded |

**Scale:** 1.0 is the largest well-scoped, releasable slice comfortably approvable for one Railspike cycle, including verification and documentation. Scores are relative judgments, not hours, priority, measured labor, or additive forecasts. R1 remains provisional pending maintainer acceptance.

**Rationale:** Comparable to R1's duplicate-preview suppression (0.10): a narrow shared-presentation change with preserved behavior to verify. Below toolbar identity (0.35), which required server seeding and cross-tab state. The inherited 0.03 captures the apparent text removal; 0.10 allows for empty-element handling, asynchronous ready transitions, shared Account interactions, and phone/desktop checks across affected views and themes. This is a new source-informed total for the same v1 outcome, not an overwrite of the original estimate or a claim of measured overrun.

**Dependencies and revision triggers:** Existing local browser fixtures, test tooling, and controllable saved/pending/failed identity states must support the acceptance checks below. Raise or revisit the total if reproducing those states requires substantial new infrastructure, if Account needs separate state handling, or if a race changes signing/submission behavior. Broader composer movement or reply latency work is separate scope. No schema migration, new navigation, or production deployment is included. Preserve draft and failure/retry recovery throughout. If ordinary Railspike planning is later chosen, carry this snapshot forward and append estimates at applicable handoffs; do not invent missing intake or approval checkpoints. Closeout should record actual verification, omissions, corrective work versus new scope, and a separate retrospective scope/maintainer-fit judgment.

## Evidence and likely scope

- `public/assets/browser_signing.js`: `identityStateMessage("ready")` still returns the exact reported message. `renderIdentityPreparationState()` sends it to shared compose/account status nodes.
- `initComposeRoot()` calls `refreshComposeIdentityFromStorage()`, which renders the preparation state immediately and again after fingerprint preparation. This provides a source-level path for a saved, published identity to show the notice.
- `setStatus()` unconditionally sets `node.hidden = false`. Merely replacing the message with an empty string would leave an empty status element visible. The renderer also uses `config.message || identityStateMessage(state)`, so an empty override alone would fall back to the existing notice.
- Shared status hooks appear in board/thread, standalone thread/reply, QDB-add, paned compose, and Account templates. Account also has its own status rendering; inspect that interaction before changing shared behavior.
- Source evidence confirms the code remains; no browser reproduction or production verification was performed. The earlier resolved identity-copy item does not close this report.

## Recommended fix and acceptance

Suppress the routine preparation-ready notice and hide its empty status element. Preserve identity loading, publishing, failure, recovery, and explicit account-action results. Avoid broad removal of all success statuses or changes to signing, publication, drafts, or submission behavior.

- With a saved, published identity, opening and focusing a composer never shows “Browser identity ready” or a blank status gap.
- Transitioning from identity preparation to ready clears the preparation message and collapses the routine status element.
- Pending and failed preparation retain actionable feedback; signing and posting still work.
- Explicit Account outcomes remain visible where useful; no stale details survive hiding a routine notice.
- Verify affected shared views on phone and desktop, including initial, loaded, empty, success, failure, and relevant expanded states. Check loaded-content overflow and composer movement in affected themes.
- Run relevant existing browser-signing, lazy-compose, and identity-diagnostics checks; update affected assertions. Record rendered checks and limitations in the [UI copy audit checklist](ui_copy_audit_checklist.md).

This is a small, straightforward cleanup eligible for the repository's direct-change exception. No implementation has been performed as part of triage.

## Closeout

- Keep Feature 02 open until implementation and verification are complete.
- User requested a reply under the original comment when the work is done. After verified completion, post a concise result under `reply-20261021175826-902c5145`; report only verified behavior and identify any remaining limitations. Do not claim that the other composer-jumping requests in that same comment are resolved.

## Implementation closeout — October 21, 2026

Implemented on user approval (“Please go ahead”). Routine readiness now produces no message; the shared status helper hides empty messages and clears stale technical details while preserving pending/error and explicit Account results.

Verification: 88 targeted PHP/Node checks, 24 standalone Chromium composer cases, and 32 shared board/thread/paned/QDB cases passed at phone/desktop widths across Light, Word97, Chicago, and Midnight. Explicit Account feedback, Outbox saves, and signed thread/reply publication passed. See the [UI copy audit checklist](ui_copy_audit_checklist.md#browser-identity-ready--october-21-2026) for coverage and limitations. Test-harness corrections handled collapsed versus draft-expanded inline reply editors; no extra product behavior was added.

Local implementation and verification are complete. Production deployment, production verification, and the source-comment reply remain pending; Feature 02 should not be represented as deployed. Retrospective scope fit: the shared presentation-only fix stayed within the triaged scope; verification covered the expected shared surfaces. No measured effort or trial outcome is claimed.
