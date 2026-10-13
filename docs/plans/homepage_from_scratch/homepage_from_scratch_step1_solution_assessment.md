> **Feature plan:** [Step 1](./homepage_from_scratch_step1_solution_assessment.md) · [Step 2](./homepage_from_scratch_step2_feature_description.md) · [Step 3](./homepage_from_scratch_step3_development_plan.md) · [Step 4](./homepage_from_scratch_step4_implementation_summary.md)

Debug mode: enabled

## Original Query
OK, that does not look as good as I had hoped. Let us start over from scratch. Please develop from scratch a new static home page for this project. Get rid of the old designs first. Write Step 1 of Railspike. Debug mode on. The target audience is Hacker News, tildes.net, lobste.rs, and generally developers, and perhaps Cambridge-area MIT and Harvard types.

## Understood Intent
Replace the site's homepage with a new design that is not derived from the QDB restyles. The old `index.html` is removed on the `feature/homepage-from-scratch` branch (uncommitted; `master` and the unmerged `feature/qdb-nostalgia-redesign` branch are untouched). The page should read as credible and well made to technically skeptical readers on link-aggregator sites and in academic engineering circles.

## Problem
The site must quickly convince a skeptical, technically literate reader that Railspike is a real, useful, and honestly described workflow, and the prior designs did not.

## Option A — Plain document
A single, text-first page that reads like a good README or short essay: a clear one-paragraph claim, the problem, how the four steps work, an install command, and links to the source. System fonts, one restrained accent, light/dark via system preference, no scripting required.

- **Pros:** Fast, trustworthy, and familiar to the audience; easy to review and maintain; passes for craft through writing rather than decoration.
- **Cons:** Easy to look generic; relies entirely on the quality of the prose to stand out.

## Option B — Show the artifact
Lead with the real thing: a worked example built from this repository's own plan files, showing a request, its Step 1 assessment, the approval gate, and the stage commits, with the explanation reduced to a few lines around it. The reader sees what the workflow produces before being told what it is.

- **Pros:** Strongest proof for skeptics ("show, don't tell"); naturally honest; reuses real artifacts.
- **Cons:** Needs a well-chosen, trimmed example; longer page; risks overwhelming first-time readers without a plain summary above it.

## Option C — Technical-paper layout
Format the page as a short paper or design note: abstract, motivation, method, a failure-modes section, and references, with numbered figures of the four steps. Appeals to academic readers.

- **Pros:** Distinctive; invites discussion of tradeoffs and failure modes, which this audience values; a natural home for `docs/failure-modes.md`.
- **Cons:** Heaviest writing effort; can read as pretentious for a prompting workflow; weaker as a quick install pitch.

## Original Recommendation (superseded)
Option A as the frame with Option B's worked example as the centerpiece, borrowing C's failure-modes section.

## Revised Direction
The user chose to **combine all three options** (revision, not yet approval): the plain document frame, a real worked example, and a paper-style design note, with real examples drawn from `~/v3` (a large project built with Railspike: about 2,100 commits, 197 Step 1 and 180 Step 4 plan files, counted from its `docs/plans`). They also asked whether this should be **three separate Railspikes**, and for the logo's spike to be **golden with a slight glow**.

Recommended split, as three features, each its own vertical slice and branch:
- **1. Logo:** `logo_golden_spike`, an asset change: the current spike is flat bronze (`#c08000`); make it gold with a soft glow, legible down to favicon size and on light and dark pages. Smallest, no dependencies, ships first.
- **2. Paper:** `railspike_paper`, documentation-only: a short design note (motivation, method, real v3 excerpts, failure modes) published in this repository. Stands alone and is linkable.
- **3. Homepage:** `homepage_from_scratch` (this plan), an application change: the plain page of Option A that leads to the paper and shows a short real example, using the new logo. Depends on 1 and 2 for final assets and links.

Rechecked tradeoffs and risks:
- **Why split:** one feature spanning an image asset, a long document, and a page would exceed the eight-stage limit and mix work types (asset, documentation-only, application); splitting keeps each reviewable and revertible. **Cost:** three approval cycles and a dependency order.
- **Provenance and privacy:** `~/v3` excerpts may contain private names, keys, or product detail. Step 2 for the paper must confirm what may be published and that excerpts are quoted unmodified and attributed.
- **Logo:** glow reads poorly at 16–32 px and on dark pages, and the supplied source image is 1254 px while the shipped file is 512 px; Step 2 for the logo should set the gold shade, glow limits, and sizes.
- **Interim state:** on this branch the old homepage is deleted; `master` keeps serving it until the homepage feature merges, so nothing is blank in the meantime.

Suggested order: logo, then paper, then homepage. Step 2 for this feature would cover the homepage only, and the other two begin as their own Step 1 documents after this one is approved.
