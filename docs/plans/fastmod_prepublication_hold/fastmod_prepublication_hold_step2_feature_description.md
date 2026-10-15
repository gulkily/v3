> **Feature plan:** [Step 1](./fastmod_prepublication_hold_step1_solution_assessment.md) · [Step 2](./fastmod_prepublication_hold_step2_feature_description.md) · [Step 3](./fastmod_prepublication_hold_step3_development_plan.md) · [Step 4](./fastmod_prepublication_hold_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Fastmod currently scores already-public posts. Unapproved authors need graded visibility based on their standing, with automatic transitions, approved-user review, and approved-author exemption.

## User stories

- As an unapproved author, I want submission status so that I know who can see my content.
- As an approved user, I want to see neutral unapproved contributions and approve individual posts so that acceptable content reaches the public.
- As an operator, I want to review negative-standing authors and audit/recover decisions so that restricted content stays within its intended audience.

## Core requirements

The audience and intake rules below apply while the enforcement flag is on.

- Approved authors bypass Fastmod restrictions. Unapproved authors have the visibility tiers below; new authors start neutral. Receipt must not wait for the provider. Initial intake must not expose new unapproved content beyond its authorized tier before the visibility decision.
- Score asynchronously with the existing concise rubric and bounded reply context. Derive author standing from validated Fastmod history: positive standing permits public visibility, neutral standing permits approved viewers, and negative standing permits operator-level approved viewers only. Fastmod's existing 0–1 hold probability is distinct from positive/negative author standing; define the calibrated mapping rather than treating a higher hold probability as better standing.
- Apply standing transitions to earlier submissions without an age cutoff as well as later submissions. Automatic widening is allowed only on valid, current evidence; missing, failed, excluded or stale results cannot widen visibility. Elapsed time never widens visibility. Resolve human-approved-post precedence before implementation.
- Any approved user can approve an individual post they are authorized to review without approving its author. Only operator-level approved users may approve posts from negative-standing authors. Ordinary approved users may neither view nor approve those posts. Show submitting authors receipt/status without exposing other restricted content; define any own-content viewing exception explicitly. Repeated/concurrent actions must yield one consistent result. No new rejection/deletion powers are implied.
- Enforce the audience for text, attachments, snippets, counts and distribution artifacts; audit evidence, policy/model/rubric versions, transitions, reviewer and overrides. Provide bounded retries and recovery. A dedicated visibility-enforcement feature flag controls this policy independently of scoring: on enforces tiers; off restores current visibility for all content, including previously held posts, subject to existing instance access rules. Preserve scores, standing, approvals and audit state; re-enabling restores applicable restrictions without treating flag-off visibility as post approval.

| Author standing | Authorized audience before an individual approval override |
| --- | --- |
| Approved | Everyone, subject to existing instance access rules |
| Unapproved, positive | Everyone, including unapproved and no-session viewers, subject to existing instance access rules |
| Unapproved, neutral/new | Authenticated approved users, including operators |
| Unapproved, negative | Operator-level approved users only |

## Delivery scope and completion boundary

**Work type: application change.** Cover local compose/signed thread and reply submissions, private scoring, graded normal-view visibility, author status, reviewer discovery/approval, automatic transitions, retroactive visibility changes and operator controls. Earlier local content has no age cutoff; imported-content coverage remains unresolved. Defer historical score backfill, editing, attachment analysis, cross-instance enforcement and new rejection/deletion workflows. Accompanying attachments still require audience protection.

Complete submission → authorized initial visibility → score/standing decision → tier transition or individual approval → consistent visibility, including retry/recovery. Neutral content belongs in approved users' ordinary reading experience, not solely a private review queue. Account approval must remove Fastmod restrictions; individual post approval does not release other posts. Enforcement is opt-in and defaults off; automated standing decisions require accepted calibration criteria. Split only with renewed approval if this exceeds one day/eight stages.

## Risks

- **Disclosure:** canonical history, media, feeds and generated copies may bypass tiers. Before Step 3, trace all read/publication paths and establish audience enforcement plus artifact invalidation. Public downloads/remote copies cannot be recalled after a downgrade.
- **False standing:** an incorrect mapping can expose bad content or hide an author's entire history. Before Step 3, define human-labeled calibration, neutral band, aggregation, transitions and acceptance limits; keep automation disabled until validated. Identity resets remain a limitation.
- **Authority/races:** operator identity, account approval, post approval and scoring may conflict. Operator-only approval of negative-standing posts is confirmed. Before Step 3, verify the existing operator role and resolve human-override precedence; verify privileges at action time and preserve provenance.
- **Scope:** three audiences and retroactive distribution controls exceed the advisory pipeline. Validate feasibility before Step 3; rescope explicitly rather than omit privacy or recovery.

## Shared component inventory

- Extend compose/thread/reply and signed write flows for receipt/status; reuse shared post/thread cards across standard, Forte and QDB views.
- Extend board/random/search, thread/post, profile/user/username, activity, tag, lobby, RSS and APIs with one viewer-aware visibility policy, including counts/snippets and treatment of replies to restricted roots.
- Extend source/current, historical blobs/commit details, image serving, published read models/exports, static pages and offline snapshot/update distribution. Public artifacts contain only public-tier content; any restricted artifacts require equivalent authorization. Invalidate/regenerate affected local artifacts on transitions.
- Extend Fastmod worker, private score/exchange audit, task queue, CLI status/retry and feature controls; preserve the retired synchronous scoring API.
- Reuse identity/account approval and operator-role checks. Add held-post discovery/actions and author status because pending-account approval screens do not approve individual posts.

## Simple user flow

1. Author submits; approved authors publish normally, new unapproved authors start neutral, and other unapproved authors follow their applicable standing.
2. Worker scores privately and updates standing; public, approved-only or operator-only visibility follows across earlier and later submissions.
3. Authorized reviewers discover and approve individual posts for public visibility; account approval remains separate.
4. Operator inspects failures or retries safely; explicitly disabling the enforcement flag restores current unrestricted-by-standing visibility, including previously held content, while retaining moderation state.

## Success criteria

Audience checks verify each tier against operator, ordinary approved, unapproved and no-session viewers on both write paths and all listed surfaces. Approved authors incur zero Fastmod restrictions. Verify automatic widening, retroactive narrowing without age cutoff, individual approvals, no-timeout behavior, failures and recovery. Restricted text/attachments and metadata never reach unauthorized viewers; transitions have attributable audit evidence. Calibration meets agreed error limits before automation is enabled. Verify flag off → on → off → on for existing and new posts across all surfaces: off matches current behavior, on reapplies retained decisions, and toggles neither erase state nor create individual approvals. Flag changes update/invalidate generated artifacts and caches; prior downloads cannot be recalled.

## Decisions before Step 3

Operator-only approval of negative-standing posts is confirmed. Resolve human-approval precedence on later downgrades, imported-content coverage and author self-view access. Define calibrated standing aggregation/thresholds and transition rules; use the existing operator role rather than assuming every approved user is an operator.


## Approved delivery split

The full requirements above remain the target. Repository inspection shows the complete feature cannot credibly fit eight approximately one-hour stages; the user approved the separate slices before Step 3 implementation planning. No slice may ship restricted content through an unprotected distribution path.

1. **Neutral intake and human release:** new local unapproved submissions are approved-viewer-only in normal reading views; approved authors remain public. Include signed/normal submission, author status, approved-user post approval, audit, retry/recovery, and all public source/media/export/offline protections. No automatic standing changes yet. This is a complete manual-moderation outcome, not completion of the full Fastmod feature; validate its own stage budget before committing to it.
2. **Negative standing and operator review:** add the operator-only tier, operator-only approval, and auditable operator standing changes covering earlier local submissions without an age cutoff. Include normal-view filtering, artifact invalidation, account-approval transitions, and recovery. Automatic Fastmod decisions remain off.
3. **Calibrated automatic standing:** privately score new submissions, validate content/history rules against human judgments, and enable automatic positive/public and negative/operator-only transitions with audit, retries, stale-result protection and rollback. Include all retroactive visibility updates and verify the complete audience matrix.

Each slice needs its own approved scope and development plan. The dependency order preserves the final approved three-tier policy while delivering usable outcomes. If slice 1 alone exceeds the budget after its public-distribution boundary is resolved, rescope it explicitly rather than omit protections.

Before planning slice 1, settle public canonical repository access and existing database/download hosting: local HTTP filtering cannot control direct Git access, externally served files or copies already obtained. Keep restricted submissions out of unprotected published artifacts. Resolve imported-content coverage, author self-view and explicit human-approval precedence; these remain decision blockers, not implementation guesses.
