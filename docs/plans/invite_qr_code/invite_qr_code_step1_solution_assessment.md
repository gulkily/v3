> **Feature plan:** [Step 1](./invite_qr_code_step1_solution_assessment.md) · [Step 2](./invite_qr_code_step2_feature_description.md) · [Step 3](./invite_qr_code_step3_development_plan.md) · [Step 4](./invite_qr_code_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

As a user, if I am inviting someone in person, I want to have a QR code for them to scan as soon as I've generated an invite on the invite page. Please write Step 1 of Railspike. Use debug mode.

## Problem

The invite page currently provides a copyable link after successful generation, but no immediately scannable way to hand that invitation to someone in person.

## Option A — Show the QR code inline (recommended)

Automatically display a QR code beside or below the generated link on the invite page, ready for the other person's phone to scan into the existing invitation flow.

- **Pros:** Meets the immediate-scan request without another action; keeps the link and Copy control available; extends the existing success result with limited scope.
- **Cons:** Requires space on small screens and a scan-friendly size and contrast across themes; repeated generation must keep the displayed link and code synchronized.

## Option B — Automatically open a scan dialog

Open a dedicated QR dialog immediately after successful generation, giving the inviter a larger code to show the recipient, with a close action returning to the generated link.

- **Pros:** Prioritizes in-person scanning and gives the code a prominent, uncluttered display.
- **Cons:** Interrupts link-copying use; adds dialog focus, dismissal, and reopening behavior; requires more interaction and accessibility work than an inline result.

## Recommendation and Vertical Slice

Choose **Option A**. Generate the QR locally in the browser from the same complete invitation link, avoiding transmission of the invitation secret to an external QR service. The existing page already creates the link in the browser after successful issuance ([current flow](../../../public/assets/invite_issuance.js), [page](../../../templates/pages/invites.php)).

- **Complete outcome:** Generate an invite through the normal invite page, immediately see its QR code and copyable link, and scan from another device to use the existing redemption flow, including any selected destination.
- **Recovery:** If QR rendering fails, keep the successfully generated link usable and explain that scanning is unavailable. On another generation attempt, prevent an older code from appearing to represent the new result; never pair a new link with an old code.
- **Validation focus:** Confirm real phone scanning, narrow-screen and theme readability, exact agreement between scanned and copied links, repeated generation, and issuance/rendering failures. The recipient must be able to reach the invitation's site address.
- **Scope:** One small, releasable slice, expected to fit within a day. Preserve existing invitation permissions, expiry, revocation, and redemption behavior; no database changes, invite history, QR downloads, or printing workflow are needed.

## Review

Awaiting `Approved Step 1` before drafting Step 2.
