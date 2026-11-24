> **Feature plan:** [Step 1](./history_sync_active_keys_step1_solution_assessment.md) · Step 2 · Step 3 · Step 4

Cycle: `v3/history_sync_active_keys`. Related: [key retirement](../key_retirement/), [session key bootstrap](../session_key_bootstrap/) (the "replaced" key state), and two smaller fixes from the same investigation (see [Related hotfixes](#related-hotfixes)).

Debug mode: disabled

Effort study: `railspike-effort` / `v3/history_sync_active_keys`; host ledger to be created with the Step 1 commit. Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

**Cycle estimate: 0.35** (recommended option A)

## Original Query

After the finding that history sync sends about three requests per target key, and `ilyag` has 78 approved keys: "ok, so we maybe should just ignore keys that are inactive? this sounds like a cycle in itself. let's do it". Then: "but let's also keep those hotfixes in mind."

[Maintainer in session, 2026-11-24.]

## Understood Intent

History sync should stop copying message keys to keys nobody is using, so a full pass costs a handful of requests instead of hundreds, and the 429s in the console go away. A key that comes back into use should get its history again without anyone doing anything.

## Problem

History sync (`public/assets/private_message_history_sync.js`, `PrivateMessageHistorySyncService.php`) walks every approved key of the account as a *target*. For each target, one turn sends `POST work`, `POST transfers` (the wrapped keys) and `POST acknowledge`. The server admits 16 history requests per key per 10 seconds and 64 per account (`PrivateMessageHistorySyncStore::admit`). With 78 targets, a pass is about 230 requests, so every turn runs into a 429, waits, and starts again. Extra windows add to it. The maintainer's console on 2026-11-24 showed repeated 429s on `history_sync/work` and `history_sync/acknowledge`.

Most of the 78 keys are almost certainly dead: browsers whose storage was cleared (Safari's seven-day wipe), old devices, test sessions. Nothing marks them as dead today. The only exclusion is explicit retirement (`KeyRetirement`), which nobody does by hand for dozens of keys.

What the server already knows:

- `viewer_sessions` rows carry `identity_id` and `last_seen_at`. Sessions last 400 days and are not expired for idleness, and `last_seen_at` is refreshed on use (throttled by `TOUCH_INTERVAL_SECONDS`). So "when was this key last signed in and used" is already on disk for any key that has used the browser.
- Keys that only sign posts from a CLI have no session. Those are agent keys, and today none of them can receive encrypted mail anyway (they have no encryption subkey; reported in the Agent Accessibility thread, 2026-11-24).

What must not change: DMs are still encrypted to **every** approved key at send time (`recipient_keys`). A device that comes back after months still gets new mail. This cycle only changes which keys history sync *copies old messages to*.

Concepts touched: private messaging history sync (changed: target selection). Proposed: *active key* (a key used within a window). Feedback: no item yet; one is allocated in Step 4.

## Options

### Option A — Recent sign-in (recommended)

A target is eligible only if a session for that key has `last_seen_at` within a window, default 30 days. The server's `nextKey` / `scheduledTarget` filter on it, with one indexed query against `viewer_sessions`. When a dormant key signs in again, its session's `last_seen_at` moves forward and the next pass includes it; nothing else is needed. A feature flag (default on) turns the filter off, and the window is an instance setting.

- Pro: uses data that's already there; no client change; automatic in both directions.
- Con: a key whose sessions were all signed out (rows deleted) counts as inactive until it signs in again. That's the right answer anyway.
- Risk: a key in a browser that's open but never signs in again within 30 days stops getting *old* history. It still gets all new mail, and catches up the next time it's used.

**Option A estimate: 0.35**

*Claude · 2026-11-24 UTC*

### Option B — Proven history-sync participants

A target is eligible only if that key has itself run history sync as a *source* within the window. The server would record a `last_synced_at` per source on admitted requests. This is stricter than A: it proves a browser holding the private key is actually running the sync code, not just signed in.

- Pro: the tightest definition of "can use the copies."
- Con: needs a new column and a write on every admitted request; a brand-new device gets nothing until it has run one pass of its own, which slows first-day history on a new phone.

**Option B estimate: 0.45**

*Claude · 2026-11-24 UTC*

### Option C — Retire in bulk, no automatic filter

Leave sync as it is and give members a key list (last seen, created, device) with "Retire selected", reusing `KeyRetirement`. Retired keys already drop out of history sync.

- Pro: explicit; also cuts envelope size for new DMs, since retired keys stop being recipients.
- Con: depends on someone doing it; doesn't stop the spam for anyone who doesn't.

**Option C estimate: 0.40**

*Claude · 2026-11-24 UTC*

## Recommendation

**Option A.** It fixes the spam for everyone without anyone acting, and it uses data the server already keeps. Option C's key list is worth doing later as its own cycle. It pairs with the "replaced" state from session_key_bootstrap, and it's the only option that also shrinks new DM envelopes.

Open questions for the maintainer:

1. Is 30 days the right default window?
2. Should the account's *own current* key count as active even without a session row (e.g. a CLI-only key)? Recommendation: no. A key with no session can't run the browser sync anyway.

## Related hotfixes

Kept in scope as separate work, per "let's also keep those hotfixes in mind":

- **`v3/dm_verify_reason`** (one-shot, implemented 2026-11-24, not yet pushed): a DM that fails verification says why. A signature dated ahead of the device's clock reads "Dated ahead of this device’s clock". Independent of this cycle.
- **History-sync pacing and shared backoff** (proposed one-shot, not started): one `retryAt` and recent-request log shared across windows via localStorage, a per-turn budget below the server's 16, the next turn timed to when the oldest request ages out, and `acknowledge` held instead of dropped during backoff. Option A shrinks the work; this stops what's left from tripping the limit, especially with several windows. It doesn't depend on this cycle and can ship before or after it.
- **Request batching** (several targets per `work`/`transfers`, `acknowledge` folded into `work`): deferred. With Option A, there should be few enough targets that it may not be needed. Revisit if 429s remain after A and the pacing fix.

*Claude · 2026-11-24 UTC · Model: claude-opus-5-5*
