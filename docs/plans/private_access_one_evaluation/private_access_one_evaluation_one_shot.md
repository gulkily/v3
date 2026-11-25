Cycle: `v3/private_access_one_evaluation`; Mode: one-shot; Record/index: [plans index](../README.md). Third of five one-shots from the "9 keys for ilyag want to read your messages" review (2026-11-25). Stacked on `private_access_confirm_timing` (branch `fix/private_access_confirm_timing`, PR #422, not merged), whose timings found the cost this cycle removes.

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/private_access_one_evaluation`; host ledger [private_access_one_evaluation.md](../effort_ledger/private_access_one_evaluation.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.12**

Status: implemented and committed; next permitted action: maintainer review (PR), after #422.

Feedback item: none matches (searched `docs/feedback/items/`).

Original request (maintainer, 2026-11-25): Confirm on the waiting-keys card "performs nowhere as instant on the frontend as on the awaiting users page". Claude proposed reusing one access evaluation per request; "can you do it in 5 one-shots please".

Authorization: "can you do it in 5 one-shots please" (2026-11-25), on Claude's list naming "reuse one `PrivateKeyAccess` per request". Exclusions: the commit path (`SignedPostTransaction` versus the approvals' lighter commit; a later, measured decision); key retirement's identical status re-check (noted, not changed); merging and deploying are the maintainer's.

Scope revision before planning commit (2026-11-25, from the sibling cycle's measurement, recorded here as the scope this authorization covers): the timings in `private_access_confirm_timing` show the access evaluation itself is cheap (`policy` ~4 ms locally), while `status` costs ~94 ms, one more gpg run on the record just verified. "Reuse one evaluation" is therefore carried out as: do not verify the same signature twice in one Confirm, and do not repeat the same lookups inside one evaluation. Reusing the policy's `PrivateKeyAccess` for the answer is not possible: the answer must see the confirm that was just written.

Bounded outcome and work type: application change.
1. After `finalizePrivateAccessConfirmation()` verifies the signature and commits the record, it tells `ReactionSignature` that these exact bytes verified, so the status answer, and the next Messages or Unread load (persistent `SignatureCheckCache`), skip gpg for this record.
2. Inside one `PrivateKeyAccess`, `isOperator` and `confirmersFor` lookups are memoized like `receiptFor` already is; the fixed-point loop and rule 1 call them repeatedly for the same keys.

Approach and reuse decisions:
- Reuse `ReactionSignature`'s own cache (`self::$cache` and `SignatureCheckCache`) rather than a second cache. A new `ReactionSignature::rememberVerified()` builds the cache key exactly as `status()` does, from the key file `status()` would read, and remembers only when that key's bytes and fingerprint are the ones the writer verified with. Otherwise it does nothing and `status()` verifies as before. It never records a check that was not made with the same inputs.
- Reuse the memo pattern of `receiptFor` in the `PrivateKeyAccess` constructor.

Risks:
- **A wrong cache entry would let an unverified record read as verified.** Mitigation: the entry is written only after a successful `verifyDetached()` and only when key bytes and fingerprint match what `status()` uses; a test checks that a mismatched key writes nothing.
- **Memoizing `confirmersFor` inside one instance hides a confirm written later.** Instances are per request, and the writer already builds a fresh one for the answer after the commit; a test confirms the answer shows the new access.

Completion conditions: a Confirm click returns `has_access: true` with `status` far below `verify` in `Server-Timing`; Messages and Unread still show the right card. Deploy with Deploy now; no schema or flag change.

Concepts touched: [Private key access](../../concepts/private_key_access.md) (used unchanged).

Verification and recovery plan: `./v3 test --smoke PrivateAccessConfirmationTest PrivateKeyAccessTest ReactionSignatureTest` (`ReactionSignatureTest` does not exist; the cache tests live in `SignatureCheckCacheTest`) plus a test for the mismatched-key case; a local timing run to compare with the sibling's 94 ms `status`; `git diff --check`. Full suite not run. Recovery: revert the commit; the cache entries are only ever true statements.

Starting revisions and branch: `fix/private_access_one_evaluation` from `fix/private_access_confirm_timing` `e18d1de`.

Planning commit: `e78b0c0` (plan and index entry only, before any implementation).

Study history:
- 2026-11-25 UTC, intake and execution entry: **0.12**. Recorded after inspecting the code and measuring, so not a clean intake. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

Dated execution history:
- 2026-11-25: `ReactionSignature::rememberVerified()` added. It remembers a check in the same memory and persistent caches `status()` uses, and only when the stored `.asc` equals the verified signature, no `.sig` sits beside it, the fingerprint is the author's, and the stored public key's bytes equal the key the writer verified with. Addition.
- 2026-11-25: `finalizePrivateAccessConfirmation()` calls it right after the commit, before the status answer. Addition.
- 2026-11-25: `PrivateKeyAccess` memoizes `isOperator` and `confirmersFor` per instance, as `receiptFor` already was. Correction of repeated work.
- 2026-11-25: tests: `SignatureCheckCacheTest::testAWritersOwnCheckIsReusedWithoutGpg` (no gpg run in this request or, from the persistent cache, the next), `testAWritersCheckWithOtherInputsRemembersNothing` (different key bytes, fingerprint or signature: gpg still runs); `PrivateKeyAccessTest::testEachKeyIsLookedUpOncePerCheck` (nine waiting keys; failed before the memo with 19 operator lookups for 11 keys, passes after).

Study history, closeout:
- 2026-11-25 UTC: retrospective scope assessment **0.12**. Delivered as revised before the planning commit. Claude, single exposed rater. Maintainer judgment pending; measured time unknown.

Outcome: delivered. Locally (one run, small test repository), finalize's `status` phase fell from 94.2 ms to 4.1 ms and the lock hold from 311.6 ms to 225.3 ms; `verify` (86 ms) and `commit` (127 ms) are unchanged and are now most of a Confirm. The next Messages or Unread load also finds the new record's check in the persistent cache.

Verification:
- Passed: `./v3 test --smoke PrivateAccessConfirmationTest PrivateKeyAccessTest SignatureCheckCacheTest InboxPageTest PrivateMessagePageControllerTest`: 532 run, 532 passed. Full suite not run.
- Passed: the memo test fails with the old `PrivateKeyAccess.php` (stashed) and passes with the new one.
- Passed: `php -l`, `git diff --check`.
- Unverified: production timings; check `Server-Timing` on a Confirm after deploying #422 and this PR.
- Inapplicable: markup, themes, icons, schema, flags.

Final review: the diff touches `ReactionSignature.php`, `PrivateKeyAccess.php`, `LocalWriteService.php`, two tests, this record, the plans index, the ledger and the debug log. One cache, reused.

Follow-up noted, not done: `finalizePreparedKeyRetirement()` re-reads its record through `KeyRetirement::read()` in the same way and would gain from the same call. The commit path (about 7 git calls through `SignedPostTransaction`, against 3 for approvals) is the next measured decision.

Commit/merge/publication status: completion commit pending at the time of writing; branch `fix/private_access_one_evaluation`, stacked on `fix/private_access_confirm_timing` (#422); PR to be opened against `main`, to merge after #422; not merged.

Debug: [debug log](./private_access_one_evaluation_debug_log.md).

Enhancement requests:
- Same signature reuse for key retirement. Addition, proposed by Claude. Not approved. Not done.

Attribution: Claude · 2026-11-25 UTC · Model: claude-opus-5-5
