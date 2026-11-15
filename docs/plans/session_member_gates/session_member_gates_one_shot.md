Cycle: `v3/session_member_gates`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/session_member_gates` — [host ledger](../effort_estimation_trial.md#cycle-v3session_member_gates); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.15**
Status: planned; next permitted action: implement and verify locally (push, PR, merge and deploy need the maintainer)

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

Pending.

## Study history

- Intake, 2026-11-15: **0.15**. Scope v1, reference R1, protocol P2. Rated after the audit and call-site inspection, so there is no pristine intake. Single exposed rater: Claude.
- Execution entry, 2026-11-15: **0.15** reaffirmed against this plan.

*— Claude · 2026-11-15 UTC · Model: claude-fable-5-2*
