# Startup dependency recovery acceptance

Verified on 2026-10-13. [Implementation summary](startup_dependency_recovery_step4_implementation_summary.md) · [Machine-readable results](acceptance_evidence.json)

## Environments and outcomes

| Environment | Selected distribution CLI | Ordinary startup | Manual guidance | Explicit installer |
|---|---|---|---|---|
| Ubuntu Base 22.04.5, amd64 chroot | PHP 8.1.2-1ubuntu2.26 | Missing PHP and missing SQLite/mbstring: root and password-sudo recovery → original port/log/caller → HTTP thread/reply writes and reads → clean shutdown | Displayed install commands and disabled-mbstring enabling command executed successfully, followed by usable startup | Both aliases; required/optional selection, ready no-op, real transactions and failure recovery |
| Ubuntu Base 24.04.3, amd64 chroot | PHP 8.3.6 (full package version in JSON) | Same four successful journeys | Same three manual repair journeys | Same matrix |

Initial startup acceptance began from checksum-verified, freshly extracted images.
After final guidance/cancellation/ownership checks, both missing-runtime and
missing-extension password-sudo startup journeys were repeated on the final source.
The complete corrected refusal/failure matrix passed on both retained targets.

Checks include Enter/Y/y acceptance, separate default-off intl, n/N/invalid/EOF,
pre-execution SIGINT/SIGTERM, malformed options, each redirected stream,
absent/denied/cancelled sudo, optional-only startup without a prompt, custom and
disabled runtimes, network/lock/partial failures, package interruption cleanup,
and successful package installation with remaining required restrictions.
Failed recovery opens neither the requested listener nor its application log.
Pre-execution refusals preserve application/package/metadata snapshots.
Modified package-owned CLI binaries are also refused, without replacement.

Pure fixtures additionally verify changed-transaction consent/cancellation,
cross-release/untrusted/mixed/local-only package origins, removals/runtime switches,
unfinished dpkg state and deferring readiness when package processes survive.
Forty prompt-boundary signal trials cover the Bash cancellation race discovered
in acceptance. Real-runtime regressions cover capability failures, tty/plain/
NO_COLOR reporting and 20 HTTP read/write/shutdown combinations, including missing
PCNTL, shell execution and stty.

## Reproduce

Requires Linux mount namespaces, root privileges, Python 3, tar, network access,
and the downloaded Ubuntu Base images. No host package installation is performed.
Each initial harness work directory must **not exist**. The harness mounts proc/dev
inside a private mount namespace and changes packages only inside its chroot.
Test-only sudo users/passwords are created inside the disposable filesystem.

Verified input images:

- [Ubuntu Base 22.04.5 amd64](https://cdimage.ubuntu.com/ubuntu-base/releases/22.04/release/ubuntu-base-22.04.5-base-amd64.tar.gz), SHA256 `242cd8898b33ea806ef5f13b1076ed7c76f9f989d18384452f7166692438ff1a`.
- [Ubuntu Base 24.04.3 amd64](https://cdimage.ubuntu.com/ubuntu-base/releases/24.04/release/ubuntu-base-24.04.3-base-amd64.tar.gz), SHA256 `6bc2cde3930ad088b3bb46fa45279e96d25bc3810f209850ecbe4722711874f9`.

For each image, substitute its local path, checksum and a new persistent directory:

```bash
sudo python3 -B tests/startup_dependency_recovery_integration_test.py \
  --base-image /path/to/ubuntu-base.tar.gz --sha256 CHECKSUM \
  --work-dir /path/to/new-startup-test
sudo python3 -B tests/startup_dependency_recovery_failure_test.py \
  --work-dir /path/to/new-startup-test
sudo python3 -B tests/startup_dependency_recovery_manual_test.py \
  --work-dir /path/to/new-startup-test
sudo python3 -B tests/local_server_dependency_install_integration_test.py \
  --base-image /path/to/ubuntu-base.tar.gz --sha256 CHECKSUM \
  --work-dir /path/to/another-new-installer-test

bash tests/missing_php_startup_test.sh
bash tests/local_server_dependency_checks_test.sh
bash tests/local_server_dependency_install_test.sh
python3 tests/local_server_dependency_install_confirmation_test.py
python3 tests/local_server_dependency_runtime_test.py
```

Retained execution roots in this workspace:

- `/home/wsl/.cache/startup-recovery-{22,24}-final/`: fresh-image startup evidence, full corrected failure logs, and final-source continuation checks.
- `/home/wsl/.cache/startup-explicit-{22,24}-final/`: complete fresh-image explicit installer matrices and installed-runtime regressions.
- `/home/wsl/.cache/startup-recovery-{22,24}-stage3/manual-logs/`: actual emitted manual commands, CLI enabling, HTTP outcomes and modified-binary refusal.

The JSON records result cases, exit statuses, source hashes and transcript hashes.
Earlier failed harness attempts remain in separate cache directories; they are
not counted as successful acceptance. Stage 4/5 summary notes describe fixes and
reruns, including the prompt cancellation defect found during final acceptance.

## Limits

These chroots share the WSL host kernel; they do **not** independently verify the
user's WSL terminal, Windows access, PATH setup, other releases, architectures or
package mirrors. No host packages were installed or removed. No schema,
deployment or external service changes were made. APT metadata must already offer
verified candidates for a review; missing/stale metadata gets explicit manual
refresh/inspection guidance. Configuration restrictions and custom runtimes remain
manual. Partial package changes have no automatic rollback.
