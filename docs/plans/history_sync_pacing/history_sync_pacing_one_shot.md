# History sync pacing one-shot

Cycle: `v3/history_sync_pacing`; Mode: one-shot; Record/index: this file, [plan index](../README.md)
Debug mode: enabled
Effort study: `railspike-effort` / `v3/history_sync_pacing`; [host ledger](../effort_ledger/history_sync_pacing.md). Standing P2 enrollment.
Extended estimates: enabled (no separate work units)

**Cycle estimate: 0.20**

Status: planned; next permitted action: implement after the planning commit.

## Original request

Handoff from a parallel session (2026-11-24), item C:

> One-shot hotfix, not started: history-sync pacing in public/assets/private_message_history_sync.js.
> - Share retryAt and a recent-request log across windows via localStorage, keyed by account and key digest. Check it in request().
> - Lower the per-turn budget to about 12.
> - Time the next turn to when the oldest request in the last 10 s ages out, instead of after 1 s.
> - During backoff, keep acknowledge batches queued instead of dropping them.
> No server change. Independent of B.

Background from the same handoff: ilyag has 78 approved keys, so a full pass is about 230 requests. The server admits 16 per key and 64 per account in 10 s. The client's per-turn budget is also 16, the next turn starts about 1 s later, each window keeps its own `retryAt`, and acknowledge ignores backoff and is dropped on a 429.

## Authorization

The maintainer passed the handoff on and replied "please continue" (2026-11-24) to the proposed order: finish `private_key_access` Stage 4, then this hotfix C. Scope: the four bullets above, client only. Excluded: server changes, the target filter in B (`history_sync_active_keys`) and the batching in D.

## Outcome and work type

Application change, limited to `public/assets/private_message_history_sync.js` and its browser test. A member with many keys browses with several tabs open and sees far fewer 429 responses from history sync. Read receipts survive a backoff.

## Approach and risks

- One shared pacing record in `localStorage` under `forum_history_pacing:<account>:<key digest>`, holding `retryAt` and the send times of the last 10 s. `request()` checks it before sending, records each send, and writes `retryAt` on a 429. Every tab, and the foreground reads and receipts outside a turn, see the same state.
- Client limit: at most 12 requests per key in any 10 s, below the server's 16. A full window behaves like a 429 the client predicted: it sets `retryAt` to when the oldest request ages out, without sending.
- Per-turn budget 16 → 12; the turn's soft stop 14 → 10.
- `continueLater` waits until the later of `retryAt` and the moment the oldest logged request ages out, and at least 1 s.
- `flushConfirmations` puts the batch back in the queue on a 429 or a predicted backoff, and flushes again after `retryAt`.
- Also, because `private_key_access` Stage 3 (unmerged branch `feat/private-key-access`) answers a public-only key with 403 `reason: public_only`, this hotfix stops history sync for the page on that answer instead of retrying. On `main` the server never sends it, so the change is inert until that branch merges. This is an addition recorded under Enhancement Requests.
- Risk: browsers that refuse `localStorage` (private mode, quota). Mitigation: fall back to in-memory state, as today.
- Risk: a stale `retryAt` far in the future from a bad header or clock jump. Mitigation: cap any stored `retryAt` at 60 s ahead and ignore larger values.

## Reuse decisions

- Extend the existing `request()`, `continueLater()` and `flushConfirmations()` in `private_message_history_sync.js`; no new module.
- Key the record like the existing tab lock `forum-history:<account>:<digest(public key)>`, which uses `ForumPrivateMessageHistoryCrypto.digest`.
- Keep the existing Retry-After parsing.

## Completion conditions

- Entry: any page that starts history sync (the unread navigation element).
- Result: no tab sends a history request while the shared `retryAt` is in the future, and no more than 12 in any 10 s per key across tabs. A 429-deferred receipt is sent after the backoff.
- Recovery: in-memory fallback without storage; continuation still resumes after backoff.
- Release: merge and deploy; Ilya's browsing on zenmemes.com shows fewer 429s in the network panel. Production evidence pending the maintainer.

Concepts touched: [Private-message history](../../concepts/private_message_history.md) (changed: the "Bounded history continuation" entry gains shared pacing).

## Verification plan

- Extend `tests/browser/private_message_history_continuation.cjs`:
  - The turn budget is 12.
  - A second simulated tab, sharing storage, waits out the first tab's 429.
  - Twelve requests inside 10 s hold the thirteenth.
  - A 429 on acknowledge keeps the receipt and sends it later.
  - A 403 `public_only` stops sync.
- Run `./v3 test --smoke PrivateMessageHistoryCryptoTest` plus the other history browser tests through their PHP runners. The full suite is not run.

Starting revision and branch: `fix/history-sync-pacing` from `origin/main` at `270cdbe32`.
Planning commit: pending.

## Study history

- Intake, 2026-11-24 UTC: 0.20 projected total, scope v1. Exposure: the file was read in full before the estimate. Claude single exposed rater; R1 hashes not re-verified.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*
