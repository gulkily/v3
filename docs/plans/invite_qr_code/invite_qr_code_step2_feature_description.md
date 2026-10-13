> **Feature plan:** [Step 1](./invite_qr_code_step1_solution_assessment.md) · [Step 2](./invite_qr_code_step2_feature_description.md) · [Step 3](./invite_qr_code_step3_development_plan.md) · [Step 4](./invite_qr_code_step4_implementation_summary.md)

Debug mode: enabled

## Problem

The invite page provides a copyable link, but inviting someone in person requires an immediately scannable handoff. Approved Step 1 selects an inline QR code alongside that link.

## User Stories

- As an approved member, I want a QR code immediately after generating an invite so that someone beside me can scan it.
- As an invite recipient, I want scanning to open the invitation so that I can complete the existing acceptance flow.

## Core Requirements

- Automatically show an inline QR code after successful invite creation, without another click or page navigation; retain the visible link and Copy control.
- Encode exactly the complete displayed invitation link, preserving its secret and the existing redemption and optional-destination behavior; generate the QR locally without an external QR service.
- Keep the QR scannable on narrow screens and across supported themes, with a clear text label and the copyable link as an accessible alternative.
- Keep the link and QR paired through repeated attempts. Hide the previous result when generation starts; failed issuance must not display a new or stale successful result.
- If QR rendering fails after issuance succeeds, retain the new copyable link and explain that scanning is unavailable without reporting invite creation as failed.

## Delivery Scope and Completion Boundary

**Work type:** Application change. Extend the existing invite result; preserve permissions, expiry, revocation, and redemption rules. Exclude invite history, downloads, printing, and schema changes.

**Boundary:** Enter through Invite, generate successfully, scan from another device, and complete existing redemption and destination handling. Release requires verified scanning and link fallback, including repeated attempts and failure recovery.

## Risks

- **Unreadable code:** Small screens, themes, or long site addresses could prevent scanning. Validate representative addresses and phone scanning at the first working preview; require sufficient contrast, clear margins, and responsive sizing.
- **Wrong or exposed invitation:** Mismatched results could share an older invite; external rendering could disclose the secret. Review the current result lifecycle before Step 3, then validate repeated attempts and rendering failure early; require local generation and one consistent link/QR result.
- **Unreachable recipient URL:** A site address accessible only on the inviter's device prevents redemption. Check the acceptance-test address before cross-device testing; use an address both devices can reach and preserve the existing link destination.

## Shared Component Inventory

- **Invite navigation and page:** Reuse existing entry and member access checks; extend the canonical generated-result area, Copy control, and feedback with a labeled QR display.
- **Issuance and revocation:** Reuse existing browser issuance and preparation/finalization APIs; the QR represents their successful invitation, not a separate issuance path. Retain existing revocation UI and behavior.
- **Lobby and redemption:** Reuse the existing invitation acceptance UI, identity readiness, redemption preparation/finalization, and destination handling. No separate scan landing page is needed.
- **Presentation:** Reuse shared page styling and asset loading; add QR presentation within the invitation result because that surface currently provides only a text link.

## Simple User Flow

1. Open Invite, optionally choose a destination, and generate an invitation.
2. Show the automatically displayed QR to the recipient, or copy the link if scanning is unavailable.
3. The recipient scans, opens the invitation, and completes existing acceptance to reach the intended destination.

## Success Criteria

- Every successful tested generation displays a matching QR/link pair without an extra action; decoded QR content exactly equals the copied link.
- A real phone scan completes redemption with and without an optional destination; the code remains readable in supported themes and at a narrow viewport.
- Repeated-attempt and issuance-failure checks show no stale result; forced QR failure leaves the new link usable with clear feedback.
