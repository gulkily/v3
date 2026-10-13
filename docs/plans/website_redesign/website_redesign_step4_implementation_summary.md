> **Feature plan:** [Step 1](./website_redesign_step1_solution_assessment.md) · [Step 2](./website_redesign_step2_feature_description.md) · [Step 3](./website_redesign_step3_development_plan.md) · [Step 4](./website_redesign_step4_implementation_summary.md)

Debug mode: enabled

## Setup
- Branch `feature/website-redesign`, created from `feature/quiet-debug-mode` (unmerged to `master`) so the quiet debug contract in use applies. A PR to `master` will include those commits unless that branch merges first.
- Planning commit `d6dec11` holds the approved plans, debug log, and `docs/plans/README.md` entry.
- `.gitignore` now ignores `/temp/` at the maintainer's request; it is outside this feature's allowed files and is left uncommitted pending the maintainer's call.
- The maintainer updated `temp/Railspike Readings.html` after Step 3: the first voice is now "Gulko's original" and the last is "Daniel Gackle". No scope change; Stage 3–4 use the updated file.

## Stage 1 - New logo asset
- Changes:
  - Replaced `railspike-logo.png` with a 512×512 palette PNG exported from `temp/Rail Spike Cross-Section Icon.png`; near-white and near-black pixels snapped to flat colors to remove compression noise.
- Verification:
  - Size 4,388 bytes (was 1,445,612), under the 100 KB target.
  - Viewed the exported image: rail, plate, and amber spike intact with clean edges; color matches the supplied icon.
  - The README references the same filename, so no README change was needed.
  - Runtime, deployment, and release checks not applicable to this stage.
- Notes:
  - The icon has an opaque white background, which will show as a white tile on dark backgrounds; Stage 2 handles placement in dark mode.

## Stage 2 - Modernized QDB look
- Changes:
  - `index.html`: replaced the stylesheet and header markup only; page content, section order, and links are unchanged. The only markup edit besides the header is replacing the hero headline's forced line break with a space (same words) and balancing its wrap.
  - Amber title bar with the logo and wordmark, a slim slash-separated menu (Workflow / Get started / Docs / GitHub), bordered blocks with amber top rules, monospace labels and code, system font stacks.
  - Light and dark color schemes via `prefers-color-scheme`; theme-color set to the amber.
- Verification:
  - Rendered with headless Chrome at 1200 px (light, dark) and 360 px: layout intact, no clipped content.
  - Overflow check at 360 px: `scrollWidth` equals `clientWidth`, so no horizontal scroll.
  - Amber used for bars and borders; link text uses a darker amber (light) or brighter amber (dark) for contrast. Contrast was judged visually, not measured with a tool.
  - `git diff --check` clean; changed files within allowed scope.
  - Deployment and release checks not applicable to this stage.
- Notes:
  - The logo keeps its opaque white tile, shown as a badge on the amber bar; dark mode needs no separate asset.
  - Keyboard focus styles were restyled but not yet exercised; Stage 4 and 5 cover keyboard walkthroughs.

## Stage 3 - Original essay as the explanation
- Changes:
  - `index.html`: new `#what` section after the hero with the maintainer's updated essay ("Gulko's original" from `temp/Railspike Readings.html`) as plain HTML, wrapped in `<article id="v-original">` for the Stage 4 selector; "What it is" added to the menu.
  - Essay text copied verbatim except one typo fix ("promt" → "prompt"); the essay's own title became the section heading, and the hero, workflow, install, and fit sections are untouched.
- Verification:
  - Text comparison of the page article against the source article: identical apart from the typo fix (835 words).
  - Rendered at 1200 px: readable 68-character column, list and headings intact.
  - The page has no scripts, so the essay is fully visible without scripting; the essay contains no links to check.
  - `git diff --check` clean; only `index.html` and this summary changed.
  - Deployment and release checks not applicable to this stage.
- Notes:
  - The essay says "we" and "my agentic developers and me"; it was kept verbatim per the plan. Maintainer may want to adjust wording later.
  - Hero headline now balances onto three lines at desktop width; acceptable, revisit in Stage 5 polish if desired.

## Stage 4 - Voice selector
- Changes:
  - Maintainer decision (gate): ship all nine voices, each non-original clearly labeled "In the style of …" with a note that it is a pastiche, not a real quotation, and not written or endorsed by that person.
  - `index.html`: the essay section now holds nine `<article class="voice">` blocks copied from the updated `temp/Railspike Readings.html`; only the Original is visible by default, the others carry `hidden`.
  - A small inline script builds an ARIA tablist (click, ArrowLeft/Right, Home, End; roving tabindex; focusable panel) from the articles; without scripting there are no tabs and the Original stays visible alone.
  - Styles for tabs, voice notes, and the sources' dateline, sign-off, and footnotes; section heading is now "Introducing Railspike" with the Original's own title as its h3.
  - Voice text unchanged apart from the earlier "promt" typo fix; the voice preference is not stored.
- Verification:
  - Text of all nine articles compared to the source: identical (Original apart from the typo fix).
  - Markup tag balance check passed; page references only GitHub and the Pages host, no fonts or scripts from third parties.
  - Headless Chrome: 9 tabs, Original selected by default; click selects Graham; ArrowRight, End, wrap-around, ArrowLeft, and a single tab stop worked as expected.
  - 360 px: `scrollWidth` equals `clientWidth` with a long voice (Torvalds) selected.
  - Rendered a selected voice (Graham) at 1200 px: tabs, label, heading, dateline, and body read cleanly.
  - `git diff --check` clean; only `index.html` and this summary changed.
  - Not performed: screen reader testing and a no-script render with a scripting-disabled browser flag; the no-script state is the markup as served (hidden attributes, no tabs).
- Notes:
  - Page weight rose to about 78 KB, all in one file with no extra requests.

## Stage 5 - Polish and release readiness
- Changes:
  - `index.html` metadata: declared the favicon as 512×512 and added `og:image:width`/`og:image:height`; title, description, canonical URL, and og:image/URL were already correct for the new copy and were kept.
- Verification:
  - All in-page anchors (`#what`, `#workflow`, `#get-started`, `#main`) resolve to existing ids.
  - Local references: only `./railspike-logo.png`, which exists (4,388 bytes). External links: five distinct GitHub/Pages URLs; the repository and LICENSE URLs returned HTTP 200, and the `#quick-start` and `#ask-your-agent-to-update-railspike` fragments match existing README headings. The Pages URL was not fetched because the site is not yet published from this branch.
  - Repository checks: `check-markdown-links.py`, `.pl`, and `.mjs` each report 37 Markdown files with valid local links; `check-railspike-git-workflow.sh` passed.
  - No third-party requests: the page contains no external scripts, stylesheets, fonts, or images.
  - Rendering reviewed at 1200 px and 360 px, light and dark, including the selector with a long voice; no horizontal overflow.
  - `git diff --check` clean.
- Notes:
  - Not done here: the live Pages URL check and the maintainer's visual review; both happen after merge to `master` per the Completion Contract.
  - Open for the maintainer: whether to commit the `/temp/` entry in `.gitignore` (left uncommitted), and that this branch includes the unmerged quiet-debug-mode commits.
