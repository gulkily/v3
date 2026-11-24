# Private key access Step 4 implementation summary

> **Feature plan:** [Step 1](./private_key_access_step1_solution_assessment.md) · [Step 2](./private_key_access_step2_feature_description.md) · [Step 3](./private_key_access_step3_development_plan.md) · [Step 4](./private_key_access_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/private_key_access`; [host ledger](../effort_ledger/private_key_access.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.95**

## Step 4 entry

- Approved: Step 3 (maintainer, "approved step 3", 2026-11-24).
- Branch `feat/private-key-access` from `origin/main` at `2d7805c8b`. Planning commit `14c370810`.
- Tracking files brought from `plans/private-group-chats` with Stage 1: the [epic checklist](../private_group_chats_checklist.md), the group chats Step 1 draft and debug log, both effort ledgers, and [PF-20261124-F01](../../feedback/items/PF-20261124-F01.txt).
- Projection: 0.95 reaffirmed from Step 3. Stage originals: 0.15, 0.15, 0.15, 0.10, 0.12, 0.08, 0.05, 0.05; not summed.
- Baseline: `./v3 test` (smoke) passed 487 of 487 on `2d7805c8b`.

## Stage 1 - The private-access check

**Original estimate: 0.15**
**Retrospective: 0.15**

- Changes:
  - `src/ForumRewrite/Security/PrivateKeyAccess.php`: `hasAccess`, `reason`, `keysFor`, `waitingFor`, `report`. It applies:
    - Rule 1: a root seed, or the username's first key. A `new_account` receipt under the current name decides the first key. With no such receipt, the earliest key with no receipt at all counts. Keys renamed into the name, and keys with other or invalid receipts, never count as first.
    - Rules 2–4: worked out together until nothing changes. Retired keys still vouch as links in a chain, but have no access themselves.
  - Receipts are found among the replies to a key's bootstrap post, so no index or schema change is needed. Collaborators are injectable for tests.
  - `scripts/private_access_report.php`, wired as `./v3 approval private-access-report [--public-only] [--json]`; listed in `./v3` help and `scripts/README.md`.
  - `tests/PrivateKeyAccessTest.php`: 12 tests covering a single key, cross-account, own device and session bootstrap, the chained fake, operator and root seed, a retired approver, a renamed key, legacy ordering, an invalid receipt, confirm, pending and unknown keys, and the report.
- Verification:
  - `./v3 test --smoke PrivateKeyAccessTest`: passed, 499 of 499 (smoke set plus the new 12). The full suite was not run.
  - Local import of zenmemes.com: `./v3 import-instance zenmemes.com --with-approvals`, then the three roots from `./v3 approval suggest-roots` seeded locally. That gave 10,137 records; 64 keys have no approval path locally.
  - `./v3 approval private-access-report`, 0.15 s: 279 active approved keys. Reasons: first 85, own_account 47, operator 9, root 4, public_only 134.
- Notes:
  - **Release finding for Stage 8 (maintainer review needed):** 28 `ilyag` keys would be public only. They trace to 10 chain roots that a `guest`, `onthebus` or `test-user` key approved. Confirming those 10 restores all 28, through rule 2: `openpgp:b2d12875…` alone restores 11 and `openpgp:5e15d8a1…` restores 8.
  - Shared agent names make up most of the rest: `claude` 43, `guest` 34, `chatgpt` 13, Grok names 6. These are keys of different people's agents under one name, so leaving them out of each other's messages is the point.
  - Every `chatgpt` key has a `cross_account_key` receipt, so none is first locally. The account's original first key is not approved in this import, so production will differ. Stage 8 reruns the report on the server.
  - Card fatigue is now concrete: Ilya's Unread card would list up to 28 keys. Stage 5 should list waiting keys compactly in one card.
- Estimate details: original scope as planned; uncertainty was legacy receipts, resolved by the import run; no revision.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*
