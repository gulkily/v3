> **Feature plan:** [Step 1](./invite_zero_click_step1_solution_assessment.md) · [Step 2](./invite_zero_click_step2_feature_description.md) · [Step 3](./invite_zero_click_step3_development_plan.md) · [Step 4](./invite_zero_click_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Targeted invitations currently require interaction before entry, and the target is not reliably reached. A recipient should open the invite and arrive at its authorized destination without choosing a username or clicking Redeem.

## User stories

- As an inviter, I want the recipient to land directly on the shared resource so that the invitation supplies both access and context; including that destination should be opt-in and default to the page I came from.
- As a recipient, I want immediate access with my existing identity or a temporary name so that account setup does not interrupt reading.
- As a recipient, I want to choose my name later without losing my key or approval so that my identity remains continuous.

## Core requirements

- Opening a valid targeted invitation automatically reuses an existing browser identity or creates a persistent browser-owned key with a temporary name, redeems the invite, authenticates, and displays the target with zero additional clicks or prompts.
- Clicking Invite from any page opens the form with “Include destination URL” unchecked and the destination field hidden/disabled. Checking it prefills the originating page’s supported internal URL, which remains editable; toggling off/on preserves edits, and unchecked submission omits the destination. The invite target takes priority over generic return destinations and preserves supported internal paths, query parameters, and fragments; unsupported or external destinations cannot redirect the recipient off-site.
- Successful redemption grants existing invitation-based member approval, including entry through the members-only gate. “Closed” means access unlocked by that approval; independent resource restrictions remain enforced.
- Naming is optional after arrival, available through Account, and preserves the key, identity, approval, and existing content. Existing identities and chosen names must not be replaced.
- Opening a targeted invite intentionally attempts single-use redemption. Interrupted success can resume for the same identity; invalid, expired, revoked, differently redeemed, unavailable-target, and browser-storage failures show actionable recovery without loops or unintended extra consumption. Untargeted invitations retain their explicit redemption flow.

## Delivery scope and completion boundary

Work type: application change. Extend the normal invite issuance-to-arrival journey and later naming; exclude temporary guest sessions, new resource permission models, and general account redesign. Completion requires a normally generated invitation to work in a fresh browser and with an existing identity on public and members-only sites, plus recovery and later naming. Release only after the acceptance scenarios below pass and destination failure is reproduced or isolated with evidence.

## Risks

- **Identity continuity:** a temporary name may be tied to published identity data. Validate current naming/publication behavior before Step 3; establish a supported same-key naming path before approving implementation dependencies.
- **Access and navigation races:** authentication may override the target or show a gate. Trace and reproduce the normal public/private journey before Step 3; retain one authoritative invite destination through verified access.
- **Consumption and interruption:** automatic processing can consume a link in a JavaScript-capable preview or strand a recipient after a network failure. Inspect redemption/retry guarantees before Step 3; define same-recipient recovery and clearly state that opening redeems.

## Shared component inventory

Reuse/extend invite navigation, the Invites destination selector and generated link/QR output, Lobby redemption/status UI, browser identity preparation and signing, invitation issue/redemption APIs, private-site authentication/return navigation, and Account key/profile naming surfaces. Profile, username, and toolbar identity displays must reflect the eventual chosen name through their canonical identity data. Extend canonical components; no separate guest UI or authorization API.

## Simple user flow

1. An approved member clicks Invite from a page. Destination inclusion starts unchecked; enabling it prefills that page’s URL. The member may edit it, then generate and share the link or QR code.
2. The recipient opens it; identity preparation, redemption, and access verification run automatically with visible progress.
3. The target opens, including behind the member gate; the recipient may choose a name later through Account.
4. On failure, the recipient receives a clear explanation and retry, account, or safe navigation recovery appropriate to the failure.

## Success criteria

Invite starts unchecked from any page; enabling destination inclusion prefills the source page rather than the Invites page, preserves edits across toggles, and unchecked submission includes no target. Fresh and existing identities reach the exact valid target with zero extra clicks/prompts on public and members-only sites; later naming retains the same approved identity; same-recipient interrupted redemption recovers; expired/revoked/other-recipient invites, unsafe targets, and storage failures neither grant unauthorized access nor loop. Untargeted invite redemption and generated link/QR behavior remain functional.
