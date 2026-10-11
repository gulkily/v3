> **Feature plan:** [Step 1](./missing_php_startup_guidance_step1_solution_assessment.md) · [Step 2](./missing_php_startup_guidance_step2_feature_description.md) · [Step 3](./missing_php_startup_guidance_step3_development_plan.md) · [Step 4](./missing_php_startup_guidance_step4_implementation_summary.md)

## Completion Contract

- Entry/outcome: `./v3 start` with existing options reports unavailable PHP CLI on standard error, exits nonzero, and provides installation/PATH, setup-reference, and retry guidance without the shell error or server launch.
- Recovery: Restore PHP availability and retry the same command; the existing server starts with its options preserved. Help and PHP-independent commands remain available.
- Scope: Launcher, README setup guidance, and focused verification only; no installers, version/extension checks, database changes, or checks for other commands.
- External verification: Use a controlled local PATH to reproduce missing PHP; smoke-test the real local server after restoring PHP. No deployment, external service, or actual software installation is required.
- Release: Applicable checks pass, guidance is readable without PHP, and Step 4 records evidence and commits. After `Approved Step 3`, create a feature branch and commit only approved Steps 1–3 first; commit each completed stage with its summary before the next stage.

## Key Risks

- **High risk:** Misleading recovery wording leaves users stuck. Validate wording and the checkout-readable README destination before Stage 1; state PHP is unavailable on PATH and cover both installation and PATH correction.
- A stripped PATH may hide Bash or `dirname`, producing an unrelated failure. Validate the reproduction environment before checks; retain launcher prerequisites while excluding PHP.
- Future installation assistance could expand scope. Review the Stage 1 boundary first; separate detection from guidance and retain a stable setup reference without installer scaffolding.

## Stage 1

- Goal: Deliver the complete friendly missing-PHP startup and manual-recovery flow (≤1 hour).
- Dependencies: Approved Step 3, feature branch/planning commit, and resolved wording/setup-reference risks.
- Expected changes: Add a startup-only PHP availability check and a separate guidance helper in `v3`; extend README Local Run with a stable PHP setup heading. Planned shell helper signature: `missing_php_startup_guidance()` with no arguments, emitting recovery guidance to standard error.
- Verification approach: Check shell syntax and document integrity; run startup with PHP absent from controlled PATH; confirm nonzero exit, empty standard output, actionable standard error, and no shell-level PHP error. Restore PHP and smoke-test a local HTTP response, then stop the server.
- Risks or open questions: Incorrect PATH isolation could invalidate evidence; first confirm Bash and `dirname` remain available. Resolve setup-reference usability before committing.
- Canonical components/API contracts touched: `v3` startup/error surface and README Local Run; reuse `scripts/dev_server.php` unchanged.

## Stage 2

- Goal: Verify argument preservation and unaffected PHP-independent behavior (≤1 hour).
- Dependencies: Stage 1 verification, summary update, and stage commit completed.
- Expected changes: Add a small standalone shell regression check under `tests/`, runnable without PHP, for missing-PHP startup, launcher help, `fdp sync --help`, and forwarding of existing address/logging options when PHP is available. Use a PHP stand-in only for argument capture; retain Stage 1's real-server evidence.
- Verification approach: Run the regression check, shell syntax checks, and whitespace checks; confirm default address, numeric port, `--listen-all`, explicit address, verbosity, and a log-file path containing spaces reach the existing server entry intact. Confirm guidance attempts no installation.
- Risks or open questions: A stand-in cannot demonstrate a working server; pair forwarding checks with Stage 1's real startup/recovery smoke result and clean up temporary fixtures.
- Canonical components/API contracts touched: Launcher command/argument contract and standalone shell test surface; no browser or API changes.
