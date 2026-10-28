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
