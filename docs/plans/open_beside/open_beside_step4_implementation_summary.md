> **Feature plan:** [Step 1](./open_beside_step1_solution_assessment.md) · [Step 2](./open_beside_step2_feature_description.md) · [Step 3](./open_beside_step3_development_plan.md) · [Step 4](./open_beside_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./open_beside_debug_log.md)), inherited from Step 3.

Effort study: `railspike-effort` / `v3/open_beside`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled, inherited from Step 3.

Status: Step 4 in progress on `feat/open_beside`, branched from `origin/main` at `55082575` plus the unpushed docs commit `b007b32f` (design notes and PF-20261117-F10). Planning commit `0dae1b9c`.

**Cycle estimate: 0.60**

# Step 4: Implementation Summary — Open beside

Step 4 entry projection: 0.60, Step 3 reaffirmed. Scope v1, R1, P2; Claude single exposed rater, 2026-11-18 UTC.

Stage estimates below are copied from Step 3 (originals rated 2026-11-18 by Claude); copying them is not a new rating.

Ledger note: Step 3 said this cycle's ledger row would go into the planning commit, but Step 4's gate keeps the ledger out of it. The row goes into the Stage 1 commit instead.

Link rules answered in session before approval (the maintainer asked which links are eligible, was given three proposals, and approved Step 3 without changing them): a link to a spot on the same page scrolls the main page; a link to a file that is not a page opens in the pane; "New post" and "Reply" links in the content open in the pane. Stage 3 builds these.

## Stage 1 - Flag and the framed page

**Original estimate: 0.08**
**Retrospective stage work: 0.08**

- Status: complete.
- Changes:
  - `FORUM_OPEN_BESIDE_ENABLED` in `FeatureFlagRegistry.php`: off by default, site-mutable, in the Experience group. `TemplateRenderer.php` passes `openBesideEnabled` to the layout.
  - `layout.php`'s head script: a page inside a same-site frame named `forum-open-beside` gets `data-open-beside-framed` and a style that hides the site header, the app shell's sky strip, the ruler, the status bar and the new-version banner. Both are set in the head, before the body is parsed.
  - `tests/FeatureFlagEvaluatorTest.php`: the new flag in both expected lists.
  - New `tests/browser/open_beside.mjs`. `BROWSER=firefox` runs it in Firefox.
  - Ledger: this cycle's row in `effort_estimation_trial.md`.
- Verification:
  - `node tests/browser/open_beside.mjs` in Chrome: passes. In the pane's frame the header, ruler and status bar are hidden the first time they can be seen and after load, and the content shows. A frame with another name keeps its header and ruler. A top-level window named like the pane is not marked. With the flag off the frame name means nothing.
  - `BROWSER=firefox node tests/browser/open_beside.mjs`: passes. Firefox 146 installed here with `npx playwright-core install firefox`, so the Step 2 Firefox evidence can be gathered in this workspace.
  - `./v3 test --smoke FeatureFlagsBehaviorTest FeatureFlagEvaluatorTest SiteProfileRegistryTest`: 492 run, 492 passed.
- Notes:
  - The hiding rules are written by the head script, not kept in `site.css`, because `site.css` loads without blocking paint and would let the header flash.
  - The status bar is hidden in every theme but Word97 anyway; the test asserts it only inside the pane.
  - The test watches the frames from the host page. A script injected into the frame does not run early enough in a frame that starts as a blank document.
- Estimate details: original scope as Step 3; uncertainty was whether Firefox could be installed here, and it could. No revisions.

*— Claude · 2026-11-18 UTC · Model: claude-fable-5-2*
