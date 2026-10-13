> **Feature plan:** [Step 1](./website_redesign_step1_solution_assessment.md) · [Step 2](./website_redesign_step2_feature_description.md) · [Step 3](./website_redesign_step3_development_plan.md) · [Step 4](./website_redesign_step4_implementation_summary.md)

Debug mode: enabled

## Original Query
As the project maintainer, I want to redesign the website (currently index.html) using the following materials in temp for inspiration:
- Railspike Readings for the explanation/what this project is material
- cross-section icon for the new logo (just the color was changed)
- the designs in qdb_design_quotes and qdb_design_welcome as the general look and feel, though you can modernize it.

Please write step 1 of Railspike.

## Understood Intent
Replace the current `index.html` with a redesigned single site: the cross-section icon as the logo, the "Railspike Readings" essay as the explanation of what Railspike is, and a modernized take on QDB's look (amber title bar, monospace text, compact slash-separated navigation, bordered content blocks). The `temp/` files are inspiration only; their text and assets are not published as-is.

## Problem
The current site does not present Railspike's story or identity in the maintainer's intended voice and visual style.

## Option A — Single-page refresh
Keep `index.html` as one static page. Restyle it in a modernized QDB look, swap in the new logo, and replace the explanation with the "Original" essay from the Readings, keeping the existing install/start-here content below it.

- **Pros:** Smallest change; keeps current URL, GitHub Pages setup, and no-build hosting; one coherent slice that is easy to review.
- **Cons:** Only one voice of the explanation; limited room for the QDB-style multi-link navigation.

## Option B — Single page with a version switcher
As Option A, but present the Readings' alternate voices on the same page through a lightweight selector, defaulting to the Original.

- **Pros:** Keeps the Readings' distinctive character; still one page and one URL.
- **Cons:** Needs interactivity and review of nine texts; larger content surface; risks burying the plain explanation.

## Option C — Small multi-page site
Add separate pages (Home, About/Readings, Docs links) with a QDB-style top navigation bar, sharing one stylesheet.

- **Pros:** Closest to the QDB structure; room to grow.
- **Cons:** More files and links to verify; new navigation and canonical-URL decisions; outsized for the current content.

## Original Recommendation (superseded)
Option A, deferring the alternate voices as a follow-up.

## Revised Direction
The user selected **Option B** (revision, not yet approval).

Build one redesigned `index.html` with the new logo and a modernized QDB look that works in light/dark and on mobile. The "what is Railspike" section presents the Readings' voices through a lightweight selector, with the Original as the default and the plain explanation visible without interaction.

Rechecked tradeoffs and risks:
- **Content review:** all nine texts need a read-through for accuracy and tone; Step 2 should confirm whether all nine ship or a curated subset.
- **Static and accessible:** the selector must work in a static GitHub Pages page, be keyboard-usable, and show the Original if scripting is unavailable.
- **Provenance:** the Readings and QDB captures in `temp/` are inspiration; QDB markup, quotes, and assets are not copied.

Vertical slice: the page loads at the existing URL, explains the project, lets a visitor switch voices, links to install and docs, and shows the new logo/favicon/social image. Needs a web-optimized logo asset (the source icon is ~1.3 MB) and confirmation that the icon's color is final as supplied.
