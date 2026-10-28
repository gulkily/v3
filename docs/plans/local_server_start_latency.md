Title: Local server startup latency
Date: 2026-10-27
Status: Implemented and locally verified
Scope: Small direct startup optimization
Dependency delta: None

Cause: On supported Ubuntu releases, the dependency preflight eagerly prepared
recovery advice for PDO, SQLite, mbstring and intl, even when available. Each
advice call verified the distribution runtime through PHP, dpkg and APT metadata.
This work preceded launching PHP's server, so occupied ports incurred it too.

Change: Include extension availability in the existing PHP identity probe and
prepare package advice only for unavailable extensions. Preserve positional
guidance arguments and all subsequent runtime capability checks. No readiness
cache, package installation changes, or application bootstrap changes.

Measurement: Three local runs per case, measured with Python's monotonic clock.
Before: Dependency report 1.270–1.557 seconds; occupied-port exit 1.249–2.020 seconds.
After: Dependency report 0.107–0.116 seconds; occupied-port exit 0.157–0.166 seconds;
successful TCP listening 0.138–0.158 seconds. These are local warm measurements,
not a guarantee for cold disks or other hosts. No HTTP request was needed to
measure listening; application first-request latency is separate.

Verification: bash tests/local_server_dependency_checks_test.sh passed, including
new assertions that healthy startup/report skip package advice and each missing
extension selects only its own advice. Python's
tests/local_server_dependency_runtime_test.py passed real missing-capability,
recovery-guidance, read-only-report, HTTP read/write, terminal/redirected output,
and SIGINT/SIGTERM shutdown cases with optional facilities disabled. Shell syntax
check passed. Occupied-port output still reports Address already in use.

Existing behavior: The development-server wrapper returns zero after a bind
failure. This pre-existing exit-status issue was observed during timing and is
outside this latency fix.
