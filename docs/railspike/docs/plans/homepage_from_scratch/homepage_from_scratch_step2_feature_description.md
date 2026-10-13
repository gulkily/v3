> **Feature plan:** [Step 1](./homepage_from_scratch_step1_solution_assessment.md) · [Step 2](./homepage_from_scratch_step2_feature_description.md) · [Step 3](./homepage_from_scratch_step3_development_plan.md) · [Step 4](./homepage_from_scratch_step4_implementation_summary.md)

Debug mode: enabled

# Homepage from scratch

## Problem
A skeptical technical reader landing on Railspike needs to understand within seconds what it is, see real evidence that it works, and learn where it falls short. The old page is removed on this branch; its replacement must do that without relying on decoration. This feature covers the homepage only; the logo and the paper are separate features.

## User stories
- As a developer arriving from Hacker News, tildes.net, or lobste.rs, I want a plain statement of what Railspike is and how to install it so that I can judge it in under a minute.
- As a skeptical reader, I want to see real plan files and commits from an actual project so that I can judge the output instead of taking claims on trust.
- As an academic-minded reader, I want the limits and failure modes stated candidly so that I can trust the rest of the page.
- As the maintainer, I want a small page with no dependencies that I can keep accurate by editing one file.

## Core requirements
- The first screen states the claim ("make your coding agent write the plan down first"), what it is (Markdown instructions with approval gates, no CLI or runtime), and the install command, with no scripting required.
- A real worked example from this repository's own plan files is shown as quoted, unmodified excerpts with links to the source files, covering a request, a Step 1 assessment, an approval, and its stage commits.
- A "limits and failure modes" section states that the gates are instructions, not enforcement, when Railspike is overkill, and links `docs/failure-modes.md`.
- One static `index.html` at the existing URL: text-first, system fonts, light and dark by system preference, usable at 360 px, no third-party requests, trackers, or required scripts.
- Every factual claim traces to the README, the workflow files, or a linked commit; no unsupported benchmarks or testimonials.

## Delivery scope
**Work type:** application change (static site).

**Allowed files:** `index.html` at the repository root, and this feature's plans, summary, debug log, organization under `docs/plans/homepage_from_scratch/`, and `docs/plans/README.md` entry. `railspike-logo.png` is referenced under its current name and is replaced by the logo feature without markup changes. The paper is linked once published, otherwise the README and `docs/failure-modes.md` are linked. README, scripts, CI, deployment, and workflow instructions are not changed.

## Completion boundary
Normal entry is the existing site URL after merge to `master` for Pages. A visitor reads the claim, copies the install command, reads the worked example and the limits, and follows links into the repository. Recovery: restore the previous `index.html` from `master` history. Release condition: maintainer review of the rendered page in light, dark, and phone widths, plus a link check, then merge.

## Risks
- **Generic or overclaiming copy:** prose is the whole design. Impact: reads as marketing to this audience. Validate early with a copy draft reviewed before layout; mitigate by sourcing each claim and cutting any that cannot be.
- **Cross-feature dependency:** the page wants the new logo and the paper. Impact: blocked or stale links. Mitigate by shipping with the current logo and repository links; swap later as a one-line change.
- **Excerpt accuracy and privacy:** quoted plan text could drift or expose something unintended. Validate by copying verbatim from this repository's committed files and linking each; use `~/v3` material only through the paper feature.
- **Page weight and accessibility regressions:** validate with size, contrast, and no-script checks in Step 3 stages.

## Shared component inventory
- `index.html`: the only page; written new, no framework or build step.
- `railspike-logo.png`: existing asset referenced for favicon, header, and social image; unchanged here.
- README, `docs/failure-modes.md`, and the plan files: source of claims and excerpts, linked rather than copied wholesale.
- Canonical URL, metadata, and GitHub Pages setup: kept as they are.

## Simple user flow
1. Visitor opens the site and reads the claim and install command.
2. Visitor scrolls through the real example, following links to the source files.
3. Visitor reads the limits and failure modes.
4. Visitor installs from the command or opens the repository.

## Success criteria
- Claim and install command are visible on the first screen at 1280×720 and 360×640 without scrolling.
- HTML is under 50 KB with no third-party requests; the whole page works with scripting disabled.
- Text meets WCAG AA contrast in light and dark; no horizontal scroll at 360 px.
- Each claim and excerpt links to its source, and the link check passes.
- The maintainer approves the rendered page and copy.
