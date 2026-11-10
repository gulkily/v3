> **Feature plan:** [Step 1](./deploy_header_prompt_step1_solution_assessment.md) · [Step 2](./deploy_header_prompt_step2_feature_description.md) · [Step 3](./deploy_header_prompt_step3_development_plan.md) · [Step 4](./deploy_header_prompt_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/deploy_header_prompt` ([ledger](../effort_estimation_trial.md), standing enrollment under protocol P2)
Extended estimates: enabled

Cycle estimate: 0.20

# Step 1: Solution Assessment — Deploy prompt in the page header

## Original Query

As a V3 developer and Zen Memes DevOps, I want to streamline the deploy process on zenmemes.com. What I want to happen is if I'm logged in with a user and I use the deploy panel successfully once, the next time I visit the website, I want, with a timeout, for the check for updates function to run. And if there are updates available, I want it to automatically surface a deploy now button in the pages header.

## Understood Intent

Today the operator opens Tools → Deploy, taps Check for updates, then taps Deploy now. The goal is that an operator who has deployed before sees a Deploy now button in the header of any page as soon as `origin/main` has new commits. "With a timeout" is read two ways: the check starts shortly after the page loads and gives up if GitHub is slow. Both are kept. Feedback search found no matching item in `docs/feedback/items/`.

## Problem Statement

An operator learns about new commits only by opening the Deploy page and checking by hand.

## Concepts touched

- Application update (`AppUpdate.php`, `DeployController.php`): changed in every option.
- Viewer session: used unchanged; the deploy endpoint still demands a root-approved session.
- Header (`app_header.php`): changed in every option.
- Internal task queue: changed in Option C only.

## Solution Options

### Option A: The browser remembers; the page checks on visit
**Estimate: 0.20**
Driver: a header button plus a throttled check; uncertainty is header layout across five themes.

After a successful Deploy now or queued deploy, `deploy_control.js` stores a flag in this browser. On later page loads, a small script waits a few seconds, then asks the existing `/tools/deploy/` endpoint to check. The server reuses the last fetch when it is recent, so most visits do not contact GitHub. The script abandons a check that takes too long. If the checkout is behind, a Deploy now button appears in the header. A refused request (signed out, not root) clears the flag.

- Pros: no schema change; server HTML stays the same for every viewer; reuses the existing endpoint, lock and attempt log.
- Cons: per browser, so a second phone needs one deploy first; the button appears after paint, which is allowed only because new commits are a real state change.

### Option B: The server remembers who deployed
**Estimate: 0.30**
Driver: new per-identity state and an endpoint that answers for any signed-in member.

The server records identities with a successful deploy in the attempt log. Every signed-in page asks a new endpoint whether this viewer qualifies and whether updates exist.

- Pros: works on every browser of that operator at once.
- Cons: every member's page load makes a request that only operators need; adds stored state and a second gate to keep in step with the root check.

### Option C: The task worker checks; pages only read
**Estimate: 0.30**
Driver: a scheduled fetch task plus the Option A header script.

The task worker fetches on a schedule and stores the result. The header script from Option A reads only that stored result, so a visit never waits on GitHub.

- Pros: visits never trigger a fetch; the button can appear almost at once.
- Cons: depends on the task worker, which the Deploy page often reports as not running; results lag by the schedule.

## Recommendation

**Option A**, with a server-side minimum interval between real fetches (about ten minutes). The visit check uses the same `check` action and the same root-only gate as the Deploy page. The header button runs Deploy now, shows progress in place and reloads on success. A failure links to Tools → Deploy. Option C can follow later if visits still feel slow.

Open questions for review: Should a successful Check for updates alone also turn the prompt on? Should the header button also show how many commits are waiting?

**Vertical-slice viability:** On an iPhone, the operator deploys once from Tools → Deploy. Later they open the board; within seconds a Deploy now button appears in the header. They tap it, the site updates and the page reloads on the new commit. A slow GitHub, a signed-out session or a failed deploy leaves the page usable and the Deploy page unchanged. Verify in all five themes, phone and desktop, and with the flag absent.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*

## Study snapshot

| Field | Value |
| --- | --- |
| Study / candidate | `railspike-effort` / `v3/deploy_header_prompt` |
| Checkpoint | Step 1 handoff (intake recorded at the same time) |
| UTC date | 2026-11-10 |
| Rater | Claude (single rater) |
| Score | 0.20 projected total for Option A; intake 0.20 |
| Scope version | v1, starting revision `cdec8702`; outcome pending |
| Reference version | R1 |
| Exposure | Intake made after reading `DeployController.php`, `deploy_control.js` and `deploy.php`, so not strictly prospective |
| Reason for change | Initial |

Ledger update pending: this snapshot will be indexed in the ledger with the planning commit.

## Enhancement Requests

- **Request:** "Please remember to make a feature flag for it." / **Disposition:** in-scope refinement; a site flag gates the whole prompt, following existing `FeatureFlagRegistry.php` flags / **Approval:** given with Approved Step 1 / **Outcome:** carried into Step 2 requirements.

Status: Approved Step 1 on 2026-11-10 (Option A). The two open questions were not answered; Step 2 records defaults for review.
