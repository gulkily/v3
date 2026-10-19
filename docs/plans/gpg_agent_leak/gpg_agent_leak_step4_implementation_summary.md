> **Feature plan:** [Step 2](./gpg_agent_leak_step2_feature_description.md) · [Step 3](./gpg_agent_leak_step3_development_plan.md) · [Step 4](./gpg_agent_leak_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

# Step 4: Implementation Summary – GPG agent leak

## Stage 1 - Confirm the kill command
- Changes:
  - None (manual repro in `/tmp` scratch homes; GnuPG 2.2.27, WSL2).
- Verification:
  - Passed: using a temp home starts one `gpg-agent` (0 → 1); `gpgconf --homedir <dir> --kill all` returns the count to 0.
  - Passed: the agent socket directory is derived from the home path (`/run/user/1000/gnupg/d.<hash>/`), so the kill must name the same `--homedir`.
- Notes:
  - Risk 1 confirmed: kill is path-keyed; helper must run kill before delete and pass the explicit home.
  - In this plain shell, 2.2.27 agents exited on their own when the home was deleted, so the reported leak is likely environment-dependent (e.g. missing `XDG_RUNTIME_DIR`, or PHP-spawned processes). Explicit kill is correct either way; Stage 5 measures the real suite.

## Stage 2 - Shared cleanup helper
- Changes:
  - New `ForumRewrite\Security\GpgHomeCleaner::dispose(string $home): void`: runs `gpgconf --homedir <home> --kill all` (10s timeout, failures ignored), then deletes the tree recursively.
  - New `tests/GpgHomeCleanerTest.php`, registered in `tests/run.php`.
- Verification:
  - Passed: `php tests/run.php GpgHomeCleanerTest OpenPgpKeyInspectorTest` (8 run, 8 passed); `gpg-agent` count 0 afterwards.
  - Not yet run: missing-`gpgconf` (stripped PATH) case beyond the swallowed-failure design; covered by `@exec` plus ignored exit status.
- Notes:
  - The recursive delete also fixes the inspector's old `glob('/*')` cleanup, which skipped dotfiles and nested dirs such as `private-keys-v1.d`, leaving temp homes behind.

## Stage 3 - Inspector and verifier use the helper
- Changes:
  - `OpenPgpKeyInspector::cleanup` and `OpenPgpSignatureVerifier::cleanup` now delegate to `GpgHomeCleaner::dispose` (verifier's private recursive delete removed).
  - Added `testInspectAndVerifyLeaveNoGpgAgentsBehind` to `tests/OpenPgpKeyInspectorTest.php` (agent count and temp-home count do not grow; waits up to 2s for already-stopped agents to exit).
- Verification:
  - Passed: `php tests/run.php OpenPgpKeyInspectorTest GpgHomeCleanerTest` (9 run, 9 passed), three consecutive runs; `gpg-agent` count 0 afterwards.
  - Limitation: the new test also passes against the old code on this host, because GnuPG 2.2.27 here exits agents on its own once the home vanishes. It guards against regressions but does not reproduce the reported leak; Stage 5 must measure the real environment.
- Notes:
  - `gpgconf --kill` is asynchronous, so the test tolerates agents still exiting from earlier operations.
