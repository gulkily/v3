> **Feature plan:** [Step 1](./local_server_dependency_install_step1_solution_assessment.md) · [Step 2](./local_server_dependency_install_step2_feature_description.md) · [Step 3](./local_server_dependency_install_step3_development_plan.md) · [Step 4](./local_server_dependency_install_step4_implementation_summary.md)

Debug mode: enabled

## Execution state

- User authorized Step 4 with “Continue to Step 4”; debug mode explicitly enabled.
- Branch: `feat/local-server-dependency-install`; planning commit: `e261361c`.
- Target: disposable Ubuntu Base 24.04.3 amd64 chroot under `/home/wsl/.cache/v3-dependency-install`, SHA256 checked against the published Ubuntu image manifest; host Ubuntu 22.04. Mounts are private per invocation.
- All five implementation stages complete.

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

## Stage 4 - Repeatable install-to-server acceptance
- Changes: Added an explicit-root, checksum-verified, fresh-image harness that creates a new disposable Ubuntu rootfs, retains transcripts, and uses private mounts. Added PTY fixtures proving changed transactions require fresh confirmation and declined revisions never install. Acceptance testing refined sudo session handling: authenticate in the original terminal, isolate the privileged APT child, and use `setsid --wait` so reassessment waits for actual completion.
- Verification: The final clean-image run (`acceptance-3`) passed every harness case: both aliases, missing PHP/Git/extensions, optional-only decline/install, untouched decline/EOF/noninteractive cases, unavailable privileges, already-ready snapshots, custom runtime/restrictions, network/lock/partial failure, root INT/TERM and password-authenticated sudo TERM cleanup, password-authenticated sudo installation, and subsequent manual startup. The existing runtime suite passed HTTP thread/reply writes and reads plus all 20 plain/PTY, optional-degradation, INT/TERM shutdown combinations with original port/log options. Host shell, changed-confirmation, syntax, and whitespace checks passed.
- Evidence: [Durable test record](./local_server_dependency_install_test_evidence.json) includes exact image checksum, OS/runtime/package versions, source hashes, case results and lifecycle checks. Full local transcripts: `/home/wsl/.cache/v3-dependency-install/acceptance-3/logs/`.
- Notes: Earlier acceptance iterations exposed test-terminal setup and detached sudo/wait issues; only the final corrected run establishes acceptance. No host package installation or application schema/deployment changes. Broader platform claims remain unverified.

## Stage 5 - Setup/recovery guidance and release evidence
- Changes: Updated top-level and alias help, README, CLI reference, dependency backlog, plan index and platform checklist. Documented terminal confirmation, optional choices, root/sudo, strict source/runtime boundaries, status semantics, partial changes, active-process recovery, external launcher tooling and manual startup retry. Ordinary startup/checks remain read-only.
- Verification: Both aliases provide identical help without PHP/Git; unknown/unattended-bypass options are rejected. Shell and confirmation regressions, the final host runtime suite, syntax checks, documentation links, and whitespace checks passed. Evidence audit covers the full Step 3 Completion Contract; installer behavior and source hashes remain those verified by Stage 4.
- Notes: The platform checklist is included now as approved documentation scope, having been excluded from the initial plans-only commit. No schema, UI, deployment, external-service integration or broader-platform implementation; their release checks are not applicable. Exact tested environment is recorded in the linked JSON; future platform checklist entries remain unchecked.

## Reproduce disposable acceptance

From the repository root on a Linux machine with root access, mount namespaces,
Python 3, tar and network access to Ubuntu APT, download the tested Ubuntu Base
image and run the harness. The supplied SHA256 was checked against Ubuntu's
published image manifest. The harness requires a new work directory, installs
packages only inside its chroot, and retains that directory and its logs for
inspection; it does not install host packages. This is a test harness, not a
supported unattended installer mode.

```bash
mkdir -p "$HOME/.cache/v3-test-images"
curl -fL https://cdimage.ubuntu.com/ubuntu-base/releases/24.04/release/ubuntu-base-24.04.3-base-amd64.tar.gz \
  -o "$HOME/.cache/v3-test-images/ubuntu-base-24.04.3-base-amd64.tar.gz"
sudo python3 tests/local_server_dependency_install_integration_test.py \
  --base-image "$HOME/.cache/v3-test-images/ubuntu-base-24.04.3-base-amd64.tar.gz" \
  --sha256 6bc2cde3930ad088b3bb46fa45279e96d25bc3810f209850ecbe4722711874f9 \
  --work-dir "$HOME/.cache/v3-install-acceptance-$(date -u +%Y%m%dT%H%M%SZ)"
```

Fast checks that do not perform real installations:

```bash
bash tests/local_server_dependency_checks_test.sh
bash tests/local_server_dependency_install_test.sh
python3 tests/local_server_dependency_install_confirmation_test.py
python3 tests/local_server_dependency_runtime_test.py
```
