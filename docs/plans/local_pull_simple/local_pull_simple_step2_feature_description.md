> **Feature plan:** [Step 1](./local_pull_simple_step1_solution_assessment.md) · [Step 2](./local_pull_simple_step2_feature_description.md) · [Step 3](./local_pull_simple_step3_development_plan.md) · [Step 4](./local_pull_simple_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/local_pull_simple`; [cycle ledger](../effort_ledger/local_pull_simple.md). Standing P2 enrollment; parent `v3/private_message_local_pull`.

Extended estimates: enabled

**Cycle estimate: 0.80**

## Problem

Reading your zenmemes.com DMs in a local clone takes eight fragile steps, because the local key is a new key that can't read your history. It should be one button and one confirmation.

## User Stories

- As a member with a local clone, I want one **Connect to zenmemes.com** button so that my DMs appear locally without registering, approving or trusting anything by hand.
- As that member, I want to confirm on zenmemes.com before my key goes anywhere, so that no page can take it silently.
- As that member, I want local Messages to open instantly on later visits so that I don't watch everything decrypt again.
- As that member, I want **Pull** to fetch new mail later with one click.

## Core Requirements

- **Your key, on request.** The local page opens a zenmemes.com page in a popup. That page sends the browser's key to the opener only after you click to confirm, and only to a loopback origin (`http://127.0.0.1:<port>` or `http://localhost:<port>`). The page and the popup check each other's origin. No server ever receives the private key.
- **One pass.** After the key arrives, Connect does the rest on its own:
  1. it runs the public import, which brings your correspondents' public keys;
  2. it trusts the keys zenmemes.com approves among you and your correspondents;
  3. it pulls your envelopes and read state;
  4. it turns saved copies on.

  Messages decrypt directly with your key. No history keys, transfers or confirmations.
- **Ask before replacing.** If this browser already has a different key on localhost, Connect says whose it is and asks before replacing it.
- **Failures are explained.** Every step has a time limit and fails with a short message plus Details, as in #454.
- **Pull again** fetches only new mail, using the key already here.

## Delivery Scope

Application change in two places:
- **zenmemes.com:** a new key-handoff page.
- **The local start page:** Connect and Pull, the automatic import and trust, and saved copies on.

The card's old steps retire: register, approve, the separate trust step, verify and confirm. Out of scope: the export's transfers field (left unused), history sync itself, and phones (a local clone runs on a computer).

## Completion Boundary

- **Normal entry:** `/local/` from `./v3 start`, in the same browser where you use zenmemes.com.
- **End-to-end outcome:** Connect, confirm in the popup, wait. Local Messages then lists every conversation with readable previews. Reloads show them instantly from saved copies.
- **Recovery:**
  - Popup blocked: the card offers a plain link instead.
  - Popup closed or no answer within 2 minutes: the card says so.
  - Signed out of zenmemes.com or no key there: the popup says so and links to Account.
  - A different key here: the card asks first.
  - Import or pull fails: the step is named, and **Pull** resumes.
- **Release condition:** merged and deployed (the handoff page lives on zenmemes.com). Then Ilya connects a fresh clone on his laptop and reloads Messages three times.

## Known User and Environment

Ilya, Firefox on his laptop, about 60 keys under `ilyag`. His current clone has a second key from the old flow. Connect replaces that key after asking, and the old local approvals stay harmless. Done means: on a fresh clone, his conversations are readable on the first load and stay readable on reload. Evidence comes from his laptop against production.

## Risks

- **A local page asks for the key.** Any program serving a loopback port could open the popup. Mitigation: the popup names the exact origin and needs a click; it never sends without one, and never to a non-loopback origin. Validate early: a test page on another origin and one on an https origin both get nothing.
- **Popup handoff differs between browsers.** Firefox and Chrome both keep `window.opener` for a same-tab popup opened on click. Validate early: Firefox, as Ilya's browser, plus Chromium in the browser check.
- **The automatic import is slow.** It runs as a queued task. Mitigation: show its existing live progress inside Connect, and give it a time limit.
- **Trust without a list.** Mitigation: the confirmation text says it, and only keys zenmemes.com approves are trusted.

## Shared Component Inventory

- **Key restore** (`browser_signing.js` `importAccountPrivateKey`, used by `key_backup_ui.js`): extend by exposing it on `__forumBrowserIdentity`, so the local page installs the received key exactly as a backup restore does.
- **Account key page** (`templates/pages/account_key.php`, `/account/key/`): the handoff page joins it as a small sibling route, `/account/key/share`, reusing its key display.
- **Relay and pull** (`LocalDevelopmentRemote`, `LocalDevelopmentRemoteController`, `LocalMessagePull`): reused for sign-in and export. Remove the actions that are no longer needed (`prepare_identity`, `create_identity`, `verify_list`, `confirm`).
- **Trust** (`trust_candidates`, `trust`): reused automatically, without the list step.
- **Import task** (`import_control.js`, the `instance_import` task): Connect starts it and waits.
- **Feature flags** (`LocalWriteService::setFeatureFlag`, `FORUM_PRIVATE_MESSAGE_CACHE_ENABLED`): reused to turn saved copies on.
- **Error Details** (#454 renderer in `local_message_pull.js`): reused.

## Concepts Touched

- [Local development import](../../concepts/local_development_import.md), messages pull: rewritten for Connect.
- [Browser key backups](../../reference/key_backups.md): note the handoff as a third way to use a key elsewhere.
- Proposed *key handoff*: created.

## User Flow

1. On `/local/`, Ilya presses **Connect to zenmemes.com**.
2. A zenmemes.com popup shows "Use your key (ilyag, …606A) on 127.0.0.1:8000?". He presses **Use it there**, and the popup closes.
3. The card shows its progress in turn: importing public content, trusting keys, pulling messages.
4. It says "Done", with a Messages link. Previews show, and on reload they appear at once.
5. Later, **Pull** fetches new mail.

## Success Criteria

- On a fresh clone, Connect leads to readable Messages with one click on each side and nothing else.
- Every pulled conversation is readable on the first load and on three reloads, with no "Couldn't decrypt preview".
- The handoff sends nothing to a non-loopback origin, or without a click. Tests cover both.
- No private key reaches any server. Tests watch the relay's requests.
- The card's old steps (register, approve, trust list, verify, confirm) are gone.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*

## Enhancement Requests

Carried from Step 1:
1. Saved copies: in scope (saved copies on after the first pull).
2. Re-encryption step: removed by this design.
3. Re-check after approval: removed by this design.

## Study Checkpoint

2026-11-27T21:45Z; scope v2; starting revision `1fc53a90`; R1 (not re-verified); P2 standing enrollment. Claude, single exposed rater.

- Step 2 handoff: **0.80 projected total**, up from 0.65. Reason: new understanding. Correspondents' public keys need the full public import, so Connect has to run and wait for the queued import task. Also, the key-restore function lives inside the Account page and must be exposed. Not hours or measured labor; no approval to advance.
- Ledger update pending, with the planning commit.

Status: Step 1 approved by the maintainer ("approved step 1", 2026-11-27T20:15Z), Option B as recommended.
