> **Feature plan:** Step 1 · [Step 2](restore_invitation_prompt_step2_feature_description.md) · [Step 3](restore_invitation_prompt_step3_development_plan.md) · [Step 4](restore_invitation_prompt_step4_implementation_summary.md)
Debug mode: enabled
Effort study: railspike-effort / v3/restore_invitation_prompt; [ledger](../effort_estimation_trial.md#restore-invitation-prompt)
Extended estimates: enabled
**Cycle estimate: 0.65**

## Original Query

Write Step 1 for restoring prompt(), please.

## Understood Intent

Restore familiar username selection during untargeted invitation redemption. The reported flow supplies neither name selection nor later naming. Initially the user preferred a native prompt; they subsequently selected a Username field with the Redeem button. Broad prompt restoration is outside this cycle.

Problem: Shared key creation defaults to `guest`, later naming is hidden behind the default-off Username changes flag, and browser tests handle dialogs without requiring one to appear.

## Option A — Restore the prompt and recover initial naming

**Estimate: 0.55**

Prompt for a name on deliberate untargeted redemption when needed, and offer the same native prompt from Account for existing unnamed guests. Permit initial naming independently of later renaming, retaining the key, posts and approval; preserve named identities and automatic targeted entry.

- Benefit: Covers fresh visitors, automatic guest keys and people already stranded as `guest`.
- Cost/risk: Requires a server-enforced definition of initial naming; local state or the literal name `guest` alone must not bypass the rename flag repeatedly. This is a proposed policy change.
- Driver/uncertainty: Same-key recovery and flag semantics; suppressed dialogs need visible retry.

## Option B — Restore only the fresh-key prompt

**Estimate: 0.25**

Prompt before creating a new key during untargeted redemption, retaining existing naming restrictions for saved keys.

- Benefit: Smallest restoration; no initial-naming policy change.
- Cost/risk: Automatic guest keys bypass the fix; registered guests remain stranded with renaming disabled. Does not fully address the report.
- Driver/uncertainty: Cancellation and actual-dialog assertions; narrower recovery than A.

## Option C — Inline initial naming (selected; recommended)

**Estimate: 0.65**

Place a Username field directly above Redeem invitation for untargeted invitations needing a name. One click names and redeems using the existing key, or creates a named key for a fresh visitor. Existing named identities keep their name. Reuse Account’s inline editor for guest recovery, with the initial-naming policy in A.

- Benefit: Visible, editable interaction without a native dialog.
- Cost/risk: More layout/theme verification; same initial-naming policy uncertainty as A.
- Driver/uncertainty: Shared naming plus responsive forms and failure states.

## Recommendation and boundaries

Select C as one releasable slice: untargeted entry → chosen name and membership → same-key retry or Account recovery. Leaving before submission consumes no slot; empty/invalid input must not silently accept `guest`; failures retain the entered name and key for retry without duplicate redemption. Verify field entry and submission, names after reload/rebuild, automatic-key and rename-flag on/off, public/private sites, phone/desktop, and targeted-entry regressions. Replay the reported condition; the user's deployment remains unverified.

Bot friction: Neither the field nor a native prompt is an access control or measured defense; existing tests automate dialogs. Preserve signed APIs and keyless participation. No blanket prompt restoration, CAPTCHA, dependency/schema changes or invitation-capacity changes.

Reuse: `public/assets/invite_redemption.js`, `public/assets/browser_signing.js`, `templates/pages/account_key.php`, signed profile-name contracts in `src/ForumRewrite/Write/LocalWriteService.php`, and existing invitation browser tests. Extend these rather than create another identity system; correct invitation guidance and UI audit records during implementation.

Concepts touched: [Invitation lifecycle](../../concepts/invitation_lifecycle.md) entry changes, authority unchanged; initial naming versus renaming is a proposed inventory addition. Related [multi-redemption cycle](../multi_redemption_invites/multi_redemption_invites_step4_implementation_summary.md) stays backend-only; its approvals do not authorize this work.

Enhancement requests: Guest recovery proposed in A/C, pending approval; broad prompt restoration and bot-control changes excluded. No implementation or branch reuse authorized.

## Study checkpoint

Scope: v1/A; baseline 767a8a2ba8270e29269fd9d2f6d2e707bff8ffa4; 2026-11-01 UTC; R1/P2 standing enrollment. Pristine intake missing: prior diagnosis/source inspection disclosed. First Step 1 projection: 0.55; alternatives are non-additive whole-cycle judgments including planning, verification and correction. Three frozen R1 hashes verified; Codex single exposed rater. No measured labor or accepted outcome. Planning uncommitted; await Approved Step 1 before Step 2.

*— Codex · 2026-11-01 UTC*

## Selection revision — 2026-11-01 UTC

Request: “Is there another way we can solve this? Like having a username selector with the redeem button?” followed by “Let's do it.”
Disposition: Select Option C, scope v2/C; retain A/B as alternatives and the original v1/A checkpoint above. Inline entry replaces the native prompt recommendation; initial naming and same-key recovery remain in scope.
Checkpoint: Whole-cycle 0.65, reaffirming the existing C estimate; prior A recommendation 0.55 preserved. Same baseline/R1/P2 and single exposed rater; frozen hashes reverified. No implementation, measured labor or accepted outcome.
Approval: Direction selected; revised Step 1 awaits “Approved Step 1” under the staged workflow. No Step 2 or implementation authorized by this revision.

*— Revised by Codex · 2026-11-01 UTC*

Approval received: User said “approved step 1” on 2026-11-01. Option C/v2 approved for Step 2; implementation remains unapproved.

*— Codex · 2026-11-01 UTC*
