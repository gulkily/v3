Cycle: `v3/private_access_waiting_keys`; Mode: one-shot; Record/index: [plans index](../README.md). First of five one-shots from the "9 keys for ilyag want to read your messages" review (2026-11-25); the others are `private_access_confirm_timing`, `private_access_one_evaluation`, `private_access_signing_helper` and `private_access_card_settle`.

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/private_access_waiting_keys`; host ledger [private_access_waiting_keys.md](../effort_ledger/private_access_waiting_keys.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.05**

Status: planned; next permitted action: implement.

Feedback item: none matches (searched `docs/feedback/items/` for private access and the waiting card; PF-20261124-F01 and F02 are different features).

Original request (maintainer, 2026-11-25): "'9 keys for ilyag want to read your messages' — i get the sense that for this module we developed some copies of components we could have reused? the reason i say this is because it performs nowhere as instant on the frontend as on the awaiting users page?" Claude's review found, among others, that `waitingKeys()` is copied verbatim into `InboxPageController` and `PrivateMessagePageController`.

Authorization: Claude proposed one one-shot covering five fixes; the maintainer answered "can you do it in 5 one-shots please" (2026-11-25). This cycle is the duplicated `waitingKeys()`. Exclusions: any change to what the card shows or how Confirm behaves (the other four cycles); merging and deploying are the maintainer's.

Bounded outcome and work type: application change (refactor). One canonical method gives the waiting keys for the card: `PrivateKeyAccess::waitingCardKeys(PDO, ?string $repositoryRoot, array $viewer)`, returning `[]` on any error as both copies do today. Both controllers call it; their private copies go.

Approach and reuse decisions:
- Reuse `PrivateKeyAccess::waitingForViewer()`, which both copies already wrap. The new static method is that wrapper, kept beside it so the card's data has one source. No new class.
- Behaviour is unchanged: same arguments, same swallow-to-empty on `Throwable` (the card is optional; a broken access check must not break Messages or Unread).

Risks: none material. A refactor of two identical five-line methods; the existing page tests render both pages with waiting keys.

Completion conditions: Messages and Unread show the same waiting card as before for a viewer with waiting keys, and none otherwise. Deploy with Deploy now; no schema or flag change.

Concepts touched: [Private key access](../../concepts/private_key_access.md) (used unchanged; the card's data source is now one method).

Verification and recovery plan: `./v3 test --smoke PrivateAccessConfirmationTest PrivateKeyAccessTest InboxPageTest PrivateMessagePageControllerTest`; `git diff --check`. Full suite not run. Recovery: revert the commit.

Starting revisions and branch: `fix/private_access_waiting_keys` from `origin/main` `797971a`.

Planning commit: pending.

Study history:
- 2026-11-25 UTC, intake and execution entry: **0.05**. Recorded after inspecting both controllers, so not a clean intake. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

*Claude · 2026-11-25 UTC*
