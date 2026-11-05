> **Feature plan:** [Step 1](./site_purpose_step1_solution_assessment.md) · [Step 2](./site_purpose_step2_feature_description.md) · [Step 3](./site_purpose_step3_development_plan.md) · [Step 4](./site_purpose_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/site_purpose`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.45**

Branch: `feat/site-purpose`. Planning commit: `1517b499`.

## Step 4 Entry

2026-11-05T16:25:00Z: Step 3 projection **0.45 reaffirmed** before implementation; scope v1/B, baseline `822d6134`, R1/P2, single exposed Claude rater. Stage originals copied from Step 3: S1 0.10, S2 0.06, S3 0.12, S4 0.08, S5 0.10 (not summed).

## Stage 1 - Site description and build example on About

**Original estimate: 0.10**

**Retrospective stage work: 0.08**

- Changes:
  - `ProfilePresentationContent.php`: the zenmemes editorial entry gains `siteDescription` and `buildExample`. A new `siteDescription(array $profile): string` returns the sentence, or an empty string for profiles without one. `about()` passes both through.
  - `templates/pages/about.php`: the intro section shows the description, then the example with "Read the thread" and "See the change" links, then the existing intro paragraph. Each part renders only when present.
  - `tests/PresentationProfileMatrixTest.php`: new test checks that only zenmemes shows the description and example, in that order, with both links.
- Verification:
  - `./v3 test --smoke PresentationProfileMatrixTest`: 431 run, 431 passed. Full suite not run, per `AGENTS.md`.
  - Example thread `thread-20261103060933-51ef9554` (Deploy page request by ilyag, Nov 3) has a reply linking PR #26; PR #26 is merged at the baseline (`822d6134`).
  - `git diff --check` clean.
- Notes:
  - Wording and example were chosen by Claude because the maintainer approved Step 3 without picking them. Description: "zenmemes is a garage where people and agents build this site together, starting from threads." Both are one-line edits if the maintainer prefers others.
  - Reuse: extended the existing per-profile copy store; no new store or template.
- Estimate details: original scope as Step 3 Stage 1; no revision; retrospective lower because the copy store already had the right shape.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*

## Stage 2 - Site description leads `/llms.txt`

**Original estimate: 0.06**

**Retrospective stage work: 0.05**

- Changes:
  - `LlmsTxtRenderer`: two optional constructor arguments, site name and site description. With both, the file opens with `# <name>`, the description as the quote line, then the existing Git and OpenPGP sentence. Without them, the opening is unchanged.
  - `Application::renderLlmsTxt()` passes the active profile's display name and `ProfilePresentationContent::siteDescription()`.
  - `tests/LlmsTxtRendererTest.php`: new test for both openings; the generic sentence appears exactly once.
- Verification:
  - `./v3 test --smoke LlmsTxtRendererTest PresentationProfileMatrixTest`: 443 run, 443 passed. Full suite not run.
  - Local server with `FORUM_SITE_ID=zenmemes`: `/llms.txt` opens with `# zenmemes` and the description.
  - `git diff --check` clean.
- Notes:
  - A first draft dropped the technical sentence for zenmemes; the local fetch caught it before commit.
- Estimate details: original scope as Step 3 Stage 2; no revision.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*

## Stage 3 - First-visit intro on the home page

**Original estimate: 0.12**

**Retrospective stage work: 0.12**

- Changes:
  - `templates/partials/visitor_intro.php` (new): one card with the site description and an About link; renders nothing when the profile has no description. It is a partial because zenmemes uses `board_list.php` and other profiles use `board.php`; both include it at the top.
  - `TemplateRenderer`: shares `siteDescription` with every template, beside `composerPrompt`.
  - `templates/layout.php` head script: sets `data-visitor="known"` before paint when the browser has a saved identity (the same `forum_pki_*` check the member script uses) or `forum_visitor_intro_hidden` is `1`. Wrapped in try/catch; on failure the intro stays visible.
  - `public/assets/site.css`: hides `[data-visitor-intro]` under `data-visitor="known"`.
  - `tests/PresentationProfileMatrixTest.php`: only zenmemes renders the intro, above the composer; every profile carries the head check.
- Verification:
  - `./v3 test --smoke PresentationProfileMatrixTest BoardListTest`: 432 run, 432 passed. Full suite not run.
  - Headless Chrome against a local zenmemes server, at 390 and 1280 px, recording the intro's computed display when it is first parsed: fresh browser `block`; saved identity `none` with `data-visitor="known"`; hidden flag `none`. No flash for members.
  - Phone screenshot in the light theme: the card sits above the board controls and reads cleanly.
  - `git diff --check` clean.
- Notes:
  - Step 3 named `board.php`; inspection showed zenmemes renders `board_list.php`, so the shared partial replaces a single-template edit. Same scope.
  - `npm install --no-save` restored the declared `playwright-core` dev dependency locally for the browser check; no dependency change.
- Estimate details: original scope as Step 3 Stage 3; no revision.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*
