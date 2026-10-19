> **Feature plan:** [Step 1](./status_frontend_target_step1_solution_assessment.md) · [Step 2](./status_frontend_target_step2_feature_description.md) · [Step 3](./status_frontend_target_step3_development_plan.md) · [Step 4](./status_frontend_target_step4_implementation_summary.md)

Debug mode: enabled

# Status frontend target — Step 2 feature description

## Problem

Plain `./v3 status` can report a healthy frontend as unreachable because it assumes port 8000. Approved Step 1 selects explicit configuration plus constrained discovery (Option C).

## User stories

- As a local developer, I want status to follow my checkout's chosen port so that changing ports does not create a false failure.
- As a production operator, I want status to check my actual site so that its verdict and recovery action are trustworthy.

## Core requirements

- Select in order: explicit `--url`, instance-specific target from existing private configuration (environment override supported), one verified active development server for this checkout, then a uniquely verified local application endpoint on port 8000 or 80. Show the chosen URL and source. Invalid explicit/configured values require correction; never silently bypass them.
- Development discovery must distinguish checkout and instance, reject stale/reused process identities, handle wildcard listening addresses as local probe destinations, and report multiple matching servers as ambiguous. A confidently selected target's failure must not trigger fallback to a healthy alternative.
- Automatic endpoint verification must establish the intended instance, not merely an HTTP response. Wrong instances, default virtual hosts, and unverifiable endpoints cannot produce OK. Production virtual hosts/HTTPS use the configured full URL with correct host and TLS behavior; unrelated redirects cannot establish success.
- Unresolved/ambiguous targets produce WARN with one concise target-setting action; confirmed connection failures, timeouts, and server errors retain FAIL. Probe target and source must remain visible, with discovery and all probes sharing a three-second network budget and no port scanning.
- Status remains observational: no server startup, configuration writes, or application-state changes from discovery. Existing health checks and explicit-URL usage remain available.

## Delivery scope and completion boundary

Application change covering CLI target selection, development-server discovery lifecycle, shared frontend probing/health reporting, configuration/help, and regression coverage. Normal entry is `./v3 status`, including its TUI launcher. Completion requires the correct verdict and unaided recovery for both reported environments; no production deployment, server reconfiguration, general network discovery, or database-schema change is included.

## Known environments and flow

1. Local development: run `./v3 start 8001`, then plain status; it identifies this instance at 8001. After a stop, crash, or port change, rerun status: stale evidence cannot yield OK or incorrectly identify another listener.
2. Production on port 80: plain status identifies a uniquely verifiable local instance; virtual-host or unverifiable setups show a configuration action. Set the instance target once in private configuration, rerun plain status, and confirm its URL/source and verdict. A configured outage stays FAIL.
3. For either environment, use `--url` to correct/override selection for one invocation; repair the server or saved target and rerun to verify recovery.

## Risks and early validation

- **Wrong-instance OK:** validate instance identification against an unrelated HTTP server and another checkout before discovery work; reject uncertain matches.
- **Stale/ambiguous development evidence:** validate process lifecycle, crashes, and two servers early; require ownership/liveness evidence and explicit selection when ambiguous.
- **Production routing and latency:** validate port 80, virtual-host routing, HTTPS, and hung candidates before integration; preserve host/TLS semantics and the shared deadline. Unavailable production access leaves deployment acceptance pending.

## Shared component inventory

Extend existing `FrontendProbe`, `OperatorStatusCollector`, and `HealthIndicators`; share target selection across CLI and TUI status invocation. Reuse private-config loading/schema/editor rather than a separate settings store. Web Codebase State also consumes the collector but currently omits frontend probing; retain that behavior. Update existing status usage and operator references; no new navigation or web settings surface.

## Success criteria

Recorded local port-8001 and production port-80 checks select the intended instance; explicit and saved targets win; stale/ambiguous evidence and unrelated listeners never produce false OK; configured outages remain FAIL; hung discovery stays within the network budget. Regression checks cover selection, failures, and recovery; environment evidence and any deployment omissions are recorded separately.
