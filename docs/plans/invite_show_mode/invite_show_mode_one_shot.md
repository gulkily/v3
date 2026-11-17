# Invite page shows only the invitation once generated

Cycle: `v3/invite_show_mode`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: enabled; no real alternatives or work units, so no unit estimates

**Cycle estimate: 0.20 (intake and execution entry)**

Status: plan written; next permitted action: planning commit, then implementation.

## Original request

Follow-up to [invite_qr_pitch](../invite_qr_pitch/invite_qr_pitch_one_shot.md) (PR #304, merged). Maintainer, 2026-11-17, with a screenshot of `/invites/` after generating: "what else can be stripped away from this page once the invite is generated?" Claude proposed hiding the form, the warning paragraph and the Revoke card, shrinking the link box to one line, folding the expiry into the QR caption, and adding a New invite button, hiding automatically on success. Maintainer: "please make follow-up pr".

Feedback items: none match.

## Authorization

Execution authorized through an open PR. Excluded: merge, deploy, production access, and a new home for Revoke (it stays on this page, shown before an invite is generated and after New invite).

## Outcome and work type

Application change. After an invite is generated, `/invites/` shows only the pitch, the QR code ("Scan to join · valid 30 days"), a one-line link with Copy, and New invite. The heading, the warning, the form and the Revoke card are hidden until New invite, which brings them back without revoking the issued invite.

Allowed paths: `templates/pages/invites.php`, `public/assets/invite_issuance.js`, `public/assets/invitations.css`, `tests/InvitationIssuanceTest.php`, this record, the plans index, the ledger and the debug log.

## Approach and risks

- Wrap heading, warning and form in `data-role=invitation-setup`; mark the Revoke card `data-role=invitation-revoke`. On successful issue, hide both; New invite shows them, hides the result and clears the link. Every lookup tolerates a missing element (the existing JS-harness tests mock only some selectors).
- Order inside the result: pitch and QR first, so they sit at the top of a phone screen; link row below.
- Link: `textarea rows=3` becomes a one-line readonly `input`; Copy still selects it, so the `execCommand` fallback keeps working.
- Expiry moves into the QR caption. When the QR can't render, the fallback message carries it instead ("…copy the invitation link instead. It expires in 30 days.").
- Uncertain-finalization path unchanged: setup stays visible with "Check invitation" because the result is not shown until success.
- Risk: no `h1` while the result shows (the pitch `h2` is the top heading). Accepted; the page title still names the page.

## Reuse decisions

Existing `button-row` layout, `meta` class and feedback node. No new components.

## Completion conditions

- Entry: You → Invite → Generate invite link.
- End-to-end: after generating, only pitch, QR, link, Copy and New invite are visible; New invite restores the form and Revoke.
- Recovery: QR failure keeps the link, Copy and the expiry message.
- Release: maintainer merges and deploys.

## Concepts touched

None.

## Verification and recovery plan

Smoke set plus `InvitationIssuanceTest` and `LocalAppSmokeTest`; a new JS-harness test for hide-on-success and New invite. Screenshot `/invites/` after a simulated issue in Gloss, Word 97, Bubblegum and Midnight at 390px and 1280px, and decode the QR. Full suite not run, per `AGENTS.md`.

Starting revisions and branch: `feat/invite-show-mode` from `f05cc945`.

Planning commit: pending.

## Dated execution history

- 2026-11-17: record written before implementation.

## Study history

- 2026-11-17 intake and execution entry: **0.20**. Prior exposure: the same agent built the parent cycle and read `invites.php` and `invite_issuance.js` before the estimate. Claude single exposed rater; R1 hashes not re-verified; baseline `f05cc945`.

## Outcome

Pending.

## Verification

Pending.

## Final review

Pending.

Commit/merge/publication status: not committed.

*Claude (claude-opus-5-5), 2026-11-17 UTC.*
