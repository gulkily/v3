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
  - **Release finding for Stage 8 (maintainer review needed; numbers revised in Stage 2):** 28 `ilyag` keys would be public only. They trace to 10 chain roots that a `guest`, `onthebus` or `test-user` key approved. Confirming those 10 restores all 28, through rule 2: `openpgp:b2d12875…` alone restores 11 and `openpgp:5e15d8a1…` restores 8.
  - Shared agent names make up most of the rest: `claude` 43, `guest` 34, `chatgpt` 13, Grok names 6. These are keys of different people's agents under one name, so leaving them out of each other's messages is the point.
  - Every `chatgpt` key has a `cross_account_key` receipt, so none is first locally. The account's original first key is not approved in this import, so production will differ. Stage 8 reruns the report on the server.
  - Card fatigue is now concrete: Ilya's Unread card would list up to 28 keys. Stage 5 should list waiting keys compactly in one card.
- Estimate details: original scope as planned; uncertainty was legacy receipts, resolved by the import run; no revision.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*

## Stage 2 - The confirm record

**Original estimate: 0.15**
**Retrospective: 0.15**

- Changes:
  - `src/ForumRewrite/Security/PrivateAccessConfirmation.php`: path, parse, acceptance receipt, `read` (signature and receipt checked, as for retirements), and `confirmersFor`, now the default rule 4 source in `PrivateKeyAccess`.
  - `src/ForumRewrite/Security/PrivateAccessConfirmationPolicy.php`: the actor is a same-username key with access (`member`) or an active operator (`operator`); the target is approved, active and public only.
  - `LocalWriteService`: `preparePrivateAccessConfirmation`, `finalizePrivateAccessConfirmation` (a repeat of the accepted request is a no-op) and `privateAccessStatus`, under the write lock like retirement. No read-model rebuild is needed, since access is computed live.
  - Routes: `POST /api/prepare_private_access_confirmation`, `POST /api/confirm_private_access`, `GET /api/private_access_status`. They are in `Application`'s route lists, `ApiRouteCatalog` and `IdentityApprovalAndInvitationApiController::privateAccessConfirmation`.
  - Record families: `SignedPostTransaction` allowlist; `ArchiveRecordCatalog` (record, signature and identity keys); `ContentImportPlanner` excludes them as instance authority, like retirements.
  - Spec: `docs/specs/private_access_confirmation_record_v1.md`.
- Fix to Stage 1, found by this stage's tests:
  - Rule 3 now uses the receipt's `actor_is_operator`, that is, whether the approver was an operator when it approved. Before, it used the approver's current status, so making someone an operator later gave every key they had approved private access. Only legacy grants without a receipt fall back to current status.
  - A root seed under a username now stands as its first key, so the legacy fallback no longer picks a second one.
  - Receipt lookups are memoized per request. `PrivateKeyAccessTest` gained the late-operator and legacy-operator cases.
- Verification:
  - `PrivateAccessConfirmationTest`: 6 tests with real gpg keys and signatures on the `parity_minimal_v1` fixture. They cover a member confirm that survives a full rebuild, a repeat no-op, refused actors (public-only, other account, already private), a wrong signer, a late operator who still confirms, and the archive paths.
  - `./v3 test --smoke PrivateKeyAccessTest PrivateAccessConfirmationTest SignedPostTransactionTest ApiRouteCatalogTest ContentImportPlannerTest ApprovalAcceptanceTest LlmsTxtRendererTest KeyBootstrapTest`: 551 of 551 passed. The full suite was not run.
  - Report rerun on the zenmemes.com import: first 84, operator 63, own_account 51, root 4, public_only 77. Public-only by name: guest 21, claude 20, ilyag 17, chatgpt 6, Grok 3, test-user 2. This replaces the Stage 1 numbers, which wrongly ignored receipts' operator evidence.
- Notes:
  - Imports leave confirmations out. A local clone of zenmemes.com will show confirmed keys as public only unless they are confirmed again there. This matters for `private_message_local_pull` later; recorded as a follow-up, not in scope.
- Estimate details: original scope as planned, plus the Stage 1 rule 3 fix (ordinary correction); no revision.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*

## Stage 3 - Enforcement behind the flag

**Original estimate: 0.15**
**Retrospective: 0.15**

- Changes:
  - New site flag `FORUM_PRIVATE_KEY_ACCESS_ENFORCED`, "Private access needs the account's own key". It is in the ACCESS group, on by default, requires `FORUM_PRIVATE_MESSAGES_ENABLED`, and was released 2026-11-24. `PrivateKeyAccess::enforced(root)` reads it.
  - `ApprovedUserKeyResolver`: non-historical lookups (who new messages are encrypted to) return only keys with private access. Historical lookups, used for verifying old signatures, are unchanged.
  - `PrivateMessageMailboxService::approvedViewer`: a public-only viewer gets `PublicOnlyKeyException`. That covers listing, conversations, unread state, read receipts, recipient keys and send.
  - `PrivateMessageHistorySyncService`: `active()` now also requires private access, which covers every current-key check (`key`, `nextKey`, `hasKey`, `context`). Historical verification is unchanged. A public-only viewer gets `PublicOnlyKeyException` from `context`.
  - Errors: `RouteServices::errorPayload` adds `reason: public_only`. `PrivateMessageApiController` and `PrivateMessageHistorySyncController` return it as 403 rather than "retry later" (503). `InboxPageController::messages` returns a `public_only` state, which Stage 5 renders.
- Inventory of approval checks in the history sync service (the Step 3 risk): `key`, `nextKey` and `hasKey` (non-historical, through `active`), and `context` (through `hasKey`). `eligibleSource` uses historical keys by design: a donor's source must already pass `context`.
- Verification:
  - `PrivateAccessConfirmationTest`: three new tests.
    - The recipient keys hold only mira1 until mira2 is confirmed, while historical lookups keep all three.
    - A public-only viewer has no mailbox, recipient keys or history work, and history never picks it as a target.
    - With the flag off, the result matches plain approval.
  - Existing tests adjusted:
    - `FeatureFlagEvaluatorTest` lists the new flag.
    - `PrivateMessagePageControllerTest` now gives its `eve-key` viewer a profile row. A real viewer always has one; an unknown key is public only.
  - `./v3 test --smoke` with the 21 test classes that touch private messages, recipient keys or history sync: 801 of 801 passed. The full suite was not run.
- Notes:
  - Until Stage 4, Messages shows a public-only key "Messages Unavailable" with the exception's text.
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*

## Stage 4 - The public-only device line

**Original estimate: 0.10**
**Retrospective: 0.12**

- Changes:
  - `PrivateMessagePageController`: the Messages list and conversation pages catch `PublicOnlyKeyException` and render the access page in a public-only state. The server already knows the viewer on these no-store pages, so the state is final on first paint, with or without scripts.
  - `templates/pages/private_message_access.php`: a public-only branch with the heading "Messages", one line ("This device can't read messages yet. Confirm it from another of your devices, or ask an operator."), the short code, Show QR, and the confirm link as selectable text. It has none of the guest or waiting copy.
  - `PrivateKeyAccess::shortCode` (last 12 hex digits in groups of four, the same on every device) and `confirmPath` (`/account/confirm/<fingerprint>`, which Stage 5 serves).
  - `public/assets/private_access_device.js`: turns the path into an absolute URL and shows Show QR only when the QR scripts are present.
  - `public/assets/qr_canvas.js`: invitation QR drawing moved out of `invite_issuance.js` so both places use one helper. `invite_issuance.js` and its browser test now load it, and the lobby script list includes it. Invite behaviour is unchanged.
  - CSS in `private_message_list.css`: monospace code, a QR that shrinks with the screen, and a wrapping URL.
- Verification:
  - `PrivateAccessConfirmationTest`: the public-only key sees the line, code and confirm path on `/messages` and on a conversation URL; its own first key sees the normal list. The short code format is checked too.
  - `./v3 test --smoke PrivateAccessConfirmationTest PrivateKeyAccessTest PrivateMessagePageControllerTest InvitationIssuanceTest PrivateMessageFeatureFlagTest`: 531 of 531 passed. The full suite was not run.
  - Chrome (Playwright 1.63, outside the repository) against the local zenmemes.com import, signed in through a real session as the public-only `ilyag` key `openpgp:19c75f6a…`:
    - Gloss, Word97, Bubblegum and Dark at 375 and 1280 px, scripts on, with the QR shown: no horizontal overflow anywhere, and the line and QR are visible.
    - Gloss at both widths with scripts off: the line, code and relative path show, with no QR button.
    - One defect found and fixed: in Word97, hiding only the button left an empty boxed paragraph, so the script now hides the whole row.
- Notes:
  - Not checked: scanning the QR with a real phone, which belongs to Stage 8. The QR opens `/account/confirm/…`, which returns 404 until Stage 5.
- Estimate details: original 0.10. Retrospective 0.12, from moving the QR helper out of invites and the screenshot pass. New understanding, not changed scope.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*

## Stage 5 - The Confirm / Not me card

**Original estimate: 0.12**
**Retrospective: 0.15**

- Changes:
  - `templates/partials/private_access_waiting.php`: one card listing the account's waiting keys, each with its short code, Confirm and Not me.
    - The heading reads "A new key for <name> wants to read your messages", or "N keys for <name> want to read your messages".
    - One kept warning: "Confirm only if the code matches a device of yours."
    - Five rows show; the rest sit in a "N more" disclosure, per the Stage 1 finding.
  - The card appears on Unread (`inbox.php`) and on Messages (`private_message_list.php`), only for a viewer with private access, through `PrivateKeyAccess::waitingForViewer`. Unread also shows "This device can't read messages yet. Confirm it" for a public-only key.
  - `GET /account/confirm/<fingerprint>` (`PrivateMessagePageController::confirmPage`) is the page the Stage 4 QR opens. It shows the card for that one key, or explains `self`, `has_access` or `not_allowed`. An unknown key gives 404, and a signed-out viewer gets the existing sign-in access page.
  - `public/assets/private_access_confirm.js`: prepare, sign with `ForumBrowserSigning.signCanonicalRecord`, then confirm. On an uncertain failure it keeps the signed request and retries it as is, as `key_retirement.js` does. On success it shows "Confirmed" and hides Not me.
  - Not me links to the key's `/tools/user-access/profiles/<slug>/` page, where the existing "Retire this key…" shows for an eligible same-account key. Retirement itself is unchanged.
  - `public/assets/private_access.css`, attached to Unread, Messages and the confirm page in `TemplateRenderer`.
- Verification:
  - `PrivateAccessConfirmationTest`, new test. It checks:
    - mira1 sees both waiting codes, the Not me links and the confirm script on `/messages` and `/unread`.
    - mira2 (public only) sees no card and gets the Unread line; bob sees no card.
    - Every confirm-page state: confirm, self, not_allowed, 404, and has_access after a confirm.
    - The Not me page offers mira1 the retirement.
  - `./v3 test --smoke PrivateAccessConfirmationTest PrivateKeyAccessTest PrivateMessagePageControllerTest InboxPageTest InboxFeedTest WebServerRoutingTest ApiRouteCatalogTest`: 526 of 526 passed. The full suite was not run.
  - End to end in Chrome (Playwright, outside the repository), on a fixture with real gpg keys: mira1's browser identity in `localStorage` with a real session opened `/account/confirm/<mira2>` and clicked Confirm. It showed "Confirmed", `/api/private_access_status` went from `public_only` to `confirmed`, and mira2 then opened Messages normally. No page errors.
  - Long list on the zenmemes.com import, signed in as an `ilyag` root key: "17 keys for ilyag want to read your messages" with 5 rows and "12 more". Checked in Gloss, Word97, Bubblegum and Dark at 375 and 1280 px, and in Gloss with scripts off: no horizontal overflow.
- Notes:
  - The card lists every waiting key, but confirming a chain root also restores the keys it approved (rule 2). On zenmemes.com, 10 confirms would clear all 17. Listing chain roots first, or saying how many each restores, would cut the taps. Not done; recorded as a follow-up.
  - On Messages the card's `h2` comes before the list's `h1`. That is minor, left as is.
- Estimate details: original 0.12. Retrospective 0.15, for the confirm page's four states and the end-to-end browser check. New understanding, not changed scope.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*
