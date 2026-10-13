> **Feature plan:** [Step 1](./readme_and_essay_homepage_step1_solution_assessment.md) · [Step 2](./readme_and_essay_homepage_step2_feature_description.md) · [Step 3](./readme_and_essay_homepage_step3_development_plan.md) · [Step 4](./readme_and_essay_homepage_step4_implementation_summary.md)

Debug mode: enabled

## Stage 1 - Verbatim unstyled page
- Changes:
  - `index.html` (6.7 KB) replaced with markup generated from `railspike.txt` by script, not retyped: first line as the single `h1`, blank-line blocks as paragraphs, the one hyphen list as `<ul>`, and a footer with the repository link shown as its URL.
  - Head: title from the essay's first line, description and `og:description` from its first sentence; canonical, icon, and social image values unchanged. No scripts or styles yet.
- Verification:
  - Source: `railspike.txt`, UTF-8, no carriage returns, SHA-256 `575d95c6b2d20bea9d71ed237983d9494613a6b9389da3323ed4587b51c6e37b` (untracked; not committed by this feature).
  - Text comparison: page body text (footer link excluded), whitespace folded, equals the source with list markers removed; 5,113 characters each; "promt" is present.
  - One `h1`; zero `<script>` and `<style>` elements.
- Notes:
  - The link is the only added text and is the URL itself.
  - Styling, contrast, and head comparison with `master` follow in Stage 2.

## Stage 2 - Simple styling and metadata check
- Changes:
  - Inline stylesheet in `index.html`: system serif at 1.125rem, a 40rem measure, light and dark through `prefers-color-scheme`, link and focus styles, and a quiet footer for the repository link; `color-scheme` and `theme-color` metas added.
  - `overflow-wrap: anywhere` on paragraphs and list items so long tokens wrap; no text changed.
- Verification:
  - Contrast (WCAG): light text 17.2, muted 8.1, link 8.0; dark text 14.9, muted 8.4, link 8.8.
  - Width: an iframe at 360 px first showed horizontal overflow (scrollWidth 377 vs 345) from a paragraph with a long unbreakable token; after the wrap rule scrollWidth equals clientWidth (345).
  - Head comparison with `master`: canonical, icon, `og:url`, `og:image` and its width, height, and alt, `og:type`, and `twitter:card` are identical.
  - Verbatim check re-run after styling: page text equals `railspike.txt` (hash prefix `575d95c6b2d20bea`), "promt" present, one `h1`, zero scripts; page is 7.9 KB.
  - Light render at 900 px and dark render (media query unwrapped; headless Chrome could not emulate the system preference) inspected.
- Notes:
  - Real OS dark-mode switching and non-Chromium browsers were not tested.
  - The page's old `theme-color` (amber) belonged to the removed design and is replaced by light and dark values.

## Stage 3 - Release verification
- Changes:
  - None to `index.html`; verification only.
- Verification:
  - Weight and dependencies: 7.9 KB (limit 15 KB); zero scripts, external stylesheets, fonts, images, or `url()` references, so no third-party requests and it works without scripting by construction; one `h1`.
  - Verbatim: re-run in Stage 2 after the last markup change; no change to `index.html` since.
  - Links: repository link returns HTTP 200; the page's other references are the canonical URL and the local logo file; `node scripts/check-markdown-links.mjs` passes for 47 Markdown files.
  - Scope: only `index.html` and this feature's plan files changed on the branch; `railspike.txt` remains untracked and unmodified.
- Notes:
  - Pending the maintainer: review of the rendered page in light, dark, and phone widths, then merge; check the live Pages URL after deploy.
  - Not exercised: real OS dark-mode toggle, non-Chromium browsers, and screen readers.
  - Content removed from the homepage by design (install command, worked example, limits) remains in Git history; the README is unchanged.
