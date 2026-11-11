# DM instant load Step 1 solution assessment

> **Feature plan:** [Step 1](./dm_instant_load_step1_solution_assessment.md) · [Step 2](./dm_instant_load_step2_feature_description.md) · [Step 3](./dm_instant_load_step3_development_plan.md) · [Step 4](./dm_instant_load_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/dm_instant_load`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

Cycle estimate: 0.70

## Original Query

This is in addition to our task to make this page load instantly rather than re-decrypt the messages every time.

(Context: a DM conversation page on phone first shows a "…" placeholder while every message is decrypted again on each visit. The maintainer approved the overall DM page plan, which puts instant load first and layout work after it.)

## Understood Intent

Opening a conversation should show its messages on first paint, as on a native chat app, instead of decrypting the whole visible history on every visit.

## Problem

Each visit loads OpenPGP, re-parses the browser's private key and the sender's keys for every message, and decrypts every envelope before anything readable appears, so the page always starts empty.

Concepts touched: [Private-message history](../../concepts/private_message_history.md) (reading path only; history sync unchanged). A "local message cache" concept is proposed for the inventory if Option B or C is chosen.

## Option A — Faster decryption, nothing persisted
**Estimate: 0.30** — driver: profiling and reordering the reader; uncertainty: how much time is OpenPGP loading versus per-message work.

Keep plaintext in memory only, as the history-sync findings recorded. Start loading OpenPGP earlier, parse the private and sender keys once per page instead of once per message, and decrypt the newest messages first so the bottom of the conversation appears before older history.

- Keeps the recorded rule that plaintext never reaches persistent browser storage.
- Helps every visit, including first visits on a new device, and the other options build on it.
- Still not instant: every visit waits for OpenPGP to load and at least one decryption before text appears.

## Option B — Encrypted local cache of decrypted messages
**Estimate: 0.70** — driver: cache storage, invalidation and identity-removal wiring; uncertainty: reading the cache early enough to look final on first paint.

Store each decrypted message in browser storage, encrypted with a key the browser generates and cannot export. On the next visit, show cached messages immediately and decrypt only messages that aren't cached yet. Removing the browser identity also deletes the cache and its key. Includes Option A's speedups for uncached messages.

- Instant on repeat visits: cached text needs no OpenPGP and one fast local decryption per message.
- Changes the recorded "browser memory only" rule. Exposure barely grows in practice, since the private key that can decrypt everything already sits in this browser's storage; the cache is also unreadable outside this browser.
- Async storage reads land after the first frame; the thread needs a short reserved hold so it doesn't visibly jump, per the "final on first paint" rule.
- Needs invalidation for retracted messages and verification state, so a cached message never shows as fresher or more trusted than it is.

## Option C — Same-tab session cache only
**Estimate: 0.40** — driver: tab-scoped storage and back/forward restoration; uncertainty: Safari's back/forward cache behavior.

Keep decrypted messages for the life of the tab only, so going back to a conversation within one session is instant, while a new tab or app launch decrypts again.

- Smaller change to the storage rule; nothing outlives the tab.
- Misses the most common case on phone: opening the site fresh and tapping into a conversation.

## Recommendation

Option B, built on Option A. Ship A's speedups as the first slice (useful alone, no storage change), then the encrypted cache behind a feature flag so it can be turned off if a problem appears. This needs the maintainer's explicit decision to amend the "plaintext in browser memory only" rule for this cache; if declined, Option A alone is the fallback.

Enhancement requests: DM page layout, header, composer and message styling from the same plan are separate direct work, not part of this cycle.

## Study snapshots

- Intake (after code inspection; no pristine intake): 0.70 projected total for the recommended scope. Baseline `6d0f9da1c4c12b3a1107d74543227d167c252417`, scope v1, R1, P2; Claude single exposed rater, 2026-11-11 UTC.
- Step 1 handoff: 0.70 reaffirmed for Option B with A. R1 hashes not re-verified in this session.

*— Claude · 2026-11-11 UTC · Model: Claude Opus 5.5*
