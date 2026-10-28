> **Feature plan:** [Step 1](./ui_first_post_identity_step1_solution_assessment.md) · [Step 2](./ui_first_post_identity_step2_feature_description.md) · [Step 3](./ui_first_post_identity_step3_development_plan.md) · [Step 4](./ui_first_post_identity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_first_post_identity` ([ledger](../effort_estimation_trial.md#cycle-v3ui_first_post_identity)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.85**

## Step 4 entry

- Step 3 approved 2026-10-28 ("approved step 3"). Branch `feat/ui-first-post-identity` from `main` at `74d044c1f`; planning commit `33a76fb31`.
- Whole-cycle projection **0.85 reaffirmed** at entry; scope v1.2 (G1) / R1 / P2 unchanged. Claude, single exposed rater. Stage originals are in the [Step 3 plan](./ui_first_post_identity_step3_development_plan.md); copied below without re-rating.
- Uncommitted on entry and carried on the branch: the ledger's pending rows for this cycle (closeout in Stage 8) and two checklist notes (cycle 3 pause, recorded as separate work in Step 1; cycle 5 start, updated in Stage 7). Their links were repaired for the new folder.

## Stage plan

| Stage | Original stage work | Status |
| --- | --- | --- |
| 1 - Keys stay local; no dialogs | 0.15 | done |
| 2 - One registered check; guests shown as guests | 0.15 | done |
| 3 - Composer name row on first paint | 0.15 | done |
| 4 - Change the name from the composer | 0.12 | done |
| 5 - Creating your account, failures and Retry | 0.12 | done |
| 6 - Other signed paths and members-only sites | 0.10 | done |
| 7 - Sites, themes, copy and docs | 0.10 | pending |
| 8 - Acceptance and closeout | 0.06 | pending |

Stage estimates are not summed.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Stage 1 - Keys stay local; no dialogs

**Original estimate: 0.15**

**Retrospective: 0.17**

- Changes:
  - `public/assets/browser_signing.js`: the name prompt and its "Continue with 'guest'?" confirm are gone. A new key takes the stored name, an invitation's temporary name, or `guest`. The Account page's prompt fallbacks (no name field; no inline name editor) are gone too, since its template always has both.
  - Automatic guest keys are created with `publishPublicKey: false` in every case. The "Publish guest key immediately" preference is no longer read or written; a leftover stored value is inert.
  - The background check on Like pages (`ensureStoredIdentityReady`) now skips any key without a matching published fingerprint, so it only verifies registered keys; their readiness is `not_applicable` and the first Like registers.
  - `public/assets/private_site_auth.js`: on public sites, the automatic sign-in on page load skips a key that was never registered. This was the second reader-side registration path (found by the new browser test). Members-only sites are unchanged; Stage 6 covers them.
  - `templates/pages/account_key.php`: the setting and its caption are removed.
  - The debug-only `username_prompt_ms` timing field is removed from both timing logs.
- Verification:
  - New `tests/browser/first_post_identity.mjs` (ZenMemes, automatic guest keys on, 390px): reading the board and a thread creates a local `guest` key and sends no `prepare_identity`, `create_identity` or `link_identity`; the repository head is unchanged. The first Like sends exactly one `prepare_identity` and one `create_identity`, then votes. After a reload, the prewarm reports `ready` and a second Like registers nothing. Any dialog fails the test. **Passed.**
  - `php tests/run.php BrowserSigningNormalizationTest`: 81/81 passed. Updated: no prompt in concurrent preparation; Account setup reads the name field; transient-retry uses a stored name; prewarm of a registered key unknown to the server republishes it. Added: prewarm leaves an unregistered key local. Removed: two tests of the publication preference.
  - `php tests/run.php PrivateSiteAuthTest`: 11/11 passed. Fixtures for registered keys now carry the published fingerprint. Added: an unregistered key on a public resume page sends nothing (fails without the fix).
  - `AccountKeyPageHooksTest` 6/6 passed. `AuthNavigationTest` 3/3 passed. `InvitationIssuanceTest` 10/10 passed. `LocalAppSmokeTest` 153/154 passed; the failure (`testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp`: a method that does not exist) is unrelated to this change.
  - Browser tests passed: `signed_reactions`, `account_states`, `board_compose_focus`, `reply_compose_focus`, `invite_redemption.cjs`, `invite_signing_readiness`, `invitation_issuance.cjs`, `invite_signer_identity.cjs`, `post_retraction`, `fastmod`, `high_traffic_control`, `same_author_collapse`, `key_backup_ui` and `private_message_compose.cjs`. `invite_zero_click` failed once, then passed on rerun (flaky).
  - Fail on `main` too, so not caused by this change: `account_key_approval` (timeout) and `reply_continuation` (timeout at line 147).
  - Registration timing: a ZenMemes-sized copy (`repository.tar.gz`, 1,619 posts, 1,595 commits) was rebuilt in the scratchpad and run on a local PHP server. Three first registrations took 314, 174 and 173 ms (prepare 20–77, sign 3–9, create 150–228). This was local, with no network or lock contention, so it is not phone evidence.
- Notes:
  - The Account page's Continue still registers a new named key. Stage 2 makes it local.
  - The `forum_pki_compose_prompt_cancelled` key no longer has a writer. It stays in backup and clear handling so older backups restore unchanged.
  - The production canary's simulated name prompt (`tests/browser/OpenPgpProductionCanaryTest.mjs`) is left as defensive coverage.
  - Retrospective 0.17 against the original 0.15, because of the extra public sign-in path and its fixture updates.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Stage 2 - One registered check; guests shown as guests

**Original estimate: 0.15**

**Retrospective: 0.14**

- Changes:
  - `browser_signing.js`: new `isRegisteredIdentity()` (a key whose stored published fingerprint matches its fingerprint), exported on `__forumBrowserIdentity`. Identity-hint syncing skips unregistered keys, so the hint cookie never names a key the server doesn't have. The profile link stays hidden for an unregistered key.
  - Account Continue: a registered key is renamed as in cycle 4. Otherwise (no key, or a local key) the page makes a new local key under the typed name and sends nothing. On members-only sites it still registers, because sign-in needs it.
  - `account_key.php`: the page script's `isRegistered()` is true when the stored published fingerprint matches, or when the server's viewer identity is this key. It runs before first paint like cycle 4's state. An unregistered key shows the guest card, now "You’re posting as {name}", and the profile link stays hidden.
  - `toolbar_identity_status.js` (Forte): for a local key, the toolbar shows the name without the filled icon and links to Account instead of a profile.
- Verification:
  - `tests/browser/account_states.mjs` (updated) **passed**. A fresh guest naming "Alice" stays a guest, shows "You’re posting as alice", sends no identity or hint request and opens no dialog. A local key named mouse shows the guest card at parse time. Continue with kim replaces the key (new fingerprint) and sends nothing. The join hint now appears for a registered waiting key, not for a local one. The original cycle 4 checks still pass.
  - `tests/browser/first_post_identity.mjs` (extended) **passed**. The Forte toolbar shows `mouse`, `/account/key/` and logged-out styling for a local key, and sends nothing. After the first Like registers the key, it shows the profile link.
  - `BrowserSigningNormalizationTest` 82/82 passed. Account setup is split into a public-site test (local key, no requests, no hint) and a members-only test (registers, then sets the hint).
  - `AccountKeyPageHooksTest` 6/6 passed. `PrivateSiteAuthTest` 11/11 passed. `LocalAppSmokeTest` 153/154 passed: the guest heading assertion was updated, and the unrelated archive-filename failure remains.
  - Browser tests passed: `signed_reactions`, `key_backup_ui`, `invite_redemption.cjs`, `invite_zero_click`, `forte_activity_parity` and `profile_public_details`.
- Notes:
  - Behavior change from cycle 4: naming a new key on Account no longer registers it, so the "Approved members already use …" hint waits until the first post or Like registers the key.
  - A key the server knows but whose published fingerprint isn't stored shows as a guest until its first action, which re-confirms it. No extra profile lookup was added.
  - The Forte toolbar label is still set by a deferred script, as before this cycle. The server renders "Guest" for a local key, because no hint is sent, so the name appears just after first paint. Stage 7's theme pass checks whether this flash is visible; the composer row (Stage 3) is set before paint.
  - Replacing a local key that already has queued Outbox items is handled with the composer rename in Stage 4.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Stage 3 - Composer name row on first paint

**Original estimate: 0.15**

**Retrospective: 0.15**

- Changes:
  - New `templates/partials/compose_identity_row.php`: "Post as {name} · Change". The server always renders `hidden` with "guest". A small inline script right after the row fills in the saved name and shows it before first paint, so server HTML is the same for every viewer. It follows `forum:identity-changed` and `storage`. Without JavaScript the row stays hidden. Change is wired in Stage 4.
  - The row is included once in `reply_form.php` and `thread_compose_form.php`, just above the actions. That covers board quick compose, thread reply, the New post and reply pages, QDB add, and Forte's reply panel and new post dialog. Collapsed inline composers hide it with their other non-editor parts, as before.
  - `compose.css`: row styles (44px tap height, wraps, long names break). In the phone docked reply panel (keyboard open) the row is one text line. When the visible area is under 320px (landscape keyboard), it is hidden: `reply_viewport.js` adds `reply-viewport-short`.
  - `browser_signing.js`: the old composer status messages "You may be asked to choose a username." (no key) and "Publish your browser identity before signed actions." (local key) are removed. Both were wrong now, and the row already says who you post as.
- Verification:
  - New `tests/browser/compose_identity_row.mjs` **passed**. It covers six composers × three key states (none, local named, registered) × 390/1280px. At parse time, before deferred scripts, every row is shown with the right name (guest / mouse / reaction-browser). Server HTML is the static hidden `guest` row on every composer. There is no page overflow, no dialog and no error. A rename elsewhere updates the row in place, and without JavaScript the row is hidden and the editor unchanged. 390px screenshots of the board quick compose and thread reply were reviewed: the row sits between the editor and Post, with no overlap.
  - `tests/browser/reply_viewport.mjs` (extended) **passed** in light, Word97, Chicago and Midnight. The row is docked below the editor in the portrait keyboard panel and hidden in the 240px landscape case, and the editor and actions still fit.
  - Browser tests passed: `board_compose_focus`, `reply_compose_focus`, `reply_viewport_flow`, `compose_disclosure`, `reply_actions_layout`, `inline_reply_link` and `first_post_identity`.
  - `BrowserSigningNormalizationTest` 82/82 passed. `LocalAppSmokeTest` 153/154 passed (the same unrelated failure).
  - Fail the same way on `main` (`74d044c1f`, checked in a scratch worktree), so not caused by this change: `compose_layout_stability` ("Draft remains intact" shown twice) and `reply_full_card` (Word97 card position).
- Notes:
  - The Forte row and every theme beyond the four above are checked in Stage 7.
  - The first-paint check uses the document's parse point (DOMContentLoaded capture). That is the moment deferred scripts would otherwise change the page.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Stage 4 - Change the name from the composer

**Original estimate: 0.12**

**Retrospective: 0.14**

- Changes:
  - `compose_identity_row.php`: Change swaps the row for a name field and Done, in place. Enter applies and Escape cancels. This is not a nested form: Enter is intercepted so it never submits the post. Errors show in the row, and the editor stays open to retry. The draft is never touched. If the signing tools aren't loaded yet, Done loads them through `ForumLazyComposeSigning` first. Change is hidden for a registered key where renaming is off; `forumBrowserIdentityOptions.usernameChangesEnabled` is new in `layout.php` and `TemplateRenderer`.
  - `browser_signing.js`: Account's rename is moved into a shared `saveRegisteredName()`, and Account keeps its own status and editor handling. New `changeIdentityName()` (on `ForumBrowserSigning`):
    - a local or missing key is replaced by a new local key under the name, sending nothing;
    - a registered key is renamed on the server;
    - a local key with anything in the Outbox is registered and then renamed, so queued signed items keep their signer. `outboxHasItems()` reads the Outbox database and aborts any upgrade, so it never creates the database.
    - A dropped connection shows "Couldn't save your name. Check your connection and try again." instead of "Failed to fetch".
  - `compose.css`: editor and error styles (Done doesn't wrap; the error uses `--status-error`).
- Verification:
  - `tests/browser/compose_identity_row.mjs` (extended, username changes on) **passed**:
    - A new visitor writes a draft, then Change → Mouse → Done: the row shows mouse, nothing is sent, the key is unregistered and the draft is intact. The first reply then sends `prepare_identity` + `create_identity` once and posts, and the server profile has mouse.
    - A registered key: rename with `prepare_profile_name` blocked shows the error in the row, the editor stays open and the draft is kept. Done again saves kim, with the same fingerprint and the `finalize_profile_name` sent.
    - With renaming off, the registered key has no Change.
    - A local key with an Outbox item: Change registers and then renames (`prepare_identity`, `create_identity`, `prepare_profile_name`, `finalize_profile_name`) and keeps the fingerprint.
    - No dialogs or page errors anywhere. Screenshots of the renamed row and the error state (390px) were reviewed.
  - Browser tests passed: `account_states` (shared rename), `invite_zero_click`, `reply_viewport`, `first_post_identity`, `board_compose_focus`, `reply_compose_focus`, `reply_actions_layout` and `compose_disclosure`.
  - `BrowserSigningNormalizationTest` 82/82 passed, `AccountKeyPageHooksTest` 6/6 passed, and `LocalAppSmokeTest` 153/154 passed (the same unrelated failure).
- Notes:
  - Retrospective 0.14 against the original 0.12, because of the Outbox read that must not create the database, and the lazy-loading path for Done.
  - The error in the row is plain text in the theme's error color. Stage 5 adds Retry for creation failures at Post and Like; the row's own retry is Done again.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Stage 5 - Creating your account, failures and Retry

**Original estimate: 0.12**

**Retrospective: 0.12**

- Changes:
  - `browser_signing.js`: during a signed action, key creation and registration both show "Creating your account…". This replaces "Generating browser keypair..." and "Publishing your public key in the background...". New `accountCreationFailure()` turns any failure in key creation or registration into "Couldn't create your account."; the original message stays under details.
  - Board, New post, QDB, Forte and the compose pages: the status line shows the failure with a Retry button that resubmits the same form with the same submitter. The draft stays, and Post anonymously stays in the menu. Inline replies show "Creating your account…" in the composer status while registering; a failure appears on the existing pending reply card with its own Retry.
  - `thread_reactions.js`: the first Like or tag on an unregistered key shows "Creating your account…" ("Preparing identity..." stays for registered keys). Failures, including the library-load ones, add Retry beside the message, which presses the same reaction again. The library-load messages keep their wording.
  - `site.css`: inline Retry buttons (`.compose-identity-retry`, `.reaction-retry`), and the composer status in the error color.
- Verification:
  - `tests/browser/first_post_identity.mjs` (extended) **passed**. Each case holds `create_identity`, sees "Creating your account…", then aborts it:
    - Like: "Couldn't create your account." with Retry; Retry applies the Like.
    - Board quick compose: the same message and Retry; the draft "My first thread" is kept and Post anonymously is still there; Retry posts and opens the new thread.
    - Inline reply: the pending card shows the message; its Retry posts the reply.
    - No dialogs or page errors.
    - 390px screenshots of both Retry states were reviewed. Retry sits inline with the message.
  - `BrowserSigningNormalizationTest` 82/82 passed (one status assertion updated to "Creating your account…"). `PrivateSiteAuthTest` 11/11 passed. `LocalAppSmokeTest` 153/154 passed (the same unrelated failure).
  - Browser tests passed: `signed_reactions`, `board_compose_focus` and `reply_compose_focus` (both include real failure/retry), `compose_identity_row`, `account_states`, `invite_zero_click`, `invite_redemption.cjs` and `private_message_compose.cjs`.
- Notes:
  - Duplicate-post risk on Retry: a composer Retry re-runs the identity check (an already-registered key is not registered again) and starts the post from scratch, because the failure happened before any post was prepared. Inline replies keep the existing continuation, which confirms a prepared reply before resending.
  - In the thread card, the Like Retry ends next to the card's `#` link at 390px. Stage 7 checks this across themes.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Stage 6 - Other signed paths and members-only sites

**Original estimate: 0.10**

**Retrospective: 0.08**

- Changes:
  - `outbox_sender.js`: a reaction signed while offline now registers its key (`ensureActionIdentity`) before `apply_signed_reaction`. Before this, a key kept in the browser would have sent a signature the server could not check. Outbox posts and unsigned reactions already registered first.
  - The other paths needed no code change: messages, invitation issuance and redemption, operator tools (Fastmod, high-traffic control, feature flags), approvals, and members-only sign-in through `private_site_auth.js`. They all go through `ensureActionIdentity` / `ensureReadyIdentity`, which register an unregistered key first and, since Stage 1, never prompt. Stage 1's public-site guard leaves members-only sign-in unchanged.
- Verification:
  - Lobby first: new `tests/browser/lobby_local_key.mjs` (members-only, 390px) **passed**. A local, unregistered key opening `/lobby/` sends `prepare_identity`, `create_identity`, then `authenticate_identity`, stores the published fingerprint and shows "Approval pending." A new visitor goes Lobby → Account → Continue as newbie, is registered there and the Lobby shows "Approval pending." No dialogs or page errors.
  - `OfflineOutboxSendTest` 3/3 passed. New: a signed reaction registers before `/api/apply_signed_reaction`; this test fails without the fix.
  - Browser tests passed: `testing_self_approval` (public and `TEST_MEMBERS_ONLY=true`), `invitation_issuance.cjs`, `railspike_auth_navigation`, `private_message_feature_flag`, `private_message_operator_flag`, `private_message_history`, `fastmod`, `high_traffic_control`, `backup_feature_flags`, `feature_flags_toolbar`, `approval_review`, `root_approval_checks` and `access_history`.
  - `invite_zero_click`: 2 of 3 runs passed on this branch (one timeout). It is also flaky on `main` (2 of 3 there, one assertion failure), so it is recorded as a flaky baseline.
  - `private_message_list_browser` times out on `main` too, so it is not caused by this change.
- Notes:
  - Messaging with a never-registered key isn't a normal path (messages need an approved key). The code path registers first, as above.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*
