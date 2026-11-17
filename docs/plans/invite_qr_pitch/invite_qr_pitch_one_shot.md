# Invite QR with a short pitch

Cycle: `v3/invite_qr_pitch`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: enabled; no real alternatives or work units, so no unit estimates

**Cycle estimate: 0.20 (intake and execution entry)**

Status: plan written; next permitted action: planning commit, then implementation.

## Original request

Maintainer, 2026-11-07 (in a Claude session): "It would be nice if the invitation page, when it showed the QR code, maybe if the QR code was a little bit smaller, but the page could give you like a quick outline of what you're being invited to and why you should join, just so that like if I'm at a party and I just show someone the QR code and tell them brief about the website, it also like introduces it."

Feedback items: none match (searched `docs/feedback/items/` for invite, QR, pitch and introduce).

## Authorization

The request describes a bounded change to one page and the maintainer expects agents to carry such requests to a PR. Read as authorization for a one-shot cycle through an open PR. Excluded: merge, deploy, production access, and changes to the lobby (redemption) page.

## Outcome and work type

Application change. On `/invites/`, after an invite is generated, the QR area shows a short pitch for the person being shown the phone: "You're invited to {site}", the site description, and a few reasons to join. The QR code is smaller, so pitch and code fit on one phone screen. Profiles with no pitch copy show only the QR, as today.

Allowed paths: `templates/pages/invites.php`, `public/assets/invitations.css`, `ProfilePresentationContent` (new `invitationReasons` editorial key), `LobbyController` (pass pitch data), `tests/InvitationIssuanceTest.php`, this record, the plans index, the ledger and the debug log.

## Approach and risks

- Copy comes from the profile, like the About page: `ProfilePresentationContent::siteDescription()` (already shared by About, the visitor intro and `/llms.txt`) plus a new short `invitationReasons` list for zenmemes. The reasons are new copy; the maintainer may want to reword them, which is a one-line change.
- The pitch lives inside the existing hidden `data-role=invitation-qr` block, so it appears only when the QR does (no first-paint change; the block is already revealed by `invite_issuance.js`).
- Layout: pitch beside the QR on wide screens, above it on phones. QR canvas drops from 18rem to 11rem; at a 4px cell and typical invite URL length it stays scannable on a phone screen. Risk: scannability at the smaller size. Mitigation: decode a rendered screenshot with a QR decoder.
- Themes: the block uses plain `h2`/`p`/`ul`; check Light, Word 97 and Bubblegum at phone width.
- UI copy rule: this adds text, but the maintainer asked for it and it is the page's purpose in that moment. The old "Scan to accept this invitation" line becomes a short caption under the code.

## Reuse decisions

- Description: `ProfilePresentationContent::siteDescription()`; already in every template as `$siteDescription`.
- Site name: `$siteName` (template global).
- Reasons: new `invitationReasons` editorial key, next to `communityParagraphs` and `buildExample`, because no existing key is a short list of reasons to join. Exposed through a new `ProfilePresentationContent::invitationReasons()` like `siteDescription()`.
- Markup: existing `.invitation-qr` block; no new component. CSS in `invitations.css`.

## Completion conditions

- Normal entry: You → Invite → Generate invite link.
- End-to-end: the QR area shows the pitch and a smaller code that still scans to the invite link.
- Recovery: if the QR can't render, the block stays hidden as before (pitch goes with it; the link and Copy remain).
- Release: maintainer merges and deploys.

## Concepts touched

None changed. Proposed: none.

## Verification and recovery plan

Smoke set plus `InvitationIssuanceTest`. Render `/invites/` locally as an approved member, generate an invite, screenshot at 390px and 1280px in Light, Word 97 and Bubblegum, and decode the QR from the screenshot. Full suite not run, per `AGENTS.md`.

Starting revisions and branch: `feat/invite-qr-pitch` from `6bdc52d7`.

Planning commit: pending.

## Dated execution history

- 2026-11-17: record written before implementation.

## Study history

- 2026-11-17 intake and execution entry: **0.20**. Prior exposure: the same agent inspected `invites.php`, `invitations.css`, `ProfilePresentationContent` and the visitor intro before the estimate. Claude single exposed rater; R1 hashes not re-verified; baseline `6bdc52d7`.

## Outcome

Pending.

## Verification

Pending.

## Final review

Pending.

Commit/merge/publication status: not committed.

*Claude (claude-opus-5-5), 2026-11-17 UTC.*
