> **Feature plan:** [Step 1](./local_server_dependency_install_step1_solution_assessment.md) · [Step 2](./local_server_dependency_install_step2_feature_description.md) · [Step 3](./local_server_dependency_install_step3_development_plan.md) · [Step 4](./local_server_dependency_install_step4_implementation_summary.md)

Debug mode: enabled

## Execution state

- User authorized Step 4 with “Continue to Step 4”; debug mode explicitly enabled.
- Branch: `feat/local-server-dependency-install`; planning commit: `e261361c`.
- Target: disposable Ubuntu Base 24.04.3 amd64 chroot under `/home/wsl/.cache/v3-dependency-install`, SHA256 checked against the published Ubuntu image manifest; host Ubuntu 22.04. Mounts are private per invocation.
- Stages 1–3 complete; Stages 4–5 pending.

## Stage 1 - Structured assessment and package review
- Changes: Both aliases dispatch `--install` to a shared coordinator. The existing PHP probe exposes machine facts using the same checks. Conservative package mapping validates official Noble candidate sources, installed CLI ownership and executable integrity, selected runtime, and installed-but-disabled capabilities. This stage remains review-only.
- Verification: `bash tests/local_server_dependency_install_test.sh`, `bash tests/local_server_dependency_checks_test.sh`, `python3 tests/local_server_dependency_runtime_test.py`, Bash/PHP syntax and `git diff --check` passed. Existing runtime tests cover no application mutation, output modes, HTTP reads/writes, and 20 shutdown combinations.
- Verification: Real Ubuntu metadata offers PHP `8.3.6-0ubuntu0.24.04.11`. A real distribution CLI/Git setup produced precisely `php8.3-sqlite3 php8.3-mbstring` as required and `php8.3-intl` as optional. Real disabled-PDO, copied/custom executable, and PATH-excluded PHP cases refused automatic mapping. Missing-PHP review proposed Git/CLI/SQLite/mbstring before the initial temporary environment was lost.
- Notes: The first `/tmp` target disappeared and exhibited truncated files; its package-install result was discarded. Recreated and verified the target in the cache directory. This is Linux chroot evidence, not native-host/WSL installer certification. No release claim yet.

## Stage 2 - Confirmed required installation and reassessment
- Changes: Terminal-only exact `yes` confirmation precedes elevation or metadata refresh. Review APT's complete resolved changes, reject removals/unapproved runtimes/unverified sources/unfinished dpkg state, pin reviewed versions, refresh metadata with strict error handling, and reconfirm changed transactions. Root and sudo paths preserve configuration and use no automatic removal. Report package result separately from fresh shared readiness and selected-runtime verification.
- Verification: Installer and existing shell regressions plus syntax/whitespace checks passed. Resolver fixtures reject removals, PHP runtime switches, and unfinished dpkg state; confirmation rejects decline/EOF.
- Verification: Real Ubuntu 24.04 root install of missing SQLite/mbstring through `dependency-check --install`; clean-base missing-PHP/Git/SQLite/mbstring recovery through `check-dependencies --install`; real non-root sudo recovery after removing mbstring. All returned success and fresh required readiness. Real unprivileged invocation without sudo refused after confirmation. PTY decline and redirected input refused without installation. Already-ready invocation returned success with unchanged package metadata/application content snapshots.
- Notes: Real missing-PHP testing exposed `php-common` as required distribution infrastructure; explicitly allowlisted it while continuing to reject other runtime families. Optional selection and interrupted-transaction handling remain Stage 3 work. Evidence transcripts are in `/home/wsl/.cache/v3-dependency-install/stage2-*.log` (disposable local evidence).

## Stage 3 - Optional choices and failed/interrupted recovery
- Changes: Optional intl requires explicit selection before final combined-batch confirmation. Unavailable optional candidates do not prevent required repair; noninstallable restrictions remain manual. APT runs in its own session; INT/TERM stops the process group, checks surviving processes, and suppresses reassessment while package processes remain active. Package failures stay nonzero even when required readiness passes.
- Verification: Shell fixture suites and syntax/whitespace checks passed, including unavailable/unverified sources, partial/broken package state, runtime change, separate readiness/outcome, and active-process refusal. Real optional-only decline and install, strict refresh-hook failure, unreachable proxy/network failure, held dpkg frontend lock, failing post-install hook after actual package changes, and SIGTERM during a slow APT hook all produced the expected outcomes. Logs: `/home/wsl/.cache/v3-dependency-install/stage3-*.log`.
- Notes: Interruption does not promise rollback or repair. Surviving processes receive explicit manual guidance. A Stage 2 snapshot check initially lacked read permission on APT's lock file; rerunning with read access passed before Stage 3 began. No release claim until the full disposable install-to-server regression in Stage 4.
