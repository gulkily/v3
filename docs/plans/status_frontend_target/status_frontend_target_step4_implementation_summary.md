> **Feature plan:** [Step 1](./status_frontend_target_step1_solution_assessment.md) · [Step 2](./status_frontend_target_step2_feature_description.md) · [Step 3](./status_frontend_target_step3_development_plan.md) · [Step 4](./status_frontend_target_step4_implementation_summary.md)

Debug mode: enabled

# Status frontend target — implementation summary

Planning commit: `1f7e49d1`. Production deployment is outside this cycle; actual production acceptance remains pending access.

## Stage 1 - Explicit and saved targets
- Changes: `FORUM_STATUS_URL` uses the existing private-config/environment machinery; explicit `--url` wins. Shared URL validation rejects credentials, whitespace and fragments; status reports target source. Default discovery remains for later stages.
- Verification: 24 targeted resolver/status/health/TUI tests passed; diff check passed.
- Notes: configuration does not write from status. Stage 2 will validate proof and HTTP routing before automatic discovery.

## Stage 2 - Instance proof and bounded HTTP
- Changes: read-only no-store/no-cookie diagnostic identity response before application/bootstrap writes; identity hashes canonical checkout, repository and read-model paths, distinguishing shared-repository checkouts. This is diagnostic evidence, not authentication. Probe preserves Host authority, query and TLS peer verification; bounded DNS child, connection and response reads share the caller deadline. No redirect following.
- Verification: seven FrontendProbe tests passed, including another checkout/repository, no initialization, unrelated/redirect responses, Host/query, trusted HTTPS and hostname mismatch. Existing seven StatusCommand tests also passed with the new probe.
- Notes: public index bypasses repository bootstrap only for the identity request. TLS fixture uses a temporary local CA, not disabled certificate verification. No production service touched.

## Additional requests
- Request: update `zenmemes_meta_feedback_2026-10-18_summary.md`, marking resolved items. Disposition: separately authorized documentation upkeep, independent of frontend implementation. Approval: explicit user request. Outcome: reviewed against current code and completion records; update will use a separate documentation commit.

## Stage 3 - Development discovery lifecycle
- Changes: startup registers the actual PHP child after exec; records bind address, checkout/instance identity, boot ID, start ticks and command identity. Shutdown removes its registration; status ignores exited/zombie/reused processes and mismatched instances without writing. Wildcard listeners map to loopback; multiple live hints produce WARN; a selected server must pass proof before its health is checked.
- Verification: 28 resolver/status/health/dev-log checks passed, including stale process identity, exited process, wildcard normalization, ambiguity and unrelated listener rejection. Real `./v3 start 127.0.0.2:8001 --quiet` followed by plain status reported the development target at 8001; SIGTERM removed the registration. Failed bind also left no registration.
- Notes: `127.0.0.1:8001` already belongs to a separate user checkout, which was left running; the real replay used `127.0.0.2:8001`. Linux `/proc` supplies process identity; platforms without it use conventional discovery/configuration in Stage 4. Stage 2 commit: `0528f0a8`; requested feedback-summary update committed separately as `fde98f83`.
