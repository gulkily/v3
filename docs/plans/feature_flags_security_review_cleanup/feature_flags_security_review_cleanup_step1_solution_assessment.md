> **Feature plan:** [Step 1](./feature_flags_security_review_cleanup_step1_solution_assessment.md) · [Step 2](./feature_flags_security_review_cleanup_step2_feature_description.md) · [Step 3](./feature_flags_security_review_cleanup_step3_development_plan.md) · [Step 4](./feature_flags_security_review_cleanup_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/feature_flags_security_review_cleanup` · [Ledger](../effort_estimation_trial.md#enrollment-index-after-p2)

## Original Query

“Review account key grants and approval-policy changes (authenticated operator session required).”
Why is there this lone paragraph on the page, and why does it lead to another page with just one line on it?
Please write Step 1.

## Understood Intent

Keep the operator audit capability, but give it a suitable home and presentation. It is a history of account-key approvals, accepted invitation grants, and cross-account approval-policy changes—not a feature-flag control.

## Problem

A detached paragraph on Feature Flags sends users to a security audit whose access checks and sparse presentation can make it look like a broken or empty destination.

## Evidence

- Feature Flags shows the link based on an identity hint; its destination requires an authenticated root-approved session and otherwise returns a single-line 403. This is a source-supported explanation, not a reproduction of the user's exact response.
- The authorized view lists relevant events and a current-policy notice; no matching events leaves it sparse. User Access already contains a second standalone “Security review” link.

## Option A — Access history within User Access (recommended)

Replace the detached links with an **Access history** section in Tools → User Access. Show recent events as a compact chronological list: what happened, affected account, actor, and date. Expand an event for authority, policy-at-acceptance and source evidence; keep the full audit available through “View all” when there is more history.

- Pros: puts history beside account/key management; immediately shows useful information; keeps technical evidence available without dominating the page.
- Cons: requires a bounded recent-history view and consistent details across embedded and full history.

## Option B — Dedicated Access history view

Give User Access an ordinary **Access history** navigation item leading to a full history page, with the same compact events and expandable evidence. Remove the Feature Flags paragraph and the existing loose User Access link.

- Pros: a clear destination for large histories, filtering and paging; avoids adding event content to the account list.
- Cons: requires another navigation step; empty or expired-session states must still be useful to avoid recreating the reported dead end.

## Recommendation and boundary

Choose A: operators discover account-access history where they manage accounts, see recent events directly, and expand only the evidence they need. Show a concise empty state when no events exist. Require authenticated operator access consistently; offer the existing account authentication/recovery path when a legitimate operator's session expires, without exposing protected events. Remove the old paragraph rather than replace it with another explanation. Preserve historical-uncertainty warnings, audit records, permissions, and the current policy control in Feature Flags. Reuse canonical history data/details and retain existing audit URLs; no new audit collection or approval-policy behavior. Verify operator, hint-only, non-operator, empty/populated, expanded and expired-session flows on phone/desktop. Include applicable icon-theme checks if navigation is added or renamed. Reproduce which one-line state the user encountered before claiming it fixed. This is a complete account-management-to-audit slice; application changes and Step 2 remain unapproved.

## Request revision

- Request: “We need to just find a new place and presentation mode for whatever this thing is.”
- Disposition: scope v2 replaces removal-only scope v1 with relocation and event presentation. Approval pending; no implementation. Original query and v1 scores retained below.

- Mockup request: “Please make a mockup of this.” Authorized a standalone interactive design artifact, not runtime implementation or Step 1 approval. [Open mockup](./feature_flags_security_review_cleanup_mockups/index.html); sample data, expandable evidence, recent/full-history preview, and empty/expired/non-operator states. Checked at 390/1280 px; theme-specific product acceptance remains future work. Scope v2 projected total **0.45 reaffirmed** with this presentation preview; no new checkpoint or independent rating.

## Study snapshots

- Intake — 2026-10-21T19:29:22Z; Codex, single exposed rater (verified model identifier unavailable); scope v1 above; start `0b9f5f8baba58b31251b6a6a6b228412784328fe`; R1/P2; projected total **0.18**. Recorded after source inspection of the link, destination, permissions, and existing tests; no pre-inspection estimate exists. Compared with 0.10 duplicate-preview removal and 0.20 profile-copy integration, allowing for role/session and destination verification. Not time, priority or approval.
- Step 1 handoff — 2026-10-21T19:29:22Z; same rater, scope, start and R1/P2; **0.18 reaffirmed** for Option A, including planning, implementation, verification, documentation and ordinary correction. No inherited feature score or independent second rating. Frozen report/diff-review hashes verified at `/home/wsl/railspike-dev/docs/research/effort_estimation/`; frozen feedback hash verified from Git `4f6cfe2b436fae8d939b1349fe724a3879ee0a0e`. Approval and runtime evidence pending; no outcome acceptance claimed.
- Revised Step 1 handoff — 2026-10-21T19:51:05Z; same rater/start/R1/P2; scope **v2**, projected total **0.45**. Changed scope now includes embedded recent history, expandable evidence, full-history handoff, empty state and authenticated-session recovery, plus required shared-view/theme checks. Uses existing history data and account-management surfaces; greater than the v1 removal-only proposal, below the 0.60 composer-decluttering reference. Includes work already done in this cycle, not only remaining work. Prior reference and source exposure retained; no independent rating or approval implied.

- Approval update — 2026-10-21: user approved Step 1 scope v2 after reviewing the interactive mockup. Earlier pending statements above are historical; Step 2 is now drafted for approval.
