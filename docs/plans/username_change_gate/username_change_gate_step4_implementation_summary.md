> **Feature plan:** [Step 1](./username_change_gate_step1_solution_assessment.md) · [Step 2](./username_change_gate_step2_feature_description.md) · [Step 3](./username_change_gate_step3_development_plan.md) · [Step 4](./username_change_gate_step4_implementation_summary.md)

Debug mode: enabled

Branch: `feature/username-change-gate`. Planning commit: `595e9c7f`.

Implementation in progress; the flag ships off by default.

## Stage 1 - Server-side gate with a default-off flag

- Changes: new site-mutable flag `FORUM_USERNAME_CHANGES_ENABLED` (default off, ACCESS group) in `FeatureFlagRegistry`; `prepareProfileName` and `finalizePreparedProfileName` refuse with "Username changes are disabled." when it is off, checked before any write; README documents the flag.
- Verification:
  - `php tests/run.php WriteApiSmokeTest` — 163 passed, including the new `testUsernameChangesAreDisabledByDefaultAndGateBothEndpoints` (default off, prepare refused, finalize of a token prepared while enabled refused after toggle, no record written, later success when enabled).
  - `php tests/run.php FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest` — 19 passed after adding the flag to the registry listing expectation.
  - Existing rename test now enables the flag explicitly.
- Notes: the UI button and activity are still ungated and unchanged until Stages 2–6. The caption choice is still needed before Stage 3. A full-suite run is planned for Stage 7.

## Stage 2 - Hide "Choose username" when the flag is off

- Changes: `TemplateRenderer` exposes `usernameChangesEnabled` to every template; `account_key.php` keeps the "Choose username" button in the markup but adds `hidden` unless the flag is on (hidden, not deleted, matching the page-hook convention in `AccountKeyPageHooksTest`; the endpoint gate from Stage 1 still protects it).
- Verification:
  - `php tests/run.php AccountKeyPageHooksTest BrowserSigningNormalizationTest` — 86 passed, including the new `testChooseUsernameButtonIsHiddenUnlessUsernameChangesAreEnabled` (flag off, on, and unconfigured default).
  - `php tests/run.php LocalAppSmokeTest` — 140 passed.
  - Cache risk checked: `/account/key/` is not a pre-built static artifact (not in `StaticArtifactInvalidator` or `StaticArtifactBuilder`), so a flag toggle cannot leave stale markup.
- Notes: real-browser confirmation of the hidden button is part of Stage 7. The caption (Stage 3) still needs the user's choice; default is "Replace key (new identity)".

## Stage 3 - Caption for the key-replacing button

- Changes: the caption was chosen by the user: "Start over with a new key". `account_key.php` now exposes the flag on the page root (`data-username-changes-enabled`) and the ready-state caption in `syncSimpleUI` is "Start over with a new key" when enabled, "Re-setup / change name" when disabled; the not-ready caption "Set up this browser" is unchanged.
- Verification:
  - `php tests/run.php AccountKeyPageHooksTest` — 6 passed (root attribute reflects the flag; template contains both captions and the conditional).
  - No other source, test, or script sets this button's text; the only other references to the old caption are in the historical plan `docs/plans/account_setup_auto_publish_public_key_plan_v1.md`, left unchanged as a record.
- Notes: caption logic is covered by a template-source assertion plus the rendered attribute; the actual displayed text in both states is confirmed in the Stage 7 real-browser check.

## Stage 4 - Activity rows for verified renames

- Changes:
  - `ProfileNames::history()` returns verified name records per identity, oldest first by `(Created-At, Record-ID)`; `latest()` now derives from it (same result).
  - `ReadModelBuilder` collects one event per verified record while building profiles (previous name = prior record, or the bootstrap name for the first), and `indexActivity` writes one `activity` row each: kind `profile_name_change`, record family `profile_name`, label "Changed username from X to Y", tags `["identity","profile-name"]`, no post/thread, source path and commit SHA of the record.
  - `ReadModelMetadata::SCHEMA_VERSION` 20 → 21, so existing databases rebuild on next use.
  - Rows are always indexed; hiding them while the flag is off is Stage 5.
- Verification:
  - `php tests/run.php WriteApiSmokeTest` — 164 passed, including new `testVerifiedProfileNamesWriteOneOrderedActivityRowEach` (two verified renames give two ordered rows with correct labels, source paths and identity; a record whose signature was forged afterwards yields no row and no name).
  - Full `php tests/run.php` — 1229 run, 1228 passed, 1 failed: the new test failed once on a `gpg` "Time conflict" while generating its key (clock-second flake, not an assertion). Fixed by retrying key generation once in the shared test helper `createSigningKey`; `WriteApiSmokeTest` re-run passed 164/164. The full suite is re-run in Stage 7.
  - Copy of `state/local_repository` rebuilt in the scratchpad: two rows, one for the real record `profile-name-535aa5…` ("ilyag" → "new", commit `e895cbe2`) and one for a second rename of another identity made later the same day (`profile-name-aa6d1f…`), each with its own commit SHA.
- Notes:
  - Incremental updates only delete activity rows by post or thread ID, so rename rows (neither) are untouched by them; name writes already trigger a full rebuild.
  - Limitation: an identity first added by an incremental update that already has name records (possible only via import) gets its rename rows at the next full rebuild.
  - The two local identities now share the username "new", so they form one composite user.
