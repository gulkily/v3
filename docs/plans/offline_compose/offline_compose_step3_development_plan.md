> **Feature plan:** [Step 1](./offline_compose_step1_solution_assessment.md) · [Step 2](./offline_compose_step2_feature_description.md) · [Step 3](./offline_compose_step3_development_plan.md) · [Step 4](./offline_compose_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./offline_compose_debug_log.md))

Effort study: `railspike-effort` / `v3/offline_compose`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 3 drafted; awaiting approval.

Cycle estimate: 0.80

# Step 3: Development Plan — Offline compose

## Completion Contract

- Entry: the normal New post button, Reply links and Post buttons, after the site has loaded once over HTTPS.
- Outcome: offline, the board, thread and compose pages show the same composers as online. A post written offline is kept, marked "Saved on this device, not sent," and published once after reconnecting, linked to its published post.
- Recovery: a refused post stays in the Outbox with the server's reason and can be edited and resent. A reply to a thread that isn't saved offline can still be written and saved.
- External verification: Ilya replays the acceptance check on zenmemes.com in Chrome on Windows, for Reply and for New post.
- Release: merged and deployed, and that replay passes.

## Key Risks

- **High risk:** a server refusal treated as "offline" and queued forever. Impact: lost posts that look safe. Early validation: Stage 2 tests a refused submit and a network failure side by side. Mitigation: queue only on a network failure.
- **High risk:** duplicate posts on reconnect. Impact: the same text published twice. Early validation: Stage 3 drops responses during sending. Mitigation: send only through the existing Outbox path, which reuses the prepared post on retry.
- Offline pages drifting from online. Impact: a different-looking composer offline, or an old one after a deploy. Early validation: Stage 7 runs the composer layout test offline. Mitigation: one composer partial, refreshed with the reader's revision check.
- Signing offline. Impact: a member's queued post can't be signed. Early validation: Stage 2 queues and reloads offline as a signed-in member. Mitigation: sign at send time, as the Outbox already does.

## Concepts touched

- [Shared composer](../../concepts/shared_composer.md): update to describe offline placements (Stage 8).
- Outbox: propose a new concept entry (Stage 8).
- Offline reader: no concept entry exists; record it as proposed in the Outbox entry rather than adding a second one.

## Stage 1

**Estimate: 0.05**

- Goal: Save to Outbox works on the board and thread composers.
- Dependencies: none.
- Expected changes: the board, board list and thread pages load the Outbox scripts, as the compose pages already do.
- Verification approach: browser check: load a thread and the board, go offline, type, Save to Outbox, and see the item in Tools → Outbox. Run `OfflineOutboxComposeTest` and the smoke set.
- Estimate details: driver: page script lists in three controllers; uncertainty: low.
- Risks or open questions: page weight from extra scripts; check that they load lazily like the existing signing scripts.
- Canonical components/API contracts touched: `outbox_compose.js` (reused), `BoardPageController.php`, `ThreadAndPostPageController.php`, `ForteBoardController.php` (script lists).

## Stage 2

**Estimate: 0.12**

- Goal: Post without a connection saves to the Outbox and says "Saved on this device, not sent."
- Dependencies: Stage 1.
- Expected changes: the composer's submit path treats a network failure (including failure before account creation) as "save as queued". The account error appears only for a real account failure. Server refusals stay in the composer with their message. The browser draft clears only once the item is saved.
- Verification approach: browser check for each composer: offline Post queues the item with the right message; a server refusal does not queue; the item survives an offline reload. Signed-in and anonymous visitors both. Extend `OfflineOutboxComposeTest`.
- Estimate details: driver: several submit paths (signed, unsigned, image, anonymous) in `browser_signing.js`; uncertainty: which ones fail before reaching the network.
- Risks or open questions:
  - Impact: a refusal mistaken for offline. Early warning: the side-by-side test. Mitigation: classify by the fetch failure, never by `navigator.onLine` alone.
  - Image posts can't go to the Outbox. Mitigation: keep the existing "Images cannot be saved to Outbox" message.
- Canonical components/API contracts touched: `browser_signing.js` submit and draft paths, `outbox_compose.js` (`replyItem`, `threadItem`), `outbox_storage.js`.

## Stage 3

**Estimate: 0.10**

- Goal: waiting posts send once on reconnect from any page with a composer, and a thread lists its own unsent replies.
- Dependencies: Stage 2.
- Expected changes: pages with a composer run the existing `processQueuedOutbox` when the connection returns. Thread pages show their unsent replies, marked "not sent", with a link to the Outbox.
- Verification approach: browser check: queue a reply offline, reconnect on the thread page, see it published once and linked. Repeat with dropped responses ten times and count posts. Run `OfflineOutboxSendTest`.
- Estimate details: driver: unsent-reply rendering on the online thread page; uncertainty: low.
- Risks or open questions: two open tabs sending at once. Mitigation: the existing delivery lock.
- Canonical components/API contracts touched: `outbox_sender.js` (reused), `outbox_storage.js` (list), thread page template.

## Stage 4

**Estimate: 0.10**

- Goal: New post and Reply open the real compose pages offline, at their usual addresses.
- Dependencies: Stage 2.
- Expected changes: the worker caches the `/compose/thread` and `/compose/reply` pages, refreshing them with the reader shell on the same revision check, and serves them when a compose navigation fails. Query strings are kept, so the page still knows its target.
- Verification approach: browser check from an open board and thread: go offline, click New post and Reply, and see the compose page. Deploy-simulation check: change the composer, refresh, and confirm the cached page updates. Extend `OfflineNavigationWorkerTest`.
- Estimate details: driver: the worker's first query-string fallback; uncertainty: compose pages that embed per-visitor data.
- Risks or open questions: a cached page carrying one visitor's identity details. Mitigation: cache a visitor-neutral version of each page.
- Canonical components/API contracts touched: `service_worker.js` (`networkFirstNavigation`, refresh list), `ComposeAndAccountKeyController.php` (visitor-neutral render).

## Stage 5

**Estimate: 0.10**

- Goal: the offline Reply page shows the post being answered, and handles threads that aren't saved.
- Dependencies: Stage 4.
- Expected changes: offline, the reply page fills the parent post from the saved snapshot. When the thread isn't saved, it says "This thread isn't saved on this device" and still allows writing and saving.
- Verification approach: browser check for a saved thread, an unsaved thread and a reply to a reply. Reload offline and confirm the draft returns.
- Estimate details: driver: snapshot lookup outside the reader page; uncertainty: low.
- Risks or open questions: the snapshot database is large to open on the compose page. Mitigation: read one post, as the reader already does.
- Canonical components/API contracts touched: `compose_reply.php`, the reader's snapshot query helpers in `offline_reader.js`.

## Stage 6

**Estimate: 0.15**

- Goal: the offline board and thread show the same inline composers as online.
- Dependencies: Stages 2 and 3.
- Expected changes: the reader shell carries the composer partial's markup as a template. The offline board renders the compact New post form and the offline thread renders its reply composer and unsent replies, sharing browser drafts with the online pages.
- Verification approach: browser check: offline board and thread show the composers, Post and Save to Outbox both queue, and text typed online appears offline. Screenshots online and offline side by side at phone and desktop widths.
- Estimate details: driver: wiring composer behavior in reader-rendered pages; uncertainty: scripts that expect server-rendered fields.
- Risks or open questions: identity controls that need the server. Mitigation: show them as they look online and explain on use.
- Canonical components/API contracts touched: `templates/partials/composer.php` (reused), `offline_reader.php`, `offline_reader.js`, `OfflineReaderController.php`.

## Stage 7

**Estimate: 0.10**

- Goal: one browser test replays the full acceptance check.
- Dependencies: Stages 1–6.
- Expected changes: a new `tests/browser/offline_compose.mjs` runs Ilya's check for Reply and New post (online, offline, click, write, reload, reconnect, sent once) and the offline Post refusal case. The composer layout test also runs against the offline pages.
- Verification approach: run both browser tests and the focused PHP tests above, plus the smoke set.
- Estimate details: driver: real worker plus reconnect timing; uncertainty: flaky waits.
- Risks or open questions: headless Chrome is not Ilya's browser. Mitigation: Stage 8 replays on production.
- Canonical components/API contracts touched: `tests/browser/composers.mjs`, `compose_layout_stability.mjs`.

## Stage 8

**Estimate: 0.06**

- Goal: documented, merged, deployed and replayed by Ilya.
- Dependencies: Stage 7.
- Expected changes: update the offline runbook limits, the MVP checklist, the shared composer concept and a new Outbox concept. Final diff review, PR, then a reply in the to-do thread.
- Verification approach: Ilya runs the acceptance check on zenmemes.com in Chrome on Windows, for Reply and New post. Until then the cycle stays pending.
- Estimate details: driver: production replay depends on Ilya; uncertainty: low.
- Risks or open questions: HTTP visitors get no offline mode. Mitigation: out of scope, tracked in `https_nudge`.
- Canonical components/API contracts touched: `docs/runbooks/offline_reading.md`, `docs/plans/offline_outbox/offline_outbox_mvp_checklist.md`, `docs/concepts/`.

## Study Checkpoint

2026-11-11T13:40:00Z; scope v2; starting revision `f3abd425`; reference R1, hashes not re-verified. Claude, single exposed rater. Projected total **0.80** reaffirmed after planning eight stages. Stage work 0.05/0.12/0.10/0.10/0.10/0.15/0.10/0.06, non-additive.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
