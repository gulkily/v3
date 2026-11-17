> **Feature plan:** [Step 1](./session_key_bootstrap_step1_solution_assessment.md) · [Step 2](./session_key_bootstrap_step2_feature_description.md) · [Step 3](./session_key_bootstrap_step3_development_plan.md) · [Step 4](./session_key_bootstrap_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/session_key_bootstrap`; [host ledger](../effort_estimation_trial.md#cycle-v3session_key_bootstrap). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.80**

# Step 4: Implementation Summary — Session key bootstrap

Step 3 approved 2026-11-16. Branch `feat/session-key-bootstrap` from `origin/main` (`65b98d00`). Planning commit `d1ee94f9`.

PR #274 was still open at Step 4 entry. This branch starts from `main` and rebases onto it if it merges first.

Published as PR #289 after Stage 6. A trial merge with `origin/main` at `c010efca` was clean, and the bootstrap tests, the smoke set (493 of 493) and the browser check passed on it. Stage 7 and the closeout wait for deploy.

## Enhancement Requests

- 2026-11-16, Ilya: "Remove this key" keeps the session open, so we need a way to log out. Disposition: no change needed; Step 3 Stage 7 was wrong. "Remove this key" already calls `/api/clear_identity` before it deletes the key, so it logs out. A deliberate removal therefore never bootstraps a key. Stage 4 adds a test that holds this. Stage 7 needs another way to drop only the stored key (see Stage 7). Approval: not needed (in-scope correction).

- 2026-11-16, discovered in Stage 2: Step 2 says retracting the grant removes membership, but v3 refuses to retract identity, approval and invitation records. Asked Ilya; he chose both: retiring the bootstrapped key is the ordinary undo, and operators alone may also retract the bootstrap record. Disposition: changed requirement, approved by Ilya in chat 2026-11-16; implemented in Stage 2. The Step 2 text stays as approved; this record supersedes its "retracting it" wording.

## Study history

- Step 4 entry, 2026-11-16 UTC: Step 3 projection **0.80** reaffirmed. Scope v2. Stage originals copied from Step 3; copying is not a new rating. Single exposed rater: Claude.

## Stage 1 - Session fingerprint and flags

**Original estimate: 0.12**

- Changes:
  - `DeviceFingerprint.php` (new): browser family, OS family and device class from the User-Agent, such as `safari/ios/mobile`. Only known, equal values match.
  - `ViewerSessionStore.php`: two columns, `device_fingerprint` and `key_bootstrapped_at`, added in place to older stores. Sign-in records the fingerprint; signing in again as the same identity refreshes it. `markKeyBootstrapped` succeeds once per signed-in session.
  - `ViewerSession.php`, `ViewerSessions.php`: carry both values; `isHttps` and `markKeyBootstrapped` for Stage 3.
  - `AuthApiController.php`: records the fingerprint at sign-in and answers `device=recorded`.
  - `private_site_auth.js`: remembers `device=recorded` per key in `forum_session_device_recorded`.
  - `key_bootstrap.js` (new), `layout.php`, `TemplateRenderer.php`: with the flag on, every page loads a small script. A browser with a published key and no record signs in again once, by force, after loading the signing scripts. Pages without a key make no requests.
  - `FeatureFlagRegistry.php`: `FORUM_SESSION_KEY_BOOTSTRAP_ENABLED` and `FORUM_SESSION_KEY_BOOTSTRAP_HTTP_ENABLED` (needs the first), both off, in the Access group.
  - Pre-existing failures fixed: two `FeatureFlagsBehaviorTest` tests also failed on `main`. The Pending chip flag lacked its Experience group, and the toolbar fixture did not pass `$indent` to nested partials.
- Verification:
  - Passed: `./v3 test --smoke ViewerSessionStoreTest DeviceFingerprintTest KeyBootstrapScriptTest PrivateSiteAuthTest FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest FeatureFlagChangePageTest AuthNavigationTest`. The full suite was not run.
  - Passed: the home page renders no bootstrap script with the flag off, and the script with hashed signing-script paths with it on.
  - Baseline: the smoke set passed on `main` (468 of 468). `FeatureFlagsBehaviorTest` failed on `main` (2 of 4) and passes now.
- Notes:
  - Names: Step 3 said `session_key_bootstrap` and `session_key_bootstrap_http`; the registry uses the project's `FORUM_…_ENABLED` form.
  - The identity hint cookie lasts 30 days. Stage 4 relies on it to spot a keyless member without a request.
- Estimate details: original scope as in Step 3; uncertainty was iPad parsing, which turned out simple. No revision.

**Retrospective stage work: 0.14** (the pre-existing test fixes added a little)

*— Claude · 2026-11-16 UTC · Model: claude-opus-5-5*

## Stage 2 - Session-sourced grant and replay

**Original estimate: 0.15**

**Revised estimate: 0.22** (2026-11-16, Claude; the undo path grew to cover operator retraction; changed requirement approved by Ilya)

- Changes:
  - `KeyBootstrapReplay.php` (new): finds bootstrap records (`Board-Tags: identity key-bootstrap`, `Key-Bootstrap-From-Identity-ID:` line) and turns those with a `session` receipt into approval candidates.
  - `ApprovalAcceptance.php`: receipt kind `session`. It must match the record: the new key signed it, it names the actor, and the classification is `own_account_key`.
  - `ReadModelBuilder.php`, `IncrementalReadModelUpdater.php`: both builders add bootstrap candidates beside invitation candidates. The incremental loader gives bootstrap records a source path.
  - `LocalWriteService.php`: `prepareKeyBootstrap` and `finalizeKeyBootstrap`. They reuse the approval policy, the prepared-post store, `commitApprovalAcceptance` and accepted-approval recovery. A session can add a key only under its own username.
  - Operator-only retraction: `RetractedPosts::retractable()` is now the single rule. `RetractionDeletion.php` deletes the receipt with the record. Retracting a bootstrap record refreshes approval state in full.
  - Specs: new `docs/specs/key_bootstrap_record_v1.md`; `approval_acceptance_record_v1.md` lists the `session` kind.
- Verification:
  - Passed: `KeyBootstrapTest` (5 tests). Covered: a grant applied incrementally and by a fresh rebuild; no approval seed; a forged actor stops the rebuild; a cross-account receipt is rejected; no receipt, no grant; a signature from the wrong key is rejected; a retry returns the accepted commit; another username, self and unapproved session keys are refused; the author cannot retract; an operator can, and that removes the receipt and the grant in both builds; the lost key retires a stolen bootstrapped key.
  - Passed: `./v3 test --smoke` plus `ApprovalAcceptanceTest ApprovalPolicyTest ApprovalReceiptMaintenanceTest ApprovalHistoryTest InvitationIssuanceTest AcceptedRetractionTest ReadModelPostRetractionsTest RetractionTransactionTest RetractionReceiptTest RemovalReceiptTest ReactionRetractionTest RetractionMaintenanceTest` (527 of 527).
  - Passed: the 58 `WriteApiSmokeTest` methods about approval, retraction or retirement.
  - The full suite was not run.
- Notes:
  - Read the retraction handoff (`retraction_simplification_handoff.md`) before the retraction change. The change keeps its direction: the record is deleted and a retraction record is kept.
  - No read-model schema change in this stage.
  - Session checks (HTTPS, device, one per session) belong to Stage 3; the writer trusts its caller for them, as the spec states.

**Retrospective stage work: 0.24**

*— Claude · 2026-11-16 UTC · Model: claude-opus-5-5*

## Stage 3 - Bootstrap endpoint

**Original estimate: 0.12**

- Changes:
  - `KeyBootstrapApiController.php` (new): `GET /api/key_bootstrap_status`, `POST /api/prepare_key_bootstrap`, `POST /api/finalize_key_bootstrap`. Each checks the flag, the scheme (HTTPS, or HTTP with its flag), a signed-in session, one bootstrap per session, an approved and unretired session key, and the browser description. POSTs must carry this site's `Origin`.
  - A mismatch answers `pending` with reason `device`; nothing is written, and the new key stays pending for the existing approval tools.
  - The session is marked before the commit, so two requests cannot both mint a key; a failed commit clears the mark. A repeat of an accepted finalize returns the accepted result through a recovery-only writer call, so a second prepared record can never ride in as a "retry".
  - `ViewerSessionStore.php`, `ViewerSessions.php`: `clearKeyBootstrapped`.
  - `Application.php`: routes; `ApiRouteCatalog.php`: three entries.
  - `tests/Support/TestViewerSession.php`: `signIn` takes an optional browser description.
- Verification:
  - Passed: `KeyBootstrapTest` (8 tests). New cases: an iPhone session gets an approved key; the second call, status and prepare refuse with `used`; a repeat finalize recovers. Mismatch and no recorded description give `pending`; HTTP is refused without its flag and allowed with it; flag off gives `off`; another origin gets 403; a signed-out session ("Remove this key") gives `signed_out`; a retired session key gives `not_member`.
  - Passed: `./v3 test --smoke KeyBootstrapTest ViewerSessionStoreTest ApiRouteCatalogTest WebServerRoutingTest AuthNavigationTest PrivateSiteAuthTest` (479 of 479). The full suite was not run.
- Notes:
  - Found and closed during review: a retry check based only on the record path would have let a second prepared record through. The writer's recovery-only mode prevents it, and the test repeats an accepted finalize.
  - Members-only sites need no route exception: the session's key is approved, and identity creation routes are already open.

**Retrospective stage work: 0.13**

*— Claude · 2026-11-16 UTC · Model: claude-opus-5-5*

## Stage 4 - Browser flow and notice

**Original estimate: 0.12**

- Changes:
  - `key_bootstrap.js`: a page with no stored key, whose `identity_hint` cookie names a key, asks `/api/key_bootstrap_status` once per hinted key. When eligible, it loads the signing scripts and makes and publishes a key under the session's username with `ensureReadyIdentity`. It then prepares, signs and finalizes the record, signs the session in as the new key and shows one notice. A `pending` answer shows the waiting notice and finalizes nothing.
  - The notice reuses the app version banner (`app-version-banner` classes): "This browser lost its key and has a new one." with Save a backup (to Account → Key) and OK. The pending text is "This browser lost its key. Its new key waits for approval." with Account and OK. It appears only after a real state change, so the first-paint rule holds.
  - `browser_signing.js`: exposes its existing `signCanonicalRecord` on `__forumBrowserIdentity`.
  - Guests make no requests: no key, and no member hint, means nothing happens. "Remove this key" signs out and sets the hint to `guest`, so a deliberate removal never bootstraps.
- Verification:
  - Passed: `node tests/browser/key_bootstrap.mjs` in headless Chrome (new). It records the device; clears local storage and keeps cookies; on reload gets a new key, approved (`Approved: yes`, `auth_status` 200), with the right username and one notice; on a second reload keeps the same key and shows no notice; after a deliberate removal gets no key.
  - Passed: `tests/browser/key_bootstrap_contract.cjs` through `KeyBootstrapScriptTest`. It covers the approved order of calls, the pending path, a refused session asked only once, a guest hint, and a session for another key than the hint.
  - Passed: `./v3 test --smoke KeyBootstrapScriptTest KeyBootstrapTest PrivateSiteAuthTest BrowserSigningNormalizationTest` (572 of 572). The full suite was not run.
  - Looked at: a screenshot of the notice in the default Gloss theme. The first version had a square link beside pill buttons; both are now buttons.
  - Without JavaScript: inapplicable by design. The server HTML does not change, and the script is the only actor.
- Notes:
  - `npm install` fetched the pinned `playwright-core` from `package.json` (already a dev dependency) for the browser check; nothing new was added.
  - The identity hint cookie lasts 30 days. A member away longer has no hint, and nothing happens; they restore a backup or ask for approval as today. This is recorded as a limitation, not handled.
  - Only the default theme was looked at for the notice; Stage 5 checks themes for the pill.

**Retrospective stage work: 0.14**

*— Claude · 2026-11-16 UTC · Model: claude-opus-5-5*

## Stage 5 - Replaced pill and queue

**Original estimate: 0.12**

- Changes:
  - `ReplacedKeys.php` (new): reads replaced keys from bootstrap posts and profile approvals already in the read model. A lost key counts as replaced while its replacement's session-vouched approval stands, and until either key is retired.
  - `user_access.php`: a "Replaced keys" card on Tools → User Access, listing each lost key with its pill. Each entry links to the key's page, which already carries the retirement action.
  - `user_access_account.php`: the account's key list adds the Replaced pill. This is the member's own list of keys.
  - `profile_access.php`: the key page shows the pill and "Replaced by" with a link to the new key.
  - `site.css`: `chip--replaced` (dashed border, soft ink) beside `chip--pending` and `chip--approved`.
- Verification:
  - Passed: `KeyBootstrapTest::testReplacedKeyShowsItsPillAndWaitsInTheQueueUntilRetired` covers the queue, the account pill, "Replaced by", no pill on the new key, and that retiring the lost key empties the queue. `KeyBootstrapTest` passes 9 of 9.
  - Passed: `./v3 test --smoke KeyBootstrapTest RootApprovalPageTest UserAccessTest` (488 of 488). The full suite was not run.
  - Looked at: screenshots of the account keys list in Gloss, Gloss Dark, Light, Dark, Word97, Chicago, Midnight, Bubblegum, Spearmint and Engagify (`KEY_BOOTSTRAP_THEME_DIR=… node tests/browser/key_bootstrap.mjs`). The pill shows in all; Word97 and Midnight draw it flat, like their other chips. In Engagify the theme's parody pop-ups cover the list, so the pill can't be seen there; that is the theme's design.
- Notes:
  - Deviation from Step 3: no read-model schema change. The data was already there, so `SCHEMA_VERSION` stays at 55 and no rebuild regression test is needed. This is a simpler route to the same outcome.
  - The plan said to show the pill wherever a Pending pill can appear, including the pending pane. A lost key is approved, so it never appears where Pending pills are; the pending pane is inapplicable.
  - The Account → Key page is drawn by the browser from local storage and has no key list. The member's list of keys is the account page under User Access.

**Retrospective stage work: 0.10**

*— Claude · 2026-11-16 UTC · Model: claude-opus-5-5*

## Stage 6 - Docs, disclosure and final review

**Original estimate: 0.04**

- Changes:
  - `docs/reference/authentication_modes.md`: the session store now describes the browser; a new recovery case for a missing key with a live session; the cases after it are renumbered.
  - Concepts: `viewer_session.md` and `approval_acceptance.md` revised; `replaced_key.md` created.
  - `docs/plans/README.md`: status line.
  - Disclosure, drafted for the anti-cookie notice thread and not posted. It waits for Ilya's go-ahead at release: "Sessions also note your browser's general type, such as Safari on an iPhone, so a browser that lost its key can be told apart from a stranger's. It stays in the server's private session store and nowhere else."
- Verification:
  - Passed: `git diff --check` on the branch and the working tree.
  - Passed: every relative link in the new and changed docs resolves.
  - Final diff review against `origin/main` (49 files): no duplicated components. The notice reuses the app banner; the pill reuses `chip`; approval writes reuse `commitApprovalAcceptance` and accepted-approval recovery; retirement reuses `KeyRetirement`. The only change outside the approved scope is the pre-existing `FeatureFlagsBehaviorTest` fix (Stage 1), which our rule on failing tests requires. The full suite was not run.
- Notes:
  - The ledger row (`docs/plans/effort_estimation_trial.md`) carries the Step 2 and Step 3 snapshots, uncommitted. It goes into the closeout commit with Stage 7.

**Retrospective stage work: 0.04**

*— Claude · 2026-11-16 UTC · Model: claude-opus-5-5*

## Stage 7 - iPhone Safari replay

**Original estimate: 0.05**

- Status: pending. It needs this branch deployed with `FORUM_SESSION_KEY_BOOTSTRAP_ENABLED` on, and Ilya's iPhone.
- Steps for Ilya, in Safari on the iPhone:
  1. Load zenmemes.com once while the key is still there, so the session records the browser (a quiet sign-in).
  2. Drop only the stored key and keep the cookies. "Remove this key" is not usable here, because it logs out first (see Enhancement Requests). Use Settings → Apps → Safari → Advanced → Website Data only if it can delete storage without cookies. Otherwise use a Mac with Safari's Web Inspector attached to the phone: Storage → Local Storage → delete the `forum_pki_*` entries. Failing both, wait for a real seven-day wipe.
  3. Reload. Expect one notice, "This browser lost its key and has a new one.", then a post that shows as approved.
  4. Reload again. Expect no second notice and the same key on Account → Key.
- Evidence so far: the same sequence passed in headless Chrome on Linux (Stage 4). That does not satisfy this stage; desktop browsers and simulators do not count.
