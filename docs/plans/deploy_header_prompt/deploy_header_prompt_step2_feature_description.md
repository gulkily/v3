> **Feature plan:** [Step 1](./deploy_header_prompt_step1_solution_assessment.md) · [Step 2](./deploy_header_prompt_step2_feature_description.md) · [Step 3](./deploy_header_prompt_step3_development_plan.md) · [Step 4](./deploy_header_prompt_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/deploy_header_prompt` ([ledger](../effort_estimation_trial.md), standing enrollment under protocol P2)
Extended estimates: enabled

Cycle estimate: 0.22

# Step 2: Feature Description — Deploy prompt in the page header

## Problem

An operator learns about new commits only by opening Tools → Deploy and tapping Check for updates. Each deploy therefore takes three taps on two pages, usually from a phone.

## User stories

- As the operator, I want a Deploy now button in the header when `origin/main` has new commits, so that I can deploy from any page.
- As the operator, I want the check to stay out of the way, so that a slow GitHub never slows the page I opened.
- As a site owner, I want a feature flag for the prompt, so that I can turn it off without a deploy.
- As a member who is not an operator, I want to see nothing new and make no extra requests.

## Core requirements

- A new site flag, off by default, turns the whole prompt on or off. With it off, no page loads the prompt script or calls the deploy endpoint.
- The prompt is active only in a browser that has completed a successful deploy from Tools → Deploy. Check for updates alone does not turn it on (default; open for review).
- A visit checks only after a short delay and abandons a slow check. The server reuses a fetch newer than about ten minutes, so most visits never contact GitHub.
- The header button appears only when the checkout is behind. It runs Deploy now with the same root-only gate, lock and attempt log as Tools → Deploy. It shows no commit count (default; open for review).
- A refused check (signed out, no longer root) turns the prompt off in that browser.

## Delivery scope

- Work type: application change.
- Completion boundary: the ordinary entry is any page on zenmemes.com with the flag on. The end-to-end outcome is a deployed checkout and a reloaded page on the new commit. Recovery covers a slow or failed check, a refused session, a failed deploy and a dropped connection. Release requires a deploy to production and the maintainer's acceptance there.

## Known user or environment

- User: the maintainer on an iPhone, signed in as a root-approved operator on zenmemes.com.
- Ordinary entry: opens any page after new commits reach `origin/main`.
- Failure to handle: a deploy fails or the connection drops mid-deploy. The button shows the message and links to Tools → Deploy, which already follows a deploy whose reply was lost.
- Verification: the header shows the new commit after reload, and Tools → Deploy lists the attempt. Evidence is a production check on the iPhone; a desktop pass does not count.

## Risks

- **A fetch on every visit.** Impact: GitHub load and a held deploy lock. Validate early that a repeat check inside the interval does no fetch. Mitigate with the server-side interval.
- **Header crowding on phones.** Impact: the button pushes out New post or the account menu. Validate early in all five themes at phone width. Mitigate by reusing the New post button styling.
- **Stale flag after a role change.** Impact: a former operator's browser keeps asking. Validate that a 403 clears the flag. Mitigate by clearing on any refusal.

## Shared component inventory

- `public/assets/deploy_control.js`: extend to set the browser flag after a successful deploy. Its request and status handling are the model for the header script.
- `src/ForumRewrite/Http/DeployController.php` and `src/ForumRewrite/Deploy/AppUpdate.php`: reuse the `check` and `now` actions; extend `check` with the minimum interval. No new endpoint.
- `public/assets/version_check.js` and its app version banner: reuse its pattern for a deferred check that reveals a hidden control. It is not reused directly, since it serves every visitor.
- `templates/partials/app_header.php` and `.new-post-button` in `site.css`: place the button beside New post with the same styling. A new header control is needed because no existing one runs an action.
- `src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php`: add the flag beside `FORUM_INBOX_ENABLED` in the EXPERIENCE group, site mutable.
- `TemplateRenderer.php`: load the script only when the flag is on, as it does for `inbox_unread.js`.

## Concepts touched

- Application update: changed. Add the header prompt and the check interval to `docs/concepts/application_update.md`.
- Viewer session: used unchanged.

## User flow

1. The operator deploys from Tools → Deploy; the browser remembers it.
2. Later, they open any page. A few seconds after load, the page asks the server to check.
3. If the checkout is behind, Deploy now appears in the header.
4. They tap it. The button shows progress, then the page reloads on the new commit.
5. On failure, the button area shows the message and a link to Tools → Deploy.

## Success criteria

- With the flag off, page HTML and network requests match today's for every viewer.
- With the flag on, a member who has never deployed makes no deploy request.
- An operator sees the button within about ten seconds of a page load when commits are waiting.
- Two visits within the interval cause at most one fetch.
- A deploy from the header writes the same attempt log line as one from Tools → Deploy.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*

## Study snapshot

| Field | Value |
| --- | --- |
| Study / candidate | `railspike-effort` / `v3/deploy_header_prompt` |
| Checkpoint | Step 2 handoff |
| UTC date | 2026-11-10 |
| Rater | Claude (single rater) |
| Score | 0.22 projected total |
| Scope version | v2: v1 plus the site feature flag; starting revision `cdec8702` |
| Reference version | R1 |
| Exposure | Step 1 score 0.20 seen; further inspection of flags, header and `version_check.js` |
| Reason for change | Feature flag added; minimum check interval needs a stored timestamp in `AppUpdate.php` |

Ledger update pending: indexed with the planning commit.

Status: Approved Step 2 on 2026-11-10.
