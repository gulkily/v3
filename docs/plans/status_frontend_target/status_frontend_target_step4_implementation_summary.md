> **Feature plan:** [Step 1](./status_frontend_target_step1_solution_assessment.md) · [Step 2](./status_frontend_target_step2_feature_description.md) · [Step 3](./status_frontend_target_step3_development_plan.md) · [Step 4](./status_frontend_target_step4_implementation_summary.md)

Debug mode: enabled

# Status frontend target — implementation summary

Planning commit: `1f7e49d1`. Production deployment is outside this cycle; actual production acceptance remains pending access.

## Stage 1 - Explicit and saved targets
- Changes: `FORUM_STATUS_URL` uses the existing private-config/environment machinery; explicit `--url` wins. Shared URL validation rejects credentials, whitespace and fragments; status reports target source. Default discovery remains for later stages.
- Verification: 20 targeted resolver/status/health/TUI tests passed; diff check passed.
- Notes: configuration does not write from status. Stage 2 will validate proof and HTTP routing before automatic discovery.

## Stage 2 - Instance proof and bounded HTTP
- Changes: read-only no-store/no-cookie diagnostic identity response before application/bootstrap writes; identity hashes canonical checkout, repository and read-model paths, distinguishing shared-repository checkouts. This is diagnostic evidence, not authentication. Probe preserves Host authority, query and TLS peer verification; bounded DNS child, connection and response reads share the caller deadline. No redirect following.
- Verification: seven FrontendProbe tests passed, including another checkout/repository, no initialization, unrelated/redirect responses, Host/query, trusted HTTPS and hostname mismatch. Existing seven StatusCommand tests also passed with the new probe.
- Notes: public index bypasses repository bootstrap only for the identity request. TLS fixture uses a temporary local CA, not disabled certificate verification. No production service touched.

## Additional requests
- Request: update `zenmemes_meta_feedback_2026-10-18_summary.md`, marking resolved items. Disposition: separately authorized documentation upkeep, independent of frontend implementation. Approval: explicit user request. Outcome: reviewed against current code and completion records; resolution/partial/verification-pending markers committed separately in `fde98f83`.

## Stage 3 - Development discovery lifecycle
- Changes: startup registers the actual PHP child after exec; records bind address, checkout/instance identity, boot ID, start ticks and command identity. Shutdown removes its registration; status ignores exited/zombie/reused processes and mismatched instances without writing. Wildcard listeners map to loopback; multiple live hints produce WARN; a selected server must pass proof before its health is checked.
- Verification: 28 resolver/status/health/dev-log checks passed, including stale process identity, exited process, wildcard normalization, ambiguity and unrelated listener rejection. Real `./v3 start 127.0.0.2:8001 --quiet` followed by plain status reported the development target at 8001; SIGTERM removed the registration. Failed bind also left no registration.
- Notes: `127.0.0.1:8001` already belongs to a separate user checkout, which was left running; the real replay used `127.0.0.2:8001`. Linux `/proc` supplies process identity; platforms without it use conventional discovery/configuration in Stage 4. Stage 2 commit: `0528f0a8`; requested feedback-summary update committed separately as `fde98f83`.

## Stage 4 - Conventional local discovery
- Changes: no-default selection now checks only loopback ports 8000 and 80 with instance proof, then probes the unique match. Discovery plus health shares three seconds; each candidate has at most one second. Incomplete proof collection cannot assert uniqueness. No matches or ambiguity produce WARN with a saved-target/override action; selected/configured outages retain FAIL.
- Verification: 27 targeted resolver/status/health/collector tests passed; proof matching, two matches, unrelated listeners, HTTP 503, explicit precedence and hung-candidate shared budget covered. Added WARN cases to existing health verdict coverage; diff check passed.
- Notes: a registered target is never bypassed for conventional discovery. Stage 3 commit: `13fd0a6c`. Actual port-80/CLI recovery acceptance follows in Stage 5.

## Stage 5 - Environment acceptance and handoff
- Changes: documented target precedence, saved `FORUM_STATUS_URL`, custom-port discovery, older-server restart, explicit override, WARN/FAIL recovery and production virtual-host setup. Added a reproducible network-isolated CLI acceptance harness; final review tightened missing-path normalization, port-zero rejection, cross-origin redirect diagnosis and disappearing-registration handling. Same-origin sign-in redirects retain responding status.
- Verification: final focused suite passed 58/58 (probe, resolver, collector, status, health, private config, dev log and TUI). After the disappearing-file guard, the six resolver tests passed again. All 11 isolated CLI checks passed with real `./v3 start 8001`, multiple servers/override, shutdown, changed port, port-80 fixture, saved virtual-host URL, configured outage and environment precedence. Command: `unshare -Urn sh -c 'ip link set lo up; php tests/Support/frontend_status_acceptance.php'`. Probe regressions include trusted HTTPS, wrong TLS hostname, cross-origin redirect rejection and hung-candidate budget.
- Broader verification: LocalAppSmokeTest ran 142 checks: 138 passed, four failed. Static-build progress counts (two tests) and the SQLite-viewer preset assertion also failed on unchanged pre-feature `50147c7e` in a disposable worktree. The three-second colon-port startup check failed once under suite load, then passed on base and on this branch; both port shorthand checks passed in the targeted replay. These baseline assertions were not changed. This is not a full-suite pass.
- Integrity: PHP syntax, Bash syntax, diff whitespace, and local links in all cycle plans and the feedback summary passed. No database schema, navigation/icon, deployment or service configuration changes; icon-theme UI checks are inapplicable. Temporary test servers/worktree were removed; existing user listeners were left running. Stage 4 commit: `d495f798`.
- Deployment evidence: actual production access/deployment was outside scope and remains pending. Port-80/virtual-host evidence is from isolated fixtures; HTTPS evidence uses a local trusted test CA. The earlier host-loopback replay used 127.0.0.2:8001 because 127.0.0.1:8001 belonged to another checkout; the isolated final replay used the exact `./v3 start 8001` command.

## Completion contract audit
- Passed: normal CLI selection and recovery, authoritative targets, checkout/instance proof, stale/ambiguous discovery, shared network budget, saved configuration and unchanged web no-probe behavior. All five stages have scoped implementation commits and summary updates, following the planning commit.
- Pending: actual production verification. No merge, push, deployment, or cron installation performed.

## Private-config template regression repair — October 23, 2026

- User reported repeated omissions of new configuration settings from `private-config refresh-template`. `FORUM_STATUS_URL` was registered as a template setting in `PrivateConfigSchema`, but the handwritten writer did not emit it. The same gap affected `SCRIPTURE_SOURCE_PATH`, `SCRIPTURE_RESPONSE_GUIDANCE`, `SCRIPTURE_RESPONSE_MODEL`, `SCRIPTURE_RESPONSE_REASONING_EFFORT`, `SCRIPTURE_RESPONSE_IMMEDIATE_DISPATCH`, `FASTMOD_META_TAGGING_ENABLED` and `FASTMOD_META_THRESHOLD`. Existing values for these keys could be dropped because the additional-value preservation path correctly classified them as schema template settings, while the writer omitted them.
- The writer now tracks explicitly rendered settings and automatically emits every remaining schema template setting. Added status URL guidance and an example, preserving environment/CLI precedence without persisting environment overrides. Existing custom settings and dedicated comment blocks remain supported. Updated CLI reference and contribution requirements; future template settings need no duplicate rendering-list entry.
- Regression: real command creation is checked against every schema template default; refreshing deliberately changed values checks preservation of every template key, a custom key and a saved URL despite an environment override. The new test failed before the repair and passed afterward. The existing LLM-update test now checks preservation of the status URL and Scripture path as well. All 18 PrivateConfigCommand/PrivateConfigSchema/FrontendTargetResolver tests passed; PHP syntax and diff checks passed.
- Scope: temporary synthetic configuration files only; no real private config read or refreshed for this repair, no dependency delta, service change, push or deployment. Unrelated effort-study working changes were left untouched. Previously lost values cannot be reconstructed by this code change; restore them from retained private backups if needed.
