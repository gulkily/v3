> **Feature plan:** [Step 2](./ui_account_states_step2_feature_description.md) · [Step 3](./ui_account_states_step3_development_plan.md) · [Step 4](./ui_account_states_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_account_states` ([ledger](../effort_estimation_trial.md#cycle-v3ui_account_states)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.95**

## Step 4 entry

- Step 3 approved 2026-10-28 ("Approved Step 3"). Branch `feat/ui-account-states` from `main` at `cba7d54f9`; planning commit `f7ba168da`.
- Whole-cycle projection **0.95 reaffirmed** at entry; scope v1 / R1 / P2 unchanged. Claude, single exposed rater. Stage originals are in the [Step 3 plan](./ui_account_states_step3_development_plan.md); copied below without re-rating.

## Stage 1 - One page with three states

**Original estimate: 0.25**

**Retrospective: 0.25**

- Changes:
  - `templates/pages/account_key.php` restructured for all sites: a Guest card ("You're posting as guest", "Choose a name" still using today's set-up until Stage 2), a member card (name, "This browser's key" with a Waiting for approval or Approved chip, "You can post now…" while waiting, "All of {name}'s posts" linking to `/user/{token}` when approved), everyday rows (Name with Change when username changes are enabled, shortened fingerprint with Copy, Save a copy of this key, Use a key from another device), Advanced (this key's profile, Keys, Link a public key, Storage and privacy) and Remove this key. Every existing `data-*` hook is kept; the backup panel stays as it is until Stage 3, opened on the right tab by its rows.
  - State before first paint: the page script runs as soon as it is parsed (it follows the markup) and sets `data-account-state` to `guest` (no key, or an unnamed `guest` key that is not approved), `waiting`, `approved`, or `member` while approval is unknown. It trusts the server's approval (`data-viewer-identity-id`, `data-viewer-approved`, new) only for this browser's own key; otherwise it reads `/api/get_profile` for the key after paint and shows the chip when known.
  - Without JavaScript the cards and rows are hidden (`@media (scripting: none)`); Advanced and Link a public key still work.
  - Hooks: `data-sky="account"` on the page, `data-tone` on cards and rows, `data-starfield-slot` and the `night` class on the member card; all inert.
  - Page title "Account" (was "Account Key"); the testing-approval error says "Refresh Account".
  - `public/assets/account.css`: card, avatar, chip, group and row styles from theme variables; Approved chip `chip--approved` beside cycle 2's `chip--pending`.
  - `/user/` link token is the lowercased saved username, matching the read model's `username_token`.
- Verification:
  - New `tests/browser/account_states.mjs`: guest from first paint with no key and with an unnamed automatic key; a named approved key shows the member card from first paint and Approved (server-known after the identity hint, or looked up); an unknown key becomes Waiting for approval; `/user/alice` link and shortened fingerprint; the Save row opens the backup panel on Backup; no JavaScript leaves Link a public key usable. Passes. Phone screenshots of all three states reviewed.
  - `php tests/run.php AccountKeyPageHooksTest` (6/6; the old caption test replaced by a first-paint state test, `simple-status-dot` retired) and the account checks in `LocalAppSmokeTest` (`testApplicationRendersCoreRoutes`, `testAutomaticGuestKeypairFlagRendersBrowserRuntimeOption`, `testIdentityHintRouteSetsCookieValue`, `testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces`): pass after wording updates.
  - Browser tests pass: `key_backup_ui` (old badge text checks replaced by state checks), `invite_zero_click` (old caption checks replaced by state checks), `testing_self_approval`, `access_history`, `invite_signing_readiness`.
  - `account_key_approval` fails on the user-access approval page (a cancelled approval proceeds); it fails the same way on a clean `main` worktree, so it predates this cycle. `compose_layout_stability` also fails on `main` (recorded in cycle 2).
- Notes:
  - In the guest state, "Choose a name" still creates a new key, as today. With an unnamed automatic key and username changes enabled, Stage 2 should rename that key instead, so its posts and any invitation stay with it.
  - The You tab is not highlighted on the account page; that comes from cycle 1's shell and is unchanged here.
- Estimate details: as expected; test churn matched the plan.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Stage 2 - Naming without prompts

**Original estimate: 0.20**

**Retrospective: 0.22**

- Changes:
  - Guest card: an optional Name field and Continue replace "Choose a name" and its browser prompt. With no key, Continue creates a key with that name (empty means `guest`). With an unnamed automatic key and username changes enabled, Continue names that key in place, so its posts and any invitation stay with it; with username changes off it creates a new key, as today. Continue with an existing key and no name says "Type a name first."
  - Name row: Change opens an in-place editor ("Posts from this key move to the new name.", Cancel, Save); Escape cancels. `browser_signing.js` saves through the existing signed `prepare_profile_name`/`finalize_profile_name` flow; the `window.prompt` rename stays only as a fallback for pages without the editor (none today). Compose and invitation prompts are unchanged.
  - Retry: a failed rename or key publish shows its message with a Retry button that repeats only the failed step (a failed publish retries publishing, never creates another key). Publish failure reads "Couldn't publish your key."
  - Join hint: a waiting key whose name approved members already use shows "Approved members already use {name}. Posts from this key join {name} once a member approves it."
  - New `GET /api/get_username?username=` (`ApiTextController::getUsername`, route, known-route list, `ApiRouteCatalog`): `Username-Token` and `Approved-Keys` in `Key: value` text, `no-store`; invalid names get 400. Not on the members-only allowlist, so unapproved visitors on private sites get the usual access response.
- Verification:
  - `tests/browser/account_states.mjs` extended: naming a fresh key without any dialog, the join hint for "alice", naming an automatic key in place (fingerprint kept), rename with Escape, a failed save with Retry, and success closing the editor. Passes.
  - `LocalAppSmokeTest::testUsernameApiCountsApprovedKeysForAName` (new), the members-only denial added to `testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces`, `ApiRouteCatalogTest`, `AccountKeyPageHooksTest`: pass.
  - Browser tests pass: `invite_signing_readiness`, `testing_self_approval`, `key_backup_ui`, `access_history`. `invite_zero_click` (rename now through the editor) failed once at its expired-invitation step (a 5-second expiry followed by a 5.1-second wait) and passed on rerun; an `invite_zero_click` flake was already an open item before this cycle.
- Notes:
  - Deviation: no `docs/reference/agent_api.md` entry; that page points to `/api/`, which lists every route from `ApiRouteCatalog`, where the endpoint is documented.
  - **Follow-up noted by the user (2026-10-28):** after a new key signs in, `private_site_auth.js` reloads the page once so the server can render the signed-in state. That reload should not be necessary; candidate improvement for later. The browser test waits for it.
- Estimate details: slightly over; the sign-in reload and the automatic-key naming case took extra test work.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Stage 3 - Save a copy and use a key from another device

**Original estimate: 0.20**

**Retrospective: 0.26**

- Changes:
  - The tabbed backup panel became one native `<dialog>` (`#key-backup`, a bottom sheet on phones) with two modes. **Save a copy of this key**: passphrase with a local strength meter (length and variety, no library), confirmation, Format (Text file or Starfield image) and one **Download copy**. **Use a key from another device**: saved copy, passphrase, **Use this key**. Close or Escape closes it and clears passphrases; the dialog's name follows its mode. The rows, the save prompt and any `#key-backup` link open it (`data-backup-open`); `key_backup_ui.js` keeps its create, verify, download and restore code unchanged.
  - `key_backup_status.js` stores the date of the last saved copy per key (older `'1'` records still count as saved) and announces `forum:key-backup-recorded`.
  - Approved keys see "Save a copy of this key to use it on another device without new approval, and keep your messages. Save a copy" until a copy is saved, then "Copy of this key saved {Mon D}." Waiting and guest keys see nothing.
  - `docs/reference/key_backups.md` rewritten for the name-and-keys model and the new labels.
  - Retired hooks (listed in `AccountKeyPageHooksTest::RETIRED_HOOKS`): the backup tab buttons, their ids and the two per-format download buttons; old tab and panel styles removed from `identity.css`.
  - **Sign-in without reload (found while testing):** on public sites every page that does not yet know the visitor's key carried the "public resume" marker, so `private_site_auth.js` signed in and then navigated to the same page. On the account page that reload could cut off a rename or naming in progress (the test failed intermittently). The account page now opts out of the resume marker and signs in in place (`data-auth-in-place`, `{ navigate: false }`), updating its state from the result and clearing the "Entering the site" text. Members-only sites keep their navigation. On deployments with testing self-approval enabled, the page still reloads once after sign-in so the session-bound "Approve current account" button can render.
  - The chevron on row links no longer reads into their accessible names (`content: "›" / ""`).
- Verification:
  - `key_backup_ui.mjs` in three configurations: text (Chromium → Firefox), `FORMAT=png REENCODE=1 RESTORE_BUNDLE=v5`, and `BROWSER=firefox BUNDLE=v5 MEMBERS_ONLY=true`: all pass, with the existing download, rejection, rollback, wrong-key and privacy checks; the test now opens the dialog by row or `#key-backup`, picks the format by radio, checks the "Strong" hint and a dated record. `key_backup.cjs` passes.
  - `account_states.mjs` extended (save prompt, dialog by name, Escape, dated line, no prompt while waiting, naming without any navigation): passes three runs in a row after the sign-in change.
  - Pass after the sign-in change: `invite_zero_click`, `testing_self_approval`, `access_history`, `invite_signing_readiness`; `PrivateSiteAuthTest`, `AccountKeyPageHooksTest`, `ApiRouteCatalogTest` and the five account smoke tests (14/14).
  - Phone screenshots of the sheet and the dated line reviewed; the inner panel border and wrapped format labels found there were fixed.
- Notes:
  - The in-place sign-in addresses the user's reload note for the account page only; other public pages still reload after resuming a session, and restoring a saved copy still signs in through the reloading path. Recorded as the broader follow-up.
- Estimate details: over the plan by the sign-in race investigation and fix.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Stage 4 - Remove this key

**Original estimate: 0.08**

**Retrospective: 0.08**

- Changes:
  - Remove this key (`data-action="remove-key"`) opens a confirmation dialog. For an approved key: "Your posts stay under {name}. To post as {name} again, a new key will wait for a member's approval, and messages sent to this key can't be read." (the messages clause is dropped when a copy is saved), with **Save a copy first** when no copy is saved; it closes this dialog and opens the save sheet. For a waiting key: "Your posts stay under {name}. A new key with this name will also wait for a member's approval." Then **Remove** (the existing `clear-browser-identity` handler) and **Cancel**.
  - Undo appears as a button beside the removal message instead of only inside Advanced; it uses the existing undo link and its saved copy of the key, name included.
  - The duplicate "Clear Saved Keypair" button in Storage and privacy is gone (`clear-browser-key` retired in `AccountKeyPageHooksTest`); `browser_signing.js` already tolerated its absence.
  - The members-only redirect to the Lobby after removal is the unchanged `clearBrowserIdentity` code.
- Verification:
  - `account_states.mjs`: dialog by name and its text, Cancel keeps the key, Save a copy first switches to the save sheet, Remove clears the key (Guest state), Undo brings back the key and name. Passes.
  - `AccountKeyPageHooksTest`, `BrowserSigningNormalizationTest`, two account smoke tests (90/90); `key_backup_ui`, `access_history` pass.
- Notes:
  - The members-only redirect was not exercised in a browser in this stage; its code is unchanged.
- Estimate details: as expected.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Stage 5 - Sites, themes, copy and closeout

**Original estimate: 0.17**

**Retrospective: 0.20**

- Changes:
  - Theme fixes from review: the save and remove sheets layer the panel over the page background so they stay solid in themes with a see-through panel (Arena); "Save a copy first" and Remove are ordinary theme buttons (they were unreadable in Arena and Word97), Remove marked by red ink and border where the theme allows.
  - `ui_copy_audit_checklist.md` entry "Account page states (all sites)" listing removed and added strings; `ui_redesign/implementation_checklist.md` Cycle 4 ticked, with the "Not backed up" warning recorded as replaced by the approved-key save prompt. No navigation destination changed, so `theme-nav-icons.css` is untouched.
  - `WriteApiSmokeTest` account-link tests updated for "This key's profile".
- Verification:
  - Every permitted theme on all four sites (ZenMemes 14; Chouse, MIT Rap Club, QDB 15 each): phone approved page, save sheet and remove dialog, desktop approved page; guest and waiting in each site's default theme. Automatic checks for horizontal overflow, transparent dialogs and page errors: none after the fixes. Contact sheets per site reviewed. (A long-lived fixture server stalled after about seven themes, so captures ran in batches of seven per server.)
  - Full suite once: 1607 run, 1601 passed, 6 failed. Three also fail on `main` (`testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp`, `PlatformDocsStaticTest`, `ReadModelBuilderTimingTest`). Two were this cycle's wording (`WriteApiSmokeTest` account-link tests), fixed and rerun. `testSignedReactionApiVerifiesCommentLikeAndRetainsBothTimes` failed at a `gpg` signing command and passed when rerun alone, as the same `gpg` step did in the tab bar cycle.
- Notes:
  - The dialogs sit at the top in some themes and as a bottom sheet in others, following each theme's own dialog styling.
- Estimate details: the theme fixes and the batched captures added a little.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Review revision - save reminder removed

- The user asked (2026-10-28) whether the approved-key reminder ("Save a copy of this key to use it on another device without new approval, and keep your messages. Save a copy") was needed when the Save row sits right below it. Removed: the row already opens the sheet, and the sheet states why. The date moved onto the row: "Save a copy of this key · Saved Oct 28".
- Verification: `account_states.mjs` (no date before a copy, "Saved Oct 28" after) and `AccountKeyPageHooksTest` pass; phone screenshot reviewed. `key_backups.md`, the copy record and the redesign checklist updated.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*

## Closeout

- All five stages complete. Whole-cycle projection **0.95** held throughout; separate **1.00 retrospective scope assessment** (stage retrospectives 0.25 / 0.22 / 0.26 / 0.08 / 0.20, not summed). The overrun is the sign-in race found in Stage 3.
- Follow-ups recorded:
  - **Sign-in reload (user, 2026-10-28):** other public pages still reload after resuming a session, and restoring a saved copy signs in through the reloading path; only the account page signs in in place.
  - **Starfield robustness (user, 2026-10-28):** the user expects Starfield images to survive screenshots and quality loss. Today's SFENC v0.2 decoding rejects JPEG conversion and single-byte damage (asserted in `key_backup_ui`), so the sheet tells people to keep the original file. Making it robust is a format change, not a copy change.
  - `invite_zero_click` intermittent failure at its expired-invitation timing (pre-existing open item).
  - `account_key_approval` and `compose_layout_stability` fail on `main` too.
- Pending: real-phone checks, the user's review, merge and deployment (only when asked), maintainer comfortable-fit judgment.
- Dependency delta: zero.

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*
