Cycle: `v3/session_member_gates`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/session_member_gates` — [host ledger](../effort_estimation_trial.md#cycle-v3session_member_gates); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.15 (retrospective 0.25)**
Status: implemented, verified locally, PR open; next permitted action: maintainer merge and deploy

## Original request

Maintainer, in a Claude session on 2026-11-15: "a few people have asked me about the security stance of v3/zenmemes ... can we please talk about how to address this?" He then asked for a read-only audit. The audit's finding 2 was: on a public instance, several member-only actions decide "approved member" from the `identity_hint` cookie, which any client can set to any username.

## Authorization

2026-11-15. Claude asked: "Do you want me to start a Railspike cycle to fix the hint-cookie bug? It's the only finding that needs code rather than a decision." The maintainer answered: "Yes, that's exactly what I want. Thank you."

Read as authority for a one-shot cycle on the bounded outcome below, committed on a local feature branch. Pushing, opening a PR, merging and deploying are not covered. Pushing also publishes the bug before the fix is live, so it waits for the maintainer's word.

## Outcome and work type

Application change. Normal entry: a thread page and its post tools on a public instance; Tools → LLM Exchanges.

Outcome: these gates accept only an approved identity that is signed in with a viewer session. A hint cookie alone no longer passes them.

- `POST /api/generate_agent_reply`
- `POST /api/expand_agent_reply`
- `POST /api/analyze_post` (the full result for approved viewers)
- The LLM Exchanges list and detail pages
- Thread and post pages: the stored post analysis and the agent-reply controls

A browser whose hint names a profile but which has no session signs in on its next public page load, the way a browser with no hint already does. An approved member therefore gets these tools back after one page load.

Excluded:

- Unsigned posts, votes and tags that name an identity. That is the verified attribution trade-off, with its own flag.
- Operator actions (feature flags, high-traffic mode, Fastmod rubrics, operator grants, attribution cutoff). Their gate also reads the hint, but each needs the operator's verified signature to finish.
- Presentation that follows the hint (liked state, own-post controls, retraction controls). Each action behind it is signed.
- Response headers, write limits and the other audit findings.

## Approach and risks

One predicate, `RouteServices::signedInApproved(?array $viewerProfile)`: the profile is approved and its `_authenticated_identity` mark is exactly `true`. `Application::resolveViewerProfileFromIdentityHint()` and `lobbyViewerProfile()` already set that mark on every profile they return. The gates above call the predicate instead of reading `is_approved` alone.

Risk: an approved member who has a hint cookie but no session loses the agent-reply controls. Today public pages resume a session only when no viewer profile resolved at all, so a hint-only browser never resumes. Mitigation: `RouteServices::renderPageTemplate` also resumes when the resolved profile is not signed in. Validate early with a page test, then in a browser.

Risk: tests that pass these gates with the hint cookie alone. They move to `TestViewerSession::signIn`, and a new test proves the hint alone is refused.

## Reuse decisions

- Session identity: `ViewerSessions` through the existing `_authenticated_identity` mark; no new lookup. `TemplateRenderer.php:612` already reads the same mark.
- Session resume: the existing `data-public-auth-resume` path in `templates/layout.php` and `public/assets/private_site_auth.js`; only its condition widens.
- Tests: `tests/Support/TestViewerSession.php`.
- No new routes, records, flags or schema.

## Completion conditions

- Each gate above refuses a request that carries only a hint cookie naming an approved member, and accepts the same member with a session.
- A hint-only page view carries the resume marker; a signed-in page view does not.
- Members-only instances behave as before.
- Recovery for a member who sees the tools missing: reload once, or open Account. Stated in the handoff.

## Concepts touched

- [Viewer session](../../concepts/viewer_session.md): changed. Add that member-only tools require the session, and that hint-only browsers resume on public pages.

## Verification and recovery plan

- `WriteApiSmokeTest`: the agent-reply tests, plus a new hint-only refusal test for the three API gates.
- `LocalAppSmokeTest`: resume marker for hint-only and signed-in viewers; analysis and LLM Exchanges visibility.
- Scripture expansion and LLM exchange tests that touch the gates.
- Browser: a hint-only browser with a saved key signs in on a public page, if the browser tests run here.
- Smoke set plus changed tests only; full suite not run.

## Starting revisions and branch

Host `main` at `ed22daed919795ec499def3a324d7d9b99c2c965`; branch `fix/member-gates-need-session`.

## Planning commit

`00ae864` (confirmed with Git; `633944c` before the rebase).

## Study history

- Intake, 2026-11-15: **0.15**. Scope v1, reference R1, protocol P2. Rated after the audit and call-site inspection, so there is no pristine intake. Single exposed rater: Claude.
- Execution entry, 2026-11-15: **0.15** reaffirmed against this plan.

- Closeout, 2026-11-15: retrospective scope assessment **0.25**. The planned change was as sized. Two pre-existing test defects found on the way were fixed under the maintainer's standing rule, and they account for the difference. No measured effort, no independent rating; maintainer acceptance pending.

## Dated execution history

2026-11-15, all by Claude:

- Added `RouteServices::signedInApproved`. `PostWorkflowApiController` (agent reply, expansion, analysis, and the retired score route's unreachable gate), `ThreadAndPostPageController` (analysis and agent-reply controls on thread and post pages) and `Application::viewerCanInspectLlmExchanges` now call it.
- `RouteServices::renderPageTemplate` also sets the public resume marker when the viewer profile's `_authenticated_identity` is `false`. A profile without the mark counts as signed in, so pages that pass their own profile do not change.
- Discovery: 41 `WriteApiSmokeTest` tests passed these gates with `identity_hint=guest` alone. That is the reported bug, exercised by the suite. They now call a `signInApprovedGuest()` helper built on `TestViewerSession`. Three tests gained hint-only assertions; with `src/` reverted, all three fail.
- Correction, pre-existing (maintainer's standing rule on base-commit failures): with `--jobs=4`, `WriteApiSmokeTest` failed 2 to 6 tests per run on unmodified `main` (3 of 3 runs; a different set each time, one with "Timed out waiting for execution lock: /tmp/forum-rewrite.lock"). `createTempEnvironment()` put the read-model database directly in the temp folder, so every test shared the rebuild lock beside it. Each test now gets its own database directory, as `CONTRIBUTING.md` asks. After: 3 of 3 parallel runs clean.
- Correction, pre-existing (same rule): `tests/browser/llm_exchange_privacy.mjs` failed on unmodified `main` at its first legacy-exchange assertion. It still expected the fail-closed visibility checks that the retraction simplification removed (`docs/reference/llm_exchange_privacy.md`, `docs/plans/retraction_simplification_handoff.md`). The test now runs `scripts/retraction_sweep.php run` where the cron worker would, expects exchanges to stay readable until then, and no longer expects a malformed retraction record to hide an unrelated exchange. `WriteApiSmokeTest::testRetractionMakesDirectAndContextOnlyLlmExchangeDetailsUnavailable` already describes the same behavior. No application code changed for this. The maintainer should confirm the removed malformed-evidence assertion matches his intent.
- The same browser test's review step used the hint alone. It now asserts 403 for the hint, then signs in.
- New `tests/browser/hint_only_resume.mjs`: a browser with a registered key and a hint, but no session, signs in on a public thread page; a signed-in page carries no resume marker. Fails with `src/` reverted.
- Docs: `docs/reference/authentication_modes.md` and the viewer session concept entry.

Enhancement requests: none.

## Outcome

Delivered as bounded. With only a hint cookie naming an approved member: `generate_agent_reply` and `expand_agent_reply` answer 403, `analyze_post` gives the anonymous view, LLM Exchanges answer 403, and thread and post pages show no analysis or agent-reply controls. A signed-in approved member sees and does what he did before.

Limitations and next actions:

- `tests/browser/scripture_trial.cjs` and `tests/browser/scripture_quality.cjs` drive paid provider trials with `identity_hint=guest` alone. They were not run and not changed. Before their next use they need a sign-in step, for example `tests/Support/test_viewer_session_cookie.php <trial directory>/app signed-in <identity>`.
- An approved member with a hint but no session sees the member tools one page load later than before. A browser with no saved key must sign in on Account.
- Not verified on the live site or on a phone. Production acceptance is the maintainer's.
- Operator gates still read the hint before asking for a signature. Excluded here; harmless while every such action verifies a signature.

## Verification

- Passed: `./v3 test --smoke --jobs=4 WriteApiSmokeTest PrivateSiteAuthTest RetractionSweepTest RootApprovalPageTest VisitorStatisticsPageTest QuoteCardDisplayNumberTest BrowserSigningNormalizationTest DeployPageTest FastmodAuditCommandTest DedalusPostAnalyzerTest LlmExchangeRecorderTest SqliteLlmExchangeStoreTest UsagePageKindTest AuthNavigationTest`: 862 run, 862 passed.
- Passed: `./v3 test WriteApiSmokeTest` serial, 219 of 219; `--jobs=4` three times, 219 of 219 each.
- Passed: `./v3 agent-reply test-local`, 22 of 22.
- Passed (Chromium): `node tests/browser/llm_exchange_privacy.mjs`, `node tests/browser/scripture_expansion.cjs`, `node tests/browser/hint_only_resume.mjs`.
- Reproduced baseline failures, now fixed: the parallel `WriteApiSmokeTest` failures and `llm_exchange_privacy.mjs`, both on `ed22daed`.
- Not run: the full suite (the maintainer runs it), the other browser tests that set `identity_hint`, and the paid scripture trials.
- Inapplicable: migrations and read-model schema (none), theme checks (no markup or CSS change), deployment (not authorized).

## Final review

Diff checked: four source files, three test files, two documents, this record, the index and the ledger. No duplicated gate logic remains at the changed call sites. No dependency change; `node_modules/` from the local `npm install` is ignored by Git. The viewer session concept entry matches the code paths it names.

## Commit, merge and publication status

Planning commit `00ae864`, completion commit `53b3862` (confirmed with Git). 2026-11-16: the maintainer asked for a PR ("please pr the hint-cookie fix"). Rebased onto `main` at `b9ffa6b`; one conflict, both sides appending to the effort ledger, kept both. After the rebase: smoke set plus the affected classes 697 of 697, agent-reply runner 22 of 22, both browser tests pass. Pushed and opened as a PR; not merged, not deployed.

2026-11-17: the maintainer asked to make #274 mergeable. Merged `main` at `c010efca` (which includes #289, session key bootstrap) into the branch rather than rebasing, so the PR's pushed history stays intact. Four documentation conflicts, each two sides adding text in the same place; kept both: `viewer_session.md` (both revisions), `authentication_modes.md` (this PR's item 3 text inside #289's renumbered list), `docs/plans/README.md` and the effort ledger. No code conflicts. After the merge: smoke set plus session, key bootstrap and 31 agent/analysis/member-gate write-API tests 514 of 514; `hint_only_resume.mjs`, `llm_exchange_privacy.mjs` and `key_bootstrap.mjs` pass. Full suite not run. Not merged, not deployed.

*— Claude · 2026-11-15 UTC · Model: claude-fable-5-2*
