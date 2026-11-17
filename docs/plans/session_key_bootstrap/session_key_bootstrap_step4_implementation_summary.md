> **Feature plan:** [Step 1](./session_key_bootstrap_step1_solution_assessment.md) · [Step 2](./session_key_bootstrap_step2_feature_description.md) · [Step 3](./session_key_bootstrap_step3_development_plan.md) · [Step 4](./session_key_bootstrap_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/session_key_bootstrap`; [host ledger](../effort_estimation_trial.md#cycle-v3session_key_bootstrap). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.80**

# Step 4: Implementation Summary — Session key bootstrap

Step 3 approved 2026-11-16. Branch `feat/session-key-bootstrap` from `origin/main` (`65b98d00`). Planning commit `d1ee94f9`.

PR #274 was still open at Step 4 entry. This branch starts from `main` and rebases onto it if it merges first.

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
