> **Feature plan:** [Step 1](./compose_keyboard_fit_step1_solution_assessment.md) · [Step 2](./compose_keyboard_fit_step2_feature_description.md) · [Step 3](./compose_keyboard_fit_step3_development_plan.md) · [Step 4](./compose_keyboard_fit_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)
Effort study: `railspike-effort` / `v3/compose_keyboard_fit` ([ledger](../effort_estimation_trial.md#cycle-v3compose_keyboard_fit)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.20**

Step 3 approved 2026-11-02 ("approved step 3"). Branch `feat/compose-keyboard-fit` from `main` `2a7d356f8`; planning commit `6e043cb6e`.

## Step 4 entry checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 4 entry | 2026-11-02T01:35Z | 0.20 (Step 3 reaffirmed) | v1.1 / R1 / P2, baseline `2a7d356f8` | Claude (claude-fable-5-1), single exposed rater; same exposure as Step 3 | No discovery between approval and entry |

Stage identities and original stage-work estimates copied from Step 3 (not new ratings): S1 full composer order 0.07; S2 phone body height and footprint 0.08; S3 acceptance, records and closeout 0.05. Not summed.

## Pending requests

- Theme chrome overflow (Word 97, Midnight, Sticker, Arena) at 375px: separate work, pending.
- Forum reply saying the posted Railspike steps are superseded: separate, maintainer's call, pending.

*— Claude · 2026-11-02 UTC · Model: claude-fable-5-1*

## Stage 1 - Full composer order

**Original estimate: 0.07**
**Retrospective stage-work assessment: 0.07**

- Changes:
  - `templates/partials/composer.php`: in full mode the Title input and its normalization status, then the tools block (tags, event), now render after `.compose-form-actions`; header comment updated. Field names, attributes and the submit contract are unchanged. Compact and reply modes render the same markup as before.
  - `tests/browser/composers.mjs`: order assertion is now body, Post row, Title, tools.
  - `tests/LocalAppSmokeTest.php`: `/compose/thread` order assertion; the paned board New Thread dialog (`/forte`) has body before Post before Title; the compact board form has no Title and no tools.
- Verification:
  - Partial renders through `TemplateRenderer::renderFragment` before and after the change: compact board, QDB add and reply (`data-phone-reply`) output byte-identical; full output differs only in order. Passed.
  - `php tests/run.php LocalAppSmokeTest`: 160 run, 157 passed. The three failures (private lobby surfaces, private forte session recovery, repository archive filename) are recorded by the runner as long-standing since 2026-10-27 and 2026-10-30 and are unrelated. Passed for this stage.
  - `node tests/browser/composers.mjs`: passed. `compose_disclosure.mjs`: passed. `reply_compose_focus.mjs`: passed (Chromium, four themes).
  - `board_compose_focus.mjs`: failed with a `waitForLoadState('networkidle')` timeout. Reproduced identically on the planning commit `6e043cb6e` in a separate worktree, so it is pre-existing and environmental, not caused by this stage. Not chased.
  - Paned dialog order: checked through the server-rendered `/forte` assertion rather than a Chromium visit; the dialog includes the same partial in full mode.
- Notes:
  - Only `compose.css` and the partial reference `.compose-title`; no script locates the Title by position.
  - Engines: Chromium only. No Playwright WebKit or Firefox build is installed in this environment.
- Estimate details: original scope one partial and two test files; uncertainty low; no revisions. Retrospective matches the original.

*— Claude · 2026-11-02 UTC · Model: claude-fable-5-1*

## Stage 2 - Phone body height and footprint

**Original estimate: 0.08**
**Retrospective stage-work assessment: 0.10**

- Changes:
  - `public/assets/compose.css`: under 640px the full composer's body (New post page and the paned New Thread dialog's form) is 5.5rem high, the compact form's height, with vertical resize; the reserved hidden picker footprint on the compose page is not kept on phones. Wider screens unchanged.
  - `tests/browser/compose_layout_stability.mjs`: a phone fit pass over the 13 permitted themes at 375 by 667, 375 by 300 and 1280 by 1000. On phones it asserts no horizontal overflow, Title below Post, body top to Post bottom within 300px, nothing reserved between body and Post, and, after focusing the body and scrolling it into view as a phone does, Post's bottom edge inside the viewport and above the tab bar when shown. At 1280 it asserts the tall body and the reserved footprint remain. New `--phone-fit-only` switch runs just that pass, like the existing `--shared-only`; `PHONE_FIT_SCREENSHOT_DIR` saves a screenshot per case.
- Verification:
  - `node tests/browser/compose_layout_stability.mjs --phone-fit-only`: 39 of 39 cases passed in Chromium. Body top to Post bottom 157 to 166px on phones, 276 to 313px on desktop.
  - Screenshots at 375 by 667 (Light, Word 97) and 375 by 300 (Light, Sticker) reviewed: body, Post row, Title, tags chip in that order; nothing runs off the right edge. Before focus at 300px tall the heading leaves Post below the fold; the focus assertion covers what a phone does next.
  - `node tests/browser/composers.mjs`: passed again with the new CSS.
  - `git diff --check`: clean.
  - The test's full mode (without the switch) fails at its first `waitForLoadState('networkidle')` with a 30s timeout. Reproduced identically on the planning commit in the baseline worktree, so it is pre-existing and environmental (a client script polls every two seconds), not caused by this stage. Not chased; the new pass avoids network-idle waits.
- Notes:
  - Engines: Chromium only; no Playwright WebKit or Firefox build installed here. The real-phone replay is Stage 3.
  - The paned dialog rule is harmless on desktop and only matters if the paned board is used under 640px.
- Estimate details: original scope the phone rules and a theme loop; uncertainty low for layout. Retrospective 0.10 rather than 0.08 because the pre-existing network-idle failure forced the `--phone-fit-only` switch and a baseline reproduction. No scope change.

*— Claude · 2026-11-02 UTC · Model: claude-fable-5-1*
