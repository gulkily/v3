# Arena header deploy button contrast

Cycle: v3/arena_deploy_prompt_contrast (one-shot), [plans index](../README.md)
Debug mode: enabled by project marker.
Effort study: railspike-effort P2, [cycle ledger](../effort_ledger/arena_deploy_prompt_contrast.md).

**Cycle estimate: 0.05**

Status: implemented, with browser and PHP verification pending.

Original request: [Arena deploy screenshot](https://zenmemes.com/threads/thread-20261121093059-ab28d30a), PF-20261121-B01. The screenshot shows dark Deploy now text on a dark fill while Deploying is visible.

Authorization: On 2026-11-21 the requester asked to “one-shot a fix for this play issue all the way up to a PR.” In context, this refers to the Deploy now display shown in the attached screenshot.

Outcome: Keep the header Deploy now label legible in Arena while the pointer is over the button, including when a deploy is pending. Scope is Arena header styling, regression coverage, and the PR. Attachment discovery is a separate issue reported to Agent Accessibility.

Approach and reuse: Preserve the shared foreground/background pair from site.css by excluding the header's active button from Arena's generic hover fill in theme-arena.css. Reuse the deploy prompt browser fixture. No deploy requests to production.

Completion and verification: Exercise the real header in Arena at phone and desktop widths with a mocked pending deploy, compare the hovered foreground/background to the theme's active pair, and exercise existing success and retry checks. Run repository smoke and feedback checks. Live acceptance follows review and deployment.

Concepts touched: [Application update](../../concepts/application_update.md), existing header prompt styling. Deployment behavior and concept contract are unchanged.

Starting revision: fcd1732. Branch: fix/arena-deploy-prompt-contrast.

2026-11-21: Intake estimate 0.05 by ChatGPT. The screenshot was inspected before implementation.

2026-11-21: Planning commit recorded before implementation. The Arena hover rule overrode the active fill without overriding the active foreground. The scoped exclusion keeps the paired theme tokens. The existing browser test now covers hover, pending deployment and re-enabling the button after a busy response.

Verification: JavaScript syntax check and git diff --check passed. The fixture-based browser tests, PHP smoke set and feedback checks could not start without PHP. A separate Chromium reproduction harness was blocked at browser startup by the environment's socket restrictions. Live operator flow and full suite were not run.

Study closeout: Intake and final projected total 0.05, same rater and scope. Browser verification and production acceptance are pending.

Completion commit: pending self-reference. Authored by ChatGPT on 2026-11-21.
