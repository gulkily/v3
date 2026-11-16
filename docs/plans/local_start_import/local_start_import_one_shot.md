Cycle: v3/local_start_import
Mode: one-shot
Index: [Plans](../README.md)
Debug mode: enabled; project marker .railspike_debug_on
Effort study: railspike-effort; R1; P2; [ledger](../effort_estimation_trial.md)
Extended estimates: enabled; no separate work units
**Cycle estimate: unavailable**
Status: planned; next action: planning commit, then implementation

Original request: http://127.0.0.1:8000/threads/thread-20261116191346-32ffe183; PF-20261116-F01. Clone, ./v3 start, browse something useful, then import production content in 1–3 clicks.
Authorization: User, 2026-11-16: “can we one-shot an mvp please?” This follows the investigation and proposed local start page and existing importer. The thread-to-PR procedure authorizes push, PR and a thread reply. No merge or deployment.
Outcome and work type: Application change. A loopback-only development homepage offers useful exploration links and a prefilled production import form. Imported content becomes readable locally. Existing deployed homepages remain unchanged.
Boundary: Local development request handling, a shared-layout template, browser pending feedback, focused tests, CLI reference/README, concept inventory, feedback record, UI audit, and this cycle's plans/index/ledger/debug record. No fixture replacement, destructive replacement, incremental synchronization, scheduling, import performance work, dependency or schema changes.
Approach and risks: Reuse the CLI importer through an argument-array subprocess with explicit destination paths and bounded execution. Expose controls only on ./v3 start with loopback peer/Host, no proxy headers, same-origin POST and a CSRF token. This avoids opening an unauthenticated production mutation route. Resolve these guards before wiring the action. Use the existing importer journal for interrupted publication and normal reruns for download/image failures. Keep detailed output collapsed; failure and recovery visible. Never infer full synchronization from an archive merge.
Reuse decisions: src/ForumRewrite/Support/TestingSelfApproval.php supplies the existing loopback guard pattern. scripts/dev_server.php already marks ./v3 start with V3_DEV_LOG. src/ForumRewrite/Application.php and Http/RouteServices.php render through View/TemplateRenderer.php and templates/layout.php. Reuse card, stack, button-row and details elements without new CSS. scripts/import_instance.php, Import/ContentImportRunner.php and Import/ImportedContentPublisher.php remain authoritative for download, validation, trust exclusions, conflicts, publication and retry. A small development controller is needed because Backup currently provides downloads only and production controls require operator identity.
Completion conditions: From an isolated fresh checkout, run ./v3 start, open /, explore existing content/docs and submit the prefilled import. Imported threads must open through normal board/thread routes. Failed downloads retain source and retry; pending journals offer resume; repeated imports avoid duplicates. No production writes or actual production import during implementation verification.
Verification and recovery plan: Smoke plus focused local-development/import tests; real isolated HTTP source and destination; normal ./v3 start entry; phone/desktop, JavaScript on/off, representative themes and overflow; reject remote/proxied/bad-host/cross-origin/tokenless requests. Replay failed source then successful retry and repeated import. Inspect diff, feedback checker and syntax. Full suite will not run. Production, physical device and maintainer acceptance remain pending.
Concepts touched: Propose docs/concepts/local_development_import.md for local exploration and importing public content. Existing content snapshot export is used unchanged.
Starting revisions and branch: f13ed6763962c92f89a36ed74b6f7b35b51316be; feat/local-start-import, freshly fetched origin/main.
Planning commit: pending

Dated execution history:
2026-11-16: Read the thread (no replies), searched feedback sources and outcomes. PF-20261111-F09 and PF-20261104-B14 are related performance/availability work, outside this MVP. No matching first-run item; allocate PF-20261116-F01.
2026-11-16: Scope decision: useful local landing page rather than replacing test fixtures. Browser imports merge public content; they do not copy private messages, production settings or approval authority.

Study history:
2026-11-16: Intake and execution entry: Codex, single exposed rater; prior investigation/source exposure disclosed. Pristine intake missing. R1 frozen external sources have not been recovered, so numeric ratings remain unavailable. Same scope v1, baseline above, P2 standing enrollment. No independent rating or measured labor; maintainer fit pending.

Outcome: pending
Verification: pending
Final review: pending
Commit/merge/publication status: planning commit pending; no implementation, merge or deployment.

*— Codex · 2026-11-16 UTC*
