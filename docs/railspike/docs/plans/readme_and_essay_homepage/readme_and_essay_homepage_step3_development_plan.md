> **Feature plan:** [Step 1](./readme_and_essay_homepage_step1_solution_assessment.md) · [Step 2](./readme_and_essay_homepage_step2_feature_description.md) · [Step 3](./readme_and_essay_homepage_step3_development_plan.md) · [Step 4](./readme_and_essay_homepage_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
- Normal entry: the existing site URL, served from `master` by GitHub Pages. Outcome: a visitor reads the essay as written and can follow the repository link.
- Allowed changes: `index.html` only, plus this feature's plans, summary, debug log, organization under `docs/plans/readme_and_essay_homepage/`, and the `docs/plans/README.md` entry. No README, script, logo, CI, deployment, workflow-instruction, or `railspike.txt` changes.
- Recovery: restore the previous `index.html` from `master` history.
- External verification: the maintainer reviews the render in light, dark, and phone widths; the live Pages URL is checked after merge. Release condition: verbatim comparison passes and the maintainer approves, then merge.

## Key Risks
- **High risk: copy drift:** retyping or normalizing text would violate the central requirement. Validate: scripted comparison of page text against `railspike.txt` in Stage 1 and again in Stage 3. Mitigation: generate the markup from the file, never retype it.
- **Unreproducible evidence:** `railspike.txt` is untracked. Validate: record its SHA-256 in the summary at Stage 1. Mitigation: the source is not committed by this feature, so the hash ties the check to the exact file.
- **Lost content:** the install block, example, and limits leave the page. Accepted by the maintainer; they remain in Git history.
- **Metadata regressions:** validate head values against `master` in Stage 2. Mitigation: keep canonical, icon, and social image unchanged.

## Stage 1
- Goal: A complete, unstyled page whose text is `railspike.txt` verbatim (≤1 hour).
- Dependencies: Approved Step 3; feature branch and planning commit.
- Expected changes: Replace `index.html` with minimal semantic markup generated from the file: first line as `h1`, paragraphs, the single list, a repository link, and head metadata reusing essay wording.
- Verification approach: Extract the page's text, fold whitespace, compare with `railspike.txt` (apart from the link); confirm "promt" is present; record the file hash.
- Risks or open questions:
  - Impact: a normalized quote, spacing, or the corrected typo.
  - Early warning / validation: a character-level diff, not a visual check.
  - Mitigation: regenerate from the source until the diff is empty.
- Canonical components/API contracts touched: `index.html`; `railspike.txt` as read-only source.

## Stage 2
- Goal: Simple styling: system fonts, light and dark, 360 px layout, and metadata check (≤1 hour).
- Dependencies: Stage 1 committed.
- Expected changes: Inline stylesheet with a readable measure, color tokens for both schemes, link and focus styles; no new text.
- Verification approach: Render at 1280×720 and 360×640 in both schemes; compute contrast; confirm no horizontal scroll; compare head tags with `master`.
- Risks or open questions:
  - Impact: low contrast or overflow from the long list lines.
  - Early warning / validation: computed contrast and overflow check.
  - Mitigation: adjust shades and wrapping.
- Canonical components/API contracts touched: `index.html` stylesheet and head.

## Stage 3
- Goal: Release verification and maintainer review (≤1 hour).
- Dependencies: Stage 2 committed.
- Expected changes: None to behavior; fix defects found.
- Verification approach: Re-run the verbatim comparison; check size under 15 KB, no scripts or external requests, one `h1`; repository link and link check; then maintainer review in light, dark, and phone widths.
- Risks or open questions:
  - Impact: unnoticed drift after styling or a rejected look.
  - Early warning / validation: the comparison and review.
  - Mitigation: revise within scope; return to Step 2 for scope changes.
- Canonical components/API contracts touched: whole page.
