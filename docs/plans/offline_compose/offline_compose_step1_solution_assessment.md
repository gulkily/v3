> **Feature plan:** [Step 1](./offline_compose_step1_solution_assessment.md) · [Step 2](./offline_compose_step2_feature_description.md) · [Step 3](./offline_compose_step3_development_plan.md) · [Step 4](./offline_compose_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./offline_compose_debug_log.md))

Effort study: `railspike-effort` / `v3/offline_compose`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 1 approved on 2026-11-11, revised the same day to Options A + B + C, and reaffirmed with Step 2 approval.

Cycle estimate: 0.55

# Step 1: Solution Assessment — Offline compose

## Original Query

Let's work on offline compose and queuing for Zen memes, please.

From the [to-do thread](https://zenmemes.com/threads/thread-20261111012022-7416348e): Ilya was offline earlier today and couldn't write anything. Offline compose for new threads and replies is broken, and this should be high priority. […] Load a thread while online, disconnect, write a reply, reload, reopen the thread. The draft and its reply target are both still there. The same works for a new thread from the board. Anything written offline shows a clear "Saved on this device, not sent" status. When the connection comes back, pending posts get sent, or are offered for sending. Nothing is lost or sent twice.

## Understood Intent

The [5 Nov notes](https://zenmemes.com/threads/thread-20261105202334-ba48d42e) say "we broke offline compose". A replay against a local server at `6012d8a6` (23 Oct, Outbox complete) and at `f3abd425` (today) behaves the same in every case below, so nothing regressed. Writing while offline was never reachable from the places people write:

- Offline, the board and threads open in the offline reader, which has no composer. `/compose/thread` and `/compose/reply` fail with a browser network error, since the worker has no fallback for them. The runbook lists "direct offline navigation to a fresh compose page" as not yet done.
- The inline composers on the board and thread pages render **Save to Outbox** but leave it disabled, because `outbox_compose.js` loads only on the `/compose/*` pages.
- Only a `/compose/*` page opened before the connection dropped can save to the Outbox.
- Pressing Post offline keeps the text on screen, but the board says "Couldn't create your account", which blames the wrong thing.

The text autosaves to the browser, but offline there is no composer that shows it.

## Revised Direction

Ilya asked whether clicking New post on an open page after going offline shows a composer that can save a draft and save to the Outbox. A replay showed it does not: the link leads to the browser's "can't be reached" page. Ilya approved Step 1 and added the overarching theme: his workflow and the site's appearance offline stay as close to the online experience as possible. Ilya then asked to roll Option B in as well: **A + B + C**. The offline board and thread show the same inline composers as online (A). The real `/compose/thread` and `/compose/reply` pages are cached and served offline at their usual addresses (B). Post without a connection saves to the Outbox (C).

## Problem Statement

People can't start or finish writing while offline, because the composer exists only on server pages that need a connection.

## Concepts touched

- [Shared composer](../../concepts/shared_composer.md): changed in every option. It already says the Outbox behaves the same everywhere, which is not yet true.
- Offline reader (`offline_reader.js`): changed in Options A and C.
- Outbox: used unchanged in every option for delivery. Proposed as a concept; none exists yet.
- Offline worker fallbacks (`service_worker.js`): changed in Option B.

## Option A — The offline reader gets the shared composer

**Estimate: 0.55**
Driver: rendering the shared composer from the reader without a server. Uncertainty: reply targets that the snapshot does not include.

The reader's cached shell carries the shared composer markup. The offline board shows the compact New post form, and the offline thread shows Reply under each post. Save goes into the Outbox as a draft, and the thread lists its own unsent drafts as "Saved on this device, not sent". The board and thread pages also load the Outbox script, so their Save to Outbox works when the connection drops mid-draft.

- Pro: writing works on the pages people actually reach offline, which matches the acceptance check.
- Pro: one composer markup, so fields, Markdown and theme choice stay the same.
- Pro: delivery reuses the Outbox send path that already avoids duplicates.
- Con: the reader has to fill in the composer's server-rendered parts (identity, reply context) itself.

## Option B — Cache the compose pages as offline shells

**Estimate: 0.65**
Driver: compose pages read the parent post and identity on the server. Uncertainty: making the worker serve one shell for many query strings.

The worker caches a generic `/compose/thread` and `/compose/reply` shell and serves it offline. The page fills in the reply context from the snapshot.

- Pro: the full compose page works offline, including the Options section.
- Con: Reply on an offline thread links to a second page instead of opening in place.
- Con: the worker would need its first query-string fallback, and every compose-page change would have to keep working without a server.

## Option C — Post offline saves to the Outbox

**Estimate: 0.25**
Driver: telling "no connection" apart from a real server refusal. Uncertainty: low.

Every composer loads the Outbox script. When Post fails for lack of a connection, the draft goes to the Outbox as queued, with "Saved on this device, not sent". The misleading account error goes away.

- Pro: small, and it fixes the most likely loss: writing on an open page, then pressing Post.
- Con: still no way to start writing after going offline, or after reloading offline.

## Recommendation

Option A, with Option C folded in. A covers starting to write offline, and C covers pressing Post when the connection is gone. Both send through the existing Outbox. C works as the first vertical slice: once the board and thread composers load the Outbox, they already save and queue, and the reader composer builds on that. Option B adds a second offline surface for little gain over A.

Related threads, left out of this cycle: [no way to clear the Outbox](https://zenmemes.com/threads/thread-20261108230824-39d176c6), [non-technical summary on /offline/](https://zenmemes.com/threads/thread-20261109180426-5c2c2e9a), and the separate `https_nudge` cycle.

## Study Checkpoint

2026-11-11T13:04:26Z; scope v1; starting revision `f3abd425`; reference R1, hashes not re-verified. Claude, single exposed rater. The Outbox, worker and composer code were read and replayed before rating, so no pristine intake is claimed.

- Intake after inspection and Step 1 handoff: **0.55** projected total for Option A with C.
- Alternatives: A 0.55, B 0.65, C 0.25, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
