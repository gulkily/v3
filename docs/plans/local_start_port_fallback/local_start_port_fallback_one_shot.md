Cycle: v3/local_start_port_fallback
Mode: one-shot
Index: [Plans](../README.md)
Debug mode: enabled; project marker .railspike_debug_on
Effort study: railspike-effort; R1; P2; [ledger](../effort_ledger/local_start_port_fallback.md)
Extended estimates: enabled; one bounded work unit, no staged estimates
**Cycle estimate: 0.25**
Status: planned; next action is planning commit, then implementation and verification

Original request: Check why ./v3 start fails; the user expected additional ports. Follow-up: “please implement it.” Workflow selection: “use one-shot please”.
Authorization: User, 2026-11-21, explicitly authorized implementation and Railspike one-shot. This includes the existing default-port request PF-20261021-F07 and truthful startup failure reporting. No push, merge, deployment, package changes or application-data repair.
Outcome and boundary: Default ./v3 start tries ports 8000 through 8009, in order. --listen-all uses the same range on its existing host. Explicit numeric, colon-prefixed and host:port requests retain one port. Only address-in-use errors advance. Exhaustion returns nonzero with an explicit-port recovery command. Confirm binding before announcing success, registering the frontend or starting the worker. Preserve logging, terminal behavior, control-pipe shutdown and optional PCNTL support.
Approach and risks: Extend the existing PHP subprocess lifecycle, using PHP's own startup confirmation rather than a racy preliminary port probe. Bound startup waits and attempts. Failed children must be closed before retry, and cancellation must stop startup as well as an established server. Unrelated startup errors must remain failures. Existing occupied listeners must remain untouched.
Reuse decisions: v3 owns argument validation and dependency recovery. scripts/local_server_start.sh owns Bash signals and the control pipe. scripts/dev_server.php owns the PHP child, terminal output, log file, worker and cleanup. Extend these contracts without a second launcher or dependency. Reuse Support/DevServerRegistry.php for registration after successful binding and Support/DevServerLog.php for normal request formatting. Extend tests/missing_php_startup_test.sh for forwarding, add focused process tests beside DevServerWorkerTest.php, and register them in tests/run.php.
Completion conditions: Replay this workspace's occupied port 8000 through ordinary ./v3 start, see the actual alternate URL and obtain an HTTP response from this checkout. Verify multiple occupied ports, fixed-range exhaustion, release-and-retry, explicit-port refusal, unrelated bind failure, request logs, frontend registration and shutdown with/without PCNTL. No leaked listeners, worker on failed startup, false listening output or successful failure exit. Application HTTP 503 from the existing read-model update is separate and out of scope.
Verification plan: ./v3 test --smoke DevServerStartTest DevServerWorkerTest DevServerLogTest, shell forwarding/dependency regression, PHP and shell syntax, git diff --check, feedback checker, and actual workspace startup. Use disposable process fixtures and ephemeral ranges for focused tests to avoid disrupting other checkouts. Full suite will not run. Browser themes, roles, schema changes and deployment are inapplicable to this CLI-only behavior.
Concepts touched: Add docs/concepts/local_development_server.md for startup, fallback and shutdown. Existing local_development_import.md is used unchanged.
Documentation boundary: This record, plans index, cycle ledger/debug log, the new concept, CLI reference, affected feedback record/index. Do not change todo.txt or vendored Railspike.
Dependency inventory: Zero added or updated third-party components, packages, manifests or locks.
Starting revision: b4ed31a48; freshly fetched origin/main matched local main on 2026-11-21.
Branch: fix/local-start-port-fallback
Planning commit: pending

Dated execution history:
2026-11-21: Investigation reproduced Address already in use from /home/wsl/v3's PHP process on 8000. Current launcher prints listening before binding and exits zero. Explicit 8001 binds but application returns its existing “updating site data” 503. No source edits preceded this plan.
2026-11-21: Direct workflow initially proposed under the pasted instructions. User selected one-shot before implementation; follow the one-shot planning and completion commit gates.

Study history:
2026-11-21 intake: Codex, single exposed rater; pristine intake missing because diagnosis and source/test inspection preceded enrollment. Projection 0.25 for scope v1 from baseline b4ed31a48. R1/P2 standing enrollment; all three frozen source hashes verified against docs/research/effort_estimation/references/R1/manifest.json. Historical feedback estimate 0.2 remains separate. No independent rating, measured labor or maintainer fit judgment.
2026-11-21 execution entry: Reaffirm 0.25, same scope v1, baseline, references and exposure. No earlier scores reconstructed. Study acceptance pending.

*— Codex · 2026-11-21 UTC*
