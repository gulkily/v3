> **Feature plan:** [Step 1](./offline_compose_step1_solution_assessment.md) · [Step 2](./offline_compose_step2_feature_description.md) · [Step 3](./offline_compose_step3_development_plan.md) · [Step 4](./offline_compose_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./offline_compose_debug_log.md))

Effort study: `railspike-effort` / `v3/offline_compose`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 2 approved on 2026-11-11; Step 3 drafted.

Cycle estimate: 0.80

# Step 2: Feature Description — Offline compose

## Problem

People can't write while offline. New post and Reply lead to pages that need a connection, and the composers already on the board and thread pages can't save to the Outbox.

## Guiding principle

Ilya's workflow and the site's appearance offline stay as close to the online experience as possible. The same buttons lead to the same addresses and show the same composer.

Approach (Step 1, revised): Options A + B + C. The offline board and thread pages carry the same inline composers as online. The real compose pages are cached and served offline at their usual addresses. Post without a connection saves to the Outbox.

## User stories

- As a member who goes offline with the site open, I want New post to open the same compose page so that I can write without changing habits.
- As a member reading a saved thread offline, I want Reply to work as it does online so that I can answer while I'm thinking about it.
- As a member who presses Post without a connection, I want the post kept and clearly marked as not sent so that I don't lose it or think it went out.
- As a member back online, I want my waiting posts sent once, without duplicates, so that I don't have to resend them by hand.

## Core requirements

- Offline, the board and thread pages show the same inline composers as online.
- Offline, New post and Reply open the cached compose pages at their normal addresses, with the same fields, Options, buttons and layout as online. Reply shows the post being answered when the saved copy has it.
- Text typed into a composer, online or offline, is still there after an offline reload. It uses the same browser draft as the online composer.
- Pressing Post without a connection saves the post to the Outbox as waiting to send, and says "Saved on this device, not sent." Save to Outbox works on every composer, including the board and thread ones.
- A thread lists its unsent replies, marked as not sent, and the board's Outbox count shows waiting posts.
- Back online, waiting posts are sent through the existing Outbox path, with no duplicates, and each one links to its published post.

## Delivery scope

Work type: application change.

## Completion boundary

- Entry: the normal New post button, Reply links, and Post buttons, with the site loaded once over HTTPS.
- End-to-end outcome: a post written offline is published once after reconnecting and links to its published post.
- Recovery: a refused post stays in the Outbox with the server's reason, and the text can still be edited and resent.
- Release: Ilya's acceptance check passes on zenmemes.com in Chrome on Windows. Load a thread online, disconnect, click Reply, write, reload, reopen, reconnect, and see the reply sent once. The same check runs for New post from the board.

## Known user and environment

Ilya, on zenmemes.com, in Chrome on Windows (and phone). The reported failure: offline, he couldn't write. His next action is to click New post or Reply as usual. Success is the composer appearing, the post marked "not sent", then published once after reconnecting. The evidence is a replay on production in that browser; a local headless pass alone does not satisfy it.

## Risks

- **Reply to a thread that isn't saved offline.** Impact: Reply leads to a blank page. Validate early: open a Reply link for a thread outside the snapshot. Mitigation: show the composer with "This thread isn't saved on this device", and still allow saving the reply.
- **Signing offline.** Impact: a signed-in member's queued post needs the key and signing scripts. Validate early: queue offline, then reload offline. Mitigation: sign at send time, as the Outbox already does, and keep the post unsigned locally until then.
- **Offline composers drifting from online.** Impact: after a later change or a deploy, an offline composer stops matching the online one. Validate early: run the existing composer browser test against the offline pages, and deploy a composer change to see what offline serves. Mitigation: render the same composer markup, not a copy, and refresh the cached compose pages on the reader's existing revision check.
- **Unknown Post failure vs no connection.** Impact: a server refusal is mistaken for being offline and queued forever. Mitigation: queue only on a network failure; keep refusals in the composer with their message.

## Shared component inventory

- [Shared composer](../../concepts/shared_composer.md) (`templates/partials/composer.php`): reused unchanged as the only composer markup, cached with the reader shell.
- Outbox (`outbox_store.js`, `outbox_storage.js`, `outbox_compose.js`, `outbox_sender.js`): reused. `outbox_compose.js` extends to the board and thread composers and to Post failing offline.
- Browser drafts (`composeDraftKey` in `browser_signing.js`): reused, so offline and online share one draft per form.
- Compose pages (`ComposeAndAccountKeyController.php`, `compose_thread.php`, `compose_reply.php`): cached as offline shells. Their server-filled parts (reply context, identity) are filled from the snapshot and the browser when offline.
- Offline worker (`service_worker.js`): extended to cache the compose shells and serve them for `/compose/thread` and `/compose/reply`, whose addresses carry a query string.
- Offline reader (`offline_reader.js`, `OfflineReaderController.php`): extended to render the board and thread inline composers and list unsent replies.
- Post error path (`browser_signing.js`, "Couldn't create your account"): changed so no connection is reported as such.

No new equivalent component is planned.

## User flow

1. Ilya has the site open, and the connection drops.
2. He clicks New post. The compose page opens at its usual address.
3. He writes and presses Post. The composer says "Saved on this device, not sent," and the Outbox count goes up.
4. He opens a saved thread, clicks Reply, writes, and reloads. The text is still there.
5. The connection returns. Waiting posts are sent once, and each links to its thread.

## Success criteria

- Every step of the acceptance check passes on production in Chrome on Windows, for both Reply and New post.
- No duplicate post across ten send attempts with dropped responses.
- The offline compose pages pass the existing composer layout browser test.
- Post without a connection never shows an account error.

## Study Checkpoint

2026-11-11T13:30:00Z; scope v1 → v2; starting revision `f3abd425`; reference R1, hashes not re-verified. Claude, single exposed rater. Step 1 approved, then revised to A + B + C. Projected total 0.55 → **0.80**: changed scope (Option B added: cached compose pages with offline-filled reply context and identity), plus the stale-shell refresh.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
