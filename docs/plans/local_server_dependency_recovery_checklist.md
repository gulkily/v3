# Local server dependency recovery fixes and follow-up checks

These follow-ups come from the user's actual Ubuntu 22.04 startup experience after the Ubuntu 24.04 installer was delivered. Status updated by the approved startup dependency recovery implementation. Checked items link to [current acceptance evidence](startup_dependency_recovery/acceptance_evidence.md); unchecked items retain broader follow-up scope. The observations below describe the earlier behavior.

Related: [original installation scope and evidence](local_server_dependency_install/local_server_dependency_install_step4_implementation_summary.md), [broader dependency backlog](dependency_fallback_checklist.md), [platform backlog](local_server_dependency_install/local_server_dependency_platform_support_checklist.md), and [Railspike improvement report](local_server_dependency_recovery_railspike_failure_report.md).

## What was observed

- With PHP absent from PATH, `./v3 start` printed generic installation/PATH advice without identifying Ubuntu 22.04, giving package names, or explaining the automatic installer's limitation.
- After PHP became available, diagnostics identified `/usr/bin/php8.1`, version `8.1.2-1ubuntu2.26`, and `/etc/php/8.1/cli/php.ini`, but offered only “Install/enable” advice for missing PDO SQLite, mbstring and optional intl.
- Startup shortened the extension advice further to “Install/enable this extension for the selected PHP CLI.” Following its `check-dependencies` pointer still did not produce concrete installation instructions.
- At discovery, automatic installation supported Ubuntu 24.04/distribution PHP 8.3 only. The known Ubuntu 22.04/PHP 8.1 environment received neither automatic recovery nor sufficiently specific manual recovery.
- The reported symptoms establish unavailable capabilities, not whether the corresponding packages were absent or installed but disabled. That distinction needs inspection before prescribing a repair.

## Fix the discovered guidance gaps

- [x] Identify the detected OS/release in missing-PHP and missing-extension recovery, and show the selected CLI/version when PHP runs.
- [x] Explain automatic installation availability explicitly: verified Ubuntu 22.04/PHP 8.1 and 24.04/PHP 8.3 distribution packages, with manual recovery for unsupported combinations.
- [x] Provide verified, copyable manual setup commands for Ubuntu 22.04's distribution PHP 8.1 when packages are actually missing. Verified missing-runtime batch: `git php8.1-cli php8.1-sqlite3 php8.1-mbstring`.
- [x] For the user's missing-extension case, validate and display the required batch `sudo apt-get install php8.1-sqlite3 php8.1-mbstring`; do not require users to translate capability labels into package names.
- [x] Show optional normalization separately, with `sudo apt-get install php8.1-intl` only when appropriate, and retain the explanation of reduced Unicode behavior.
- [x] Distinguish absent packages from installed-but-disabled extensions. For the latter, identify the selected CLI configuration and relevant module/scan configuration, then give a verified enabling procedure rather than suggesting reinstallation.
- [x] Preserve PATH-first guidance when PHP/Git is already installed; avoid proposing duplicate runtimes to repair executable selection.
- [x] Keep startup concise but actionable: include required commands or a direct next diagnostic step that actually resolves the missing information. The detailed report must add useful recovery detail.
- [x] Reuse one OS/runtime/package mapping for startup, both diagnostic aliases, manual guidance and installation eligibility; prevent shell/PHP message paths from drifting.
- [x] Offer the `--install` command as a recovery option only when supported for the detected environment. Explain unsupported cases and provide a usable manual path.
- [x] Keep ordinary diagnostics and redirected startup noninteractive/read-only. Approved Steps 1–3 supersede the original startup boundary: eligible terminal startup offers reviewed Y/n recovery, with privileges after consent.
- [x] State privileges and system-package effects alongside manual commands; explain omitting sudo in an existing root shell.
- [x] Finish manual/explicit recovery with checks and a manual retry. Approved terminal recovery rechecks and continues the original startup automatically, preserving port/logging options and caller privileges.
- [x] Update help, README, CLI reference and completion/checklist wording so verified Ubuntu 24.04 installation is not mistaken for useful recovery on every supported runtime.

## Proposed subsequent installer extension

Approved Steps 1–3 incorporated this extension into the startup recovery slice. Manual guidance and automatic installation have separate verification evidence.

- [x] Plan explicit Ubuntu 22.04/APT distribution PHP 8.1 support rather than reusing the Ubuntu 24.04/PHP 8.3 mapping.
- [x] Reuse confirmation, optional selection, ownership/source verification, resolved-transaction review, privilege handling and recovery logic.
- [x] Require real Ubuntu 22.04 package/install evidence and install-to-server acceptance before enabling or advertising this mapping. Do not install or upgrade packages on the user's machine merely to collect evidence.

## Additional checks prompted by these findings

### Detection and repair selection

- [ ] Cover missing PHP, missing Git, both missing, unusable executables, PATH omissions, broken alternatives and unsupported PHP versions.
- [ ] Check multiple PHP versions, custom builds, executable wrappers and CLI-versus-web-server differences; never select packages from OS version alone when a different runtime is active.
- [x] Cover PDO absent, PDO present without SQLite, mbstring absent, optional intl absent, and combinations; required batches must be complete and deduplicated.
- [x] Cover installed-but-disabled modules, alternate `PHPRC`/`PHP_INI_SCAN_DIR`, disabled required functions and host execution restrictions. Verify that a suggested repair affects the CLI actually being used.
- [x] Keep missing PDO SQLite distinct from a missing `sqlite3` executable and from an unwritable/corrupt application database.
- [ ] Check stale or absent APT metadata, unavailable candidates, third-party sources, alternate mirrors and ambiguous ownership; unsupported automatic installation must not erase known manual guidance.
- [x] Verify missing/unreadable/malformed OS metadata and Ubuntu derivatives produce honest limitations, without an incorrect Ubuntu package recipe.
- [ ] Verify behavior when Bash, `dirname`, `cat`, APT/dpkg or installer process-control tools are absent; give external setup guidance when the launcher cannot run.

### Entry points and message usefulness

- [x] Replay the user's exact two states through `start`, `check-dependencies` and `dependency-check`: PHP missing; then PHP 8.1 present with SQLite/mbstring unavailable and optional intl unavailable.
- [x] For each state, judge whether a user can take the next step without guessing package names, CLI selection or the meaning of “enable.” Test actionable content, not merely the presence of “Recovery guidance.”
- [x] Ensure the full diagnostic command improves on the startup summary rather than repeating the same vague advice.
- [x] Check terminal, redirected and `NO_COLOR` output for copyable commands, clear required/optional separation and no color escapes in plain output.
- [x] Confirm help works without PHP/Git; both aliases retain equivalent options, messages and exit statuses.
- [x] Verify optional limitations never become required installation steps or block otherwise usable startup.
- [x] Verify users learn about the supported install option from the failure they encounter, without having to find it in a separate document.

### Recovery, side effects and evidence

- [x] Snapshot package/application state around ordinary checks, failed startup, pre-execution decline, EOF and noninteractive install refusal; verify no unintended writes or privilege requests.
- [x] Exercise manual recovery on disposable Ubuntu 22.04 with the actual recommended commands, then recheck and manually start with the original options; verify HTTP reads, thread/reply writes and shutdown.
- [ ] Record WSL-specific terminal/PATH/host-access evidence separately from Linux chroot evidence before making a WSL support claim.
- [x] Preserve verified Ubuntu 24.04 behavior while improving 22.04 guidance: package selection, root/password-authenticated sudo, optional-only paths, transaction changes, failures and interrupt cleanup.
- [x] If adding automatic 22.04 installation, repeat the full real-transaction matrix: missing runtime/extensions, already-ready, refusal, absent privileges, network/lock/partial failure, INT/TERM and fresh readiness.
- [x] Keep package-operation success separate from required readiness and remaining optional limitations; never promise rollback or silently switch runtimes.
- [x] Audit “complete” and “verified” statements against a matrix of platform, runtime, user entry point, failure state and actual outcome. Preserve historical evidence while adding these newly discovered gaps.

## Completion standard

Close guidance items only after the exact reported failures produce a verified next action on Ubuntu 22.04/PHP 8.1 and the action restores usable local operation. Record the environment, command/output evidence and remaining limitations alongside closed items. Treat installer expansion as a separately verified capability.

Evidence limits: chroots share the WSL host kernel but do not certify the user's WSL terminal/PATH or Windows integration. Broad multiple-installation, launcher-tool-absence and alternate-mirror exploration remains open above. Historical failure reports and prior installer evidence are preserved.
