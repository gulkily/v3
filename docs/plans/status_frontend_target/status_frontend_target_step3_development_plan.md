> **Feature plan:** [Step 1](./status_frontend_target_step1_solution_assessment.md) · [Step 2](./status_frontend_target_step2_feature_description.md) · [Step 3](./status_frontend_target_step3_development_plan.md) · [Step 4](./status_frontend_target_step4_implementation_summary.md)

Debug mode: enabled

# Status frontend target — Step 3 development plan

## Completion Contract

- Entry: plain `./v3 status`, directly or through TUI; outcome: correct instance URL, source, and verdict. Preserve explicit override precedence, existing health checks, and web no-probe behavior.
- Recovery: one target-setting action for unresolved/ambiguous discovery; selected outages remain FAIL. Rerunning after configuration/server repair verifies recovery.
- Release: targeted regressions, local `start 8001` replay, and production port-80/virtual-host verification recorded separately. No deployment is authorized; absent production access leaves deployment acceptance pending. No schema changes.

## Key Risks

- **High risk: wrong-instance OK.** Validate instance proof against another checkout/default virtual host first; reject unverifiable discovery. Proof must disclose no paths/secrets and avoid application initialization writes.
- **High risk: hidden outage.** Validate stale/PID-reuse/multiple-server cases before integration; require checkout/instance ownership and never fail over after selecting an authoritative target.
- Routing/deadlines: validate Host/TLS and hung candidates early; no automatic cross-host redirects, shared three-second network budget. Failed feasibility gates block dependent stages and require replanning rather than weaker identification.

## Stage 1 — Explicit and saved targets

- Goal: one-time production configuration works through normal status.
- Dependencies: approved plans; feature branch and planning commit first.
- Expected changes: shared target resolver; `FORUM_STATUS_URL` in existing private-config schema/environment handling; precedence and URL validation; CLI source display.
- Verification approach: explicit > environment/private config; malformed values stop selection; saved outages never fall back; existing status/TUI checks.
- Risks or open questions: conflicting settings misdirect probes; test precedence first, reuse canonical configuration rules.
- Canonical components/API contracts touched: PrivateConfig/Schema, status CLI, collector target metadata; proposed resolver `resolve(...): array`.

## Stage 2 — Instance proof and bounded HTTP

- Goal: verify intended-instance identity without mutating state.
- Dependencies: Stage 1; proof feasibility gate before discovery.
- Expected changes: minimal read-only instance-proof response and shared verification; deadline-aware probing with correct authority/TLS handling. Identity is diagnostic evidence, not authentication.
- Verification approach: intended/other checkout, shared-repository instances, unrelated/default host, no-cookie/cache/write behavior, HTTPS and hung sockets.
- Risks or open questions: false proof or disclosure; establish checkout/instance distinction and no sensitive output before wiring discovery.
- Canonical components/API contracts touched: FrontController early request handling, FrontendProbe; proposed `probe(url, remainingBudget)` and proof contract.

## Stage 3 — Development discovery lifecycle

- Goal: follow the active custom-port server reliably.
- Dependencies: Stage 2 proof and lifecycle feasibility gates.
- Expected changes: checkout-local runtime server registrations with process-start identity, instance context, startup/shutdown cleanup, and wildcard normalization; status only reads them.
- Verification approach: start/stop/crash, failed bind, PID reuse, port change, two servers and another checkout; live-but-hung selected server remains FAIL.
- Risks or open questions: stale registration masks outages; validate ownership/liveness before selection, ambiguity requires override.
- Canonical components/API contracts touched: dev_server lifecycle, shared resolver and runtime registrations; no canonical records.

## Stage 4 — Conventional local discovery

- Goal: plain status recognizes production port 80 when verifiable.
- Dependencies: Stages 2–3.
- Expected changes: proof-check only local ports 8000/80 without scanning; unique-match selection, ambiguity/unresolved WARN, shared deadline, concise recovery.
- Verification approach: one/two/no matches, wrong instance, redirects, hung candidate plus healthy candidate, explicit/registered outage precedence.
- Risks or open questions: deadline starvation hides evidence; incomplete discovery cannot claim uniqueness, offer configured-target recovery.
- Canonical components/API contracts touched: resolver, FrontendProbe, HealthIndicators and collector; CLI/TUI share behavior.

## Stage 5 — Environment acceptance and handoff

- Goal: demonstrate both reported failures and unaided recovery.
- Dependencies: Stages 1–4 passing; each prior stage verified, summarized and committed before advancing.
- Expected changes: operator/CLI/config guidance and final regression evidence; no new UI copy beyond target/source/recovery.
- Verification approach: real `./v3 start 8001` then plain status; stop/change/retry; port-80 and virtual-host fixtures plus actual production checks only when access exists. Run relevant status, config, dev-server, routing and TUI suites; record production omissions explicitly.
- Risks or open questions: fixtures mistaken for deployment acceptance; label evidence by environment and keep unavailable production verification pending.
- Canonical components/API contracts touched: existing CLI help, operator recovery/deployment references and implementation summary; retain web collector omission.
