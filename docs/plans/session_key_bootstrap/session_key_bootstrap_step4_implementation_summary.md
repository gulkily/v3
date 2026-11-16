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
