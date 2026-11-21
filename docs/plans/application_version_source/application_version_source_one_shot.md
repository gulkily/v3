# Application version source

Cycle: v3/application_version_source; Mode: one-shot; Index: [plans](../README.md)
Debug mode: enabled
Effort study: railspike-effort, R1/P2; [cycle ledger](../effort_ledger/application_version_source.md)
Extended estimates: enabled

**Cycle estimate: 0.25**

Status: planned; next action: planning commit, then implementation and focused verification.
Original request: User selected “2” from the ranked list: fix the application-version source.
Authorization: 2026-11-21 chat selection authorizes implementing the bounded fix in one-shot mode. No production access, deployment, flag mutation on another instance, push or merge is authorized.
Feedback: PF-20261030-B18. Its broader request includes independent cached-page revisions; preserve that behavior as part of fixing the shared version contract.
Outcome: /api/version and ordinary forum app-version metadata identify the code checkout, remain unchanged after content commits, and change after code updates. Cached navbar pages still detect fresh content independently. Documentation release versions remain independent.
Boundary: Application, existing revision lookup, TemplateRenderer/layout metadata, version-check and cached-navigation scripts/service worker, affected tests, concept/reference documentation, this record/index/ledger/debug log and feedback item/index. No dependency, schema, authentication, theme, navigation destination, deployment or canonical-record changes.
Approach: Reuse ReadModelMetadata::repositoryHead with the project root for application version; support linked-worktree .git files in its existing Git fallback. Keep the existing text endpoint, banner, Reload action and retry/backoff. Add separate page-version metadata combining code and content revisions for cached-navigation freshness and a separate pending-page storage key, using the same banner helper. Keep /api/docs-version and its pending state independent.
Reuse decisions: src/ForumRewrite/Application.php::appVersion is the shared source for the endpoint, forum metadata, agent discovery/checkin and skills. src/ForumRewrite/ReadModel/ReadModelMetadata.php::repositoryHead already resolves HEAD without changing the repository; extend its .git guard rather than duplicate Git resolution. src/ForumRewrite/Http/CodebaseStateController.php already uses the code checkout and separately renders content revision; retain its existing generic Git/status implementation. templates/layout.php, public/assets/version_check.js, public/assets/nav_cached_copy.js and public/service_worker.js own the existing refresh notice and cache handshake; extend these contracts, not the UI. TemplateRenderer receives the content revision explicitly from Application; documentation rendering retains its release version.
Risks: Shared cached-page behavior currently depends on content-backed app-version. Resolve this before implementation by providing separate page freshness and pending state; test content-only changes and Reload. Linked worktrees must resolve the actual code checkout and source-only exports must not inherit an enclosing Git repository. Preserve no-git/git-error fallbacks. Production's disabled notification flag remains operator-controlled; document re-enabling after deployment.
Completion conditions: Normal /api/version and board output agree with code HEAD; content commits do not change app-version; code commits do. Linked worktree, packed/detached refs and missing Git behavior covered. Existing cached navbar flow detects content changes and Reload reaches fresh content without repeated redirects. Browser phone/desktop checks cover the existing banner flow. Network failure retains retry behavior. Production deployment and phone acceptance remain explicitly unverified.
Concepts touched: [Application update](../../concepts/application_update.md), changed to document code-version detection and independent cached-page freshness. Existing static-publication behavior used unchanged; no new inventory concept needed.
Verification plan: ./v3 test --smoke VersionCheckBehaviorTest FastNavigationLayoutTest ApplicationVersionTest LocalAppSmokeTest::testApplicationRendersTextApisAndRss LocalAppSmokeTest::testApplicationRendersBoardThreadPostAndUserPages (confirm exact method names first); add affected read-model tests if resolver change warrants them. Run existing cached-navigation browser replay and an isolated code/content HTTP/browser regression. Review all appVersion consumers and Git fallback cases. Full suite will not be run. Do not claim deployed recovery from local checks.
Recovery: Failed polling silently retries with existing backoff. Reload uses existing __v bypass; separate pending state clears once the requested code/page is shown. Operator enables App version notification after deploying this fix; if an environment override disables it, remove that override and restart the service. Source-only distributions retain no-git and cannot detect Git-based updates.
Starting revision: 22fc9f10a4d4f8cba9ecaed501aba2ea90d129e4, freshly fetched origin/main.
Branch: fix/application-version-source
Planning commit: pending.
Dependency delta: zero; only existing PHP/Git/Node/browser facilities.

## Study history

2026-11-21T17:50:50Z intake: pristine pre-inspection estimate missing; code/backlog inspection occurred while ranking and scoping. First exposed scoped estimate 0.25; Codex, single rater. Scope v1 includes shared cached-page recovery, documentation and checks, starting revision above. R1 cached calibration-report, implementation-diff-review and user-feedback hashes verified against manifest; standing P2 enrollment. No independent rating or measured labor.
2026-11-21T17:50:50Z execution entry: 0.25 reaffirmed for scope v1 and R1/P2. No numbered work units or alternatives manufactured. Maintainer comfortable-fit judgment and acceptance pending.

## Execution and verification

Pending planning commit. No implementation edits yet.

*— Codex · 2026-11-21 UTC*
