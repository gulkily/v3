> **Feature plan:** [Step 1](./message_sync_activity_step1_solution_assessment.md) · [Step 2](./message_sync_activity_step2_feature_description.md) · [Step 3](./message_sync_activity_step3_development_plan.md) · [Step 4](./message_sync_activity_step4_implementation_summary.md)

Status: Earlier proposal superseded; legacy upgrade-required restriction withdrawn from current recommendation, never approved
Date: 2026-10-30 UTC
Cycle: v3/message_sync_activity
Scope: Historical verification under the approved resource ceilings

## Current disposition

The user rejected blocking sends and selected recorded-key-first recovery with bounded fallback. The [revised Step 2](./message_sync_activity_step2_feature_description.md#revision-checkpoint--recovery-without-send-restrictions) is the current proposal. No new send restrictions or legacy upgrade-required response are authorized. The remaining text preserves the earlier proposal and evidence; its suggested compatibility relaxation is not current scope. Legacy progress under the unchanged limits remains to be demonstrated, not assumed impossible or already solved.

## Finding

The Stage 3 exact-signer optimization is unsafe. `PrivateMessageMailboxService::send` records the authenticated submitting identity, without examining the encrypted signature. An original can therefore be submitted by key A and signed by another approved account key B. The existing history service supplies all currently approved sender keys; using only A changes which existing messages can be verified.

A new regression test reproduces the exclusion in the experimental implementation and passes against the Stage 2 implementation. The existing real-key crypto test separately confirms that original verification succeeds with the actual signer and rejects an unrelated key. This is a supported-data compatibility concern, not evidence that the user's particular messages used different submitting/signing keys.

The experiment's bounded fallback also rejected accounts with more than its remaining 64-key budget. A generic 503 would retry forever for unchanged data. Keeping that fallback is not acceptable. All experimental Stage 3 product/schema changes have been withdrawn; completed Stages 1 and 2 remain committed. No schema upgrade is now part of this branch.

## Proposed replacement for the affected Step 2 requirement

Keep every approved numeric ceiling. Add an explicitly versioned bounded verification-key protocol for the updated browser. Submitting identity is a lookup hint only: failure to verify with that key must continue through bounded pages of approved sender verification material. Preserve original ciphertext and signatures and require successful original verification before issuing a receipt. Persist only non-secret continuation metadata; do not persist extracted session keys or plaintext.

Preserve existing legacy routes and their successful behavior wherever complete verification material fits the approved limits. When a legacy request cannot fit, return a documented non-retryable `409 history_client_upgrade_required` response directing the caller to reload/update the client. Do not silently omit verification keys, emit a perpetual transient retry response, or expand the budgets. Existing encrypted history remains recoverable through the updated client. Ordinary send/list/read routes remain independent.

This deliberately narrows the earlier promise that every old client can recover every historical record under the new ceilings. Approval is required for that compatibility change. An alternative is to retain the full old-client promise and revise the ceilings; that is not recommended because it preserves the amplification the user wants to remove. An old-client-compatible server-driven replay scheme has not been ruled out, but would require a separate design/verification pass to establish failed-verification progress and avoid repeating original crypto for every key page; no claim of mathematical impossibility is made.

## Consequent Step 3 revision, after Step 2 approval

Stage 3 must define and test paged verification for mismatched submitting/signing keys, missing historical identity metadata, 115 approved keys, maximum accepted key/original sizes, and the explicit legacy response. Stage 5 must share verified originals across destinations and handle verification continuation without caching a temporary failure as final. Stage 6 must resume these pages within the existing request/time budgets. Stages 7 and 8 must cover the client-upgrade recovery flow and document the compatibility boundary. Other stage requirements remain unchanged.

No dependent implementation is authorized by this proposal. Obtain renewed Step 2 approval, then write and obtain approval for the revised Step 3 before continuing implementation.

Study checkpoint: P2/R1, single exposed Codex rater; original whole-cycle 0.95 and Stage 3 estimate 0.15 preserved. Current outcome incomplete at the compatibility gate; no retrospective completion score, measured labor or maintainer acceptance. Re-estimate the revised scope at its planning checkpoint rather than assigning an implementation-success score here.

*— Codex · 2026-10-30 UTC*
