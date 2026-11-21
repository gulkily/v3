Cycle: v3/local_start_port_fallback
Mode: one-shot
Index: [Plans](../README.md)
Debug mode: enabled; project marker .railspike_debug_on
Effort study: railspike-effort; R1; P2; [ledger](../effort_ledger/local_start_port_fallback.md)
Extended estimates: enabled; one bounded work unit, no staged estimates
**Cycle estimate: 0.25**
Status: technically complete locally; implementation included with this record; merge and maintainer acceptance pending

Original request: Check why ./v3 start fails; the user expected additional ports. Follow-up: “please implement it.” Workflow selection: “use one-shot please”.
Authorization: User, 2026-11-21, explicitly authorized implementation and Railspike one-shot. This includes the existing default-port request PF-20261021-F07 and truthful startup failure reporting. No push, merge, deployment, package changes or application-data repair.
Outcome and boundary: Default ./v3 start tries ports 8000 through 8009, in order. --listen-all uses the same range on its existing host. Explicit numeric, colon-prefixed and host:port requests retain one port. Only address-in-use errors advance. Exhaustion returns nonzero with an explicit-port recovery command. Confirm binding before announcing success, registering the frontend or starting the worker. Preserve logging, terminal behavior, control-pipe shutdown and optional PCNTL support.
Approach and risks: Extend the existing PHP subprocess lifecycle, using PHP's own startup confirmation rather than a racy preliminary port probe. Bound startup waits and attempts. Failed children must be closed before retry, and cancellation must stop startup as well as an established server. Unrelated startup errors must remain failures. Existing occupied listeners must remain untouched.
Reuse decisions: v3 owns argument validation and dependency recovery. scripts/local_server_start.sh owns Bash signals and the control pipe. scripts/dev_server.php owns the PHP child, terminal output, log file, worker and cleanup. Extend these contracts without a second launcher or dependency. Reuse Support/DevServerRegistry.php for registration after successful binding and Support/DevServerLog.php for normal request formatting. Extend tests/missing_php_startup_test.sh for forwarding, add focused process tests beside DevServerWorkerTest.php, and register them in tests/run.php.
Completion conditions: Replay this workspace's occupied port 8000 through ordinary ./v3 start, see the actual alternate URL and obtain an HTTP response from this checkout. Verify multiple occupied ports, fixed-range exhaustion, release-and-retry, explicit-port refusal, unrelated bind failure, request logs, frontend registration and shutdown with/without PCNTL. No leaked listeners, worker on failed startup, false listening output or successful failure exit. Application HTTP 503 from the existing read-model update is separate and out of scope.
Verification plan: ./v3 test --smoke DevServerStartTest DevServerWorkerTest DevServerLogTest, shell forwarding/dependency regression, PHP and shell syntax, git diff --check, feedback checker, and actual workspace startup. Use disposable process fixtures and ephemeral ranges for focused tests to avoid disrupting other checkouts. Full suite will not run. Browser themes, roles, schema changes and deployment are inapplicable to this CLI-only behavior.
Concepts touched: Add docs/concepts/local_development_server.md for startup, fallback and shutdown. Existing local_development_import.md is used unchanged.
Documentation boundary: This record, plans index, cycle ledger/debug log, the new concept, README local-run instructions, CLI reference, affected feedback record/index. Do not change todo.txt or vendored Railspike.
Dependency inventory: Zero added or updated third-party components, packages, manifests or locks.
Starting revision: b4ed31a48; freshly fetched origin/main matched local main on 2026-11-21.
Branch: fix/local-start-port-fallback
Planning commit: cdaf343dc; confirmed before implementation edits

Dated execution history:
2026-11-21: Investigation reproduced Address already in use from /home/wsl/v3's PHP process on 8000. Current launcher prints listening before binding and exits zero. Explicit 8001 binds but application returns its existing “updating site data” 503. No source edits preceded this plan.
2026-11-21: Direct workflow initially proposed under the pasted instructions. User selected one-shot before implementation; follow the one-shot planning and completion commit gates.

Study history:
2026-11-21 intake: Codex, single exposed rater; pristine intake missing because diagnosis and source/test inspection preceded enrollment. Projection 0.25 for scope v1 from baseline b4ed31a48. R1/P2 standing enrollment; all three frozen source hashes verified against docs/research/effort_estimation/references/R1/manifest.json. Historical feedback estimate 0.2 remains separate. No independent rating, measured labor or maintainer fit judgment.
2026-11-21 execution entry: Reaffirm 0.25, same scope v1, baseline, references and exposure. No earlier scores reconstructed. Study acceptance pending.

*— Codex · 2026-11-21 UTC*

Execution and outcome:
2026-11-21: Implemented bounded retries in scripts/dev_server.php after the planning commit. v3 passes an internal fallback flag only for default ports. Explicit addresses remain exact. PHP startup confirmation precedes the header, registry and worker. Failed children are closed, only address-in-use retries, failures exit 1, and the final URL flows through the existing output and registry. Startup diagnostics remain visible and logged.
2026-11-21: Updated README, CLI reference and the local-development-server concept. Registered four process tests and one fast smoke method. Extended existing argument-forwarding checks. Zero dependency, schema, CSS or markup changes.
2026-11-21 corrections during verification: The first new process test expected static requests in default log mode; corrected it to request --verbose. Another new test accidentally passed the internal retry flag through the public CLI; corrected it to exercise the PHP entry for an unrelated bind error. Both corrected tests pass. The first manual exhaustion fixture mistook TIME_WAIT for a live listener; using SO_REUSEADDR made its occupancy match PHP and the actual exhaustion/retry replay passed. The first terminal probe expected different spacing in the unchanged header; its corrected assertion passed. No product scope expansion.

Verification:
Passed: ./v3 test DevServerStartTest DevServerWorkerTest DevServerLogTest on final runtime code, 15/15. Covers successive collisions, actual HTTP response and request/file logs, accurate frontend registration, PCNTL-disabled shutdown, exhaustion without worker/bootstrap, release/retry, explicit numeric/colon/host-port failure through v3, and unrelated bind failure without retry. Log: /tmp/v3-port-fallback-focused-final.log.
Passed: ./v3 test FrontendTargetResolverTest FrontendProbeTest, 14/14. Existing discovery consumers retain live-registration, wildcard, stale/ambiguous and timeout behavior. Log: /tmp/v3-port-fallback-frontend.log.
Failed with baseline evidence: ./v3 test --smoke DevServerStartTest DevServerWorkerTest DevServerLogTest, 496 run, 492 passed, four failed. All startup checks passed. The four failures reproduce on untouched b4ed31a48 in /tmp/v3-port-fallback-baseline: AgentPostingApiTest::testSignedControlDiagnosticsBeforeGenericParserFailure, AgentPostingApiTest::testRulesErrorsAndLegacyCompatibility, SingleRequestHttpTest::testUnicodeRoundTripAndSymbolRejection, SignedPostBundleValidatorTest::testStrictSubsetAndExactBytes. Baseline emits unsupported Unicode-property warnings. Logs: /tmp/v3-port-fallback-tests.log and /tmp/v3-port-fallback-baseline.log. Full suite was not run.
Passed: bash tests/missing_php_startup_test.sh and bash tests/local_server_dependency_checks_test.sh. Default, --listen-all, explicit 8000, shorthand, verbose and spaced log paths forward correctly. Dependency checks remain read-only. PHP/shell syntax and git diff --check pass.
Passed: Actual ./v3 start --no-worker and --listen-all --no-worker both skip the workspace's occupied 8000 and 8001, bind 8002 and serve HTTP 200 CSS matching this checkout byte-for-byte. Holding all ten ports produces exit 1 without listening output and suggests ./v3 start 8010. Releasing only 8009 allows a successful rerun on 8009. SIGTERM releases each listener. Evidence: /tmp/v3-port-fallback-acceptance-u9y4p5e5/.
Passed: Actual terminal startup displays the selected 8002 URL in its pinned header; Ctrl+C exits 0. Evidence: /tmp/v3-port-fallback-terminal-jn3qc93f/. Pre-start control cancellation with PCNTL disabled exits 0 without a success announcement, worker or leaked listener. Evidence: /tmp/v3-port-fallback-cancel-n7navfn6/.
Failed before this work: ./v3 feedback check reports an invalid Evidence value in PF-20261104-F50. The identical failure reproduces on untouched main. The changed item's isolated validation passes. Index regeneration is blocked by that unrelated record; leave it and the generated index unchanged.
Limitations: PHP 8.1/Linux was exercised; PHP 8.3 and other OS environments were not replayed. The bounded five-second no-output startup timeout was inspected but not fault-injected. Existing application data can still return its unrelated HTTP 503 update page. This task changes binding, not read-model recovery. No full suite, production access, deployment, push or merge.
Final review: Checked the diff, ordinary entry, recovery, shared registry consumers and source links. Existing signal/control, logging, worker, registry and formatting contracts are reused. Only scoped code, tests, feedback and documentation changed. No new third-party components or todo.txt edits. Feedback remains blocked pending merge and maintainer acceptance.
Study closeout: Retrospective scope assessment 0.25, same v1/R1/P2 and baseline. No material projection revision, split or independent rating. One bounded work unit completed. Maintainer comfortable-fit judgment, outcome acceptance and measured labor remain unavailable; no accepted-count increment.
Commit/merge/publication status: Planning cdaf343dc confirmed before source edits. Completion commit pending creation. No push, merge or release authorized.

*— Codex · 2026-11-21 UTC*
