> **Feature plan:** [Step 1](./readme_and_essay_homepage_step1_solution_assessment.md) · [Step 2](./readme_and_essay_homepage_step2_feature_description.md) · [Step 3](./readme_and_essay_homepage_step3_development_plan.md) · [Step 4](./readme_and_essay_homepage_step4_implementation_summary.md)

Debug mode: enabled

# Essay homepage

## Problem
The homepage should be the maintainer's own essay, as written, in a simple readable format, rather than designed marketing copy. The README already carries the reference material and stays as it is.

## User stories
- As a visitor, I want to read the maintainer's essay on a clean, fast page so that I understand the project in the author's own words.
- As the maintainer, I want the page to reproduce `railspike.txt` exactly so that no copy is rewritten by anyone else.
- As a reader who wants to try it, I want a way to reach the repository so that I am not left at a dead end.

## Core requirements
- The page's text is `railspike.txt` unchanged: every word, the existing typo ("promt"), and the punctuation. Only structure is added: paragraphs, the one bulleted list, and its first line as the page heading.
- Nothing else is written: no tagline, install block, wordmark, or new headings. The only addition is a single link to `https://github.com/gulkily/railspike`, shown as the URL itself.
- A single static `index.html` at the existing URL, with system fonts, light and dark by system preference, readable at 360 px, no scripts and no third-party requests.
- Metadata that is not shown on the page reuses essay wording (title from its first line, description from its first sentence); canonical, icon, and social image values stay as they are.

## Delivery scope
**Work type:** application change (static site).

**Allowed files:** `index.html` at the repository root, and this feature's plans, summary, debug log, organization under `docs/plans/readme_and_essay_homepage/`, and the `docs/plans/README.md` entry. `railspike.txt` is the source and is not edited or published as a file by this feature. `README.md`, scripts, the logo, CI, deployment, and workflow instructions are not changed.

## Completion boundary
Normal entry is the existing site URL after merge to `master` for Pages. A visitor reads the essay and can follow the repository link. Recovery: restore the previous `index.html` from `master` history. Release condition: the verbatim comparison passes, the maintainer reviews the rendered page in light, dark, and phone widths, then merge.

## Risks
- **Silent copy drift:** a conversion step could normalize quotes, spaces, or the typo. Impact: violates the central requirement. Validate by comparing the page's extracted text with `railspike.txt` after whitespace folding; mitigate by copying text, not retyping.
- **Lost content:** the install command, worked example, and limits section disappear from the page. Impact: weaker conversion for skeptical readers. Mitigation: they remain in Git history and, partly, in the README; the maintainer has accepted this.
- **Dead end:** the essay mentions "instructions included" and "git submodule" with no link. Mitigation: the single repository link; confirmed at review.
- **Visible typo:** accepted by the maintainer; it will appear on the live page.

## Shared component inventory
- `index.html`: replaced by a minimal new page; no shared components, framework, or script.
- `railspike.txt`: sole text source; read, not modified.
- Head metadata (canonical, icon, social image): values carried over from the current page.
- `README.md`, `docs/failure-modes.md`: unchanged and linked only through the repository.

## Simple user flow
1. Visitor opens the site and reads the essay.
2. Visitor follows the repository link at the end.

## Success criteria
- The page's visible text, apart from the link, equals `railspike.txt` after whitespace folding, including "promt".
- The page weighs under 15 KB, has no scripts or external requests, and has one `h1`.
- Text meets WCAG AA contrast in light and dark, with no horizontal scroll at 360 px.
- The repository link resolves, and the link check passes.
- The maintainer approves the rendered page.
