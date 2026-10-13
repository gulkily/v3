> **Feature plan:** [Step 1](./homepage_from_scratch_step1_solution_assessment.md) · [Step 2](./homepage_from_scratch_step2_feature_description.md) · [Step 3](./homepage_from_scratch_step3_development_plan.md) · [Step 4](./homepage_from_scratch_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
- Normal entry: the existing site URL, served from `master` by GitHub Pages. Outcome: a visitor reads the claim and install command, the real worked example, and the limits, and follows links into the repository.
- Allowed changes: `index.html` (new, replacing the removed file) plus this feature's plans, summary, debug log, organization under `docs/plans/homepage_from_scratch/`, and the `docs/plans/README.md` entry. No logo, README, scripts, CI, deployment, workflow-instruction, or `~/v3` content changes.
- Recovery: restore the previous `index.html` from `master` history.
- External verification: maintainer reviews the render in light, dark, and phone widths; the live Pages URL is checked after merge. Release condition: maintainer approval and a passing link check, then merge.

## Key Risks
- **Generic or overclaiming copy:** the page is its prose. Validate: copy and claim-source table reviewed in Stage 1 before any styling. Mitigation: cut every claim without a source.
- **High risk: blank or broken site on release:** the old page is deleted on this branch. Validate: the first commit that touches `index.html` is a complete working page (Stage 1). Mitigation: never commit the deletion alone.
- **Excerpt drift or exposure:** quoted text may not match files. Validate: byte comparison against the source commit in Stage 2. Mitigation: use only this repository's committed files, verbatim, with links.
- **Weight and accessibility regressions:** validate sizes, contrast, and no-script rendering in Stages 4 and 6. Mitigation: system fonts, no scripts.

## Stage 1
- Goal: A complete, unstyled, working page with the claim, what it is, and the install command (≤1 hour).
- Dependencies: Approved Step 3; feature branch and planning commit.
- Expected changes: New `index.html` with semantic headings and plain content; a claim-to-source table recorded in the summary.
- Verification approach: Each claim matches the README or workflow files; renders with no styles; links resolve.
- Risks or open questions:
  - Impact: unsupported claim or a broken install command.
  - Early warning / validation: claim-source table and a command copy check against the README.
  - Mitigation: remove or reword the claim.
- Canonical components/API contracts touched: `index.html`; README and workflow files as sources.

## Stage 2
- Goal: Real worked example with verbatim excerpts and source links (≤1 hour).
- Dependencies: Stage 1 committed.
- Expected changes: Add a section quoting a request, a Step 1 option excerpt, an approval, and stage commit lines from this repository's plans and `git log`.
- Verification approach: Diff each excerpt against its source file or commit; check that each link resolves.
- Risks or open questions:
  - Impact: misquote or stale link.
  - Early warning / validation: scripted text comparison.
  - Mitigation: link to a commit hash, not a branch.
- Canonical components/API contracts touched: `index.html`; committed plan files under `docs/plans/`.

## Stage 3
- Goal: Limits and failure-modes section, footer, and calls to action (≤1 hour).
- Dependencies: Stage 2 committed.
- Expected changes: Add the candid limits section (instructions, not enforcement; overkill cases), link `docs/failure-modes.md`, and add repository, license, and update links.
- Verification approach: Compare statements with the README and `docs/failure-modes.md`; link check.
- Risks or open questions:
  - Impact: overstatement or a missing caveat.
  - Early warning / validation: read-through against the source docs.
  - Mitigation: quote the source wording.
- Canonical components/API contracts touched: `index.html`; `docs/failure-modes.md` (linked only).

## Stage 4
- Goal: Text-first styling: system fonts, light and dark, first-screen layout, 360 px (≤1 hour).
- Dependencies: Stage 3 committed.
- Expected changes: Inline stylesheet with tokens, readable measure, code-block styling, and prefers-color-scheme support.
- Verification approach: Render at 1280×720 and 360×640 in both schemes; confirm the claim and install command are on the first screen; no horizontal scroll.
- Risks or open questions:
  - Impact: claim pushed below the fold or low contrast.
  - Early warning / validation: screenshots and computed contrast.
  - Mitigation: reduce top spacing; adjust shades.
- Canonical components/API contracts touched: `index.html` stylesheet.

## Stage 5
- Goal: Metadata, logo reference, and accessibility pass (≤1 hour).
- Dependencies: Stage 4 committed.
- Expected changes: Title, description, canonical, social tags, favicon and header logo using the existing file name, skip link, landmarks, and focus styles.
- Verification approach: Inspect head tags against the old values; keyboard walkthrough; heading order check.
- Risks or open questions:
  - Impact: lost link previews or broken canonical.
  - Early warning / validation: compare with `master`'s metadata.
  - Mitigation: keep the canonical URL and image path unchanged.
- Canonical components/API contracts touched: `index.html` head; `railspike-logo.png` reference only.

## Stage 6
- Goal: Release verification and maintainer review (≤1 hour).
- Dependencies: Stage 5 committed.
- Expected changes: None to behavior; fix defects found.
- Verification approach: File size under 50 KB, no third-party requests, no-script render, link check, final excerpt comparison, then maintainer review in light, dark, and phone widths.
- Risks or open questions:
  - Impact: unnoticed regression or copy the maintainer rejects.
  - Early warning / validation: the checklist above plus review.
  - Mitigation: revise within scope; return to Step 2 for scope changes.
- Canonical components/API contracts touched: whole page.
