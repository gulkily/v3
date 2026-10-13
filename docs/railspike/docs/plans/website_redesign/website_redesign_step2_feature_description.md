> **Feature plan:** [Step 1](./website_redesign_step1_solution_assessment.md) · [Step 2](./website_redesign_step2_feature_description.md) · [Step 3](./website_redesign_step3_development_plan.md) · [Step 4](./website_redesign_step4_implementation_summary.md)

Debug mode: enabled

# Website redesign

## Problem
The current `index.html` does not tell Railspike's story in the maintainer's own words or carry its intended identity. Visitors need a clear "what is this" explanation, a recognizable logo, and a path to install and docs.

## User stories
- As a first-time visitor, I want a plain explanation of Railspike up front so that I can decide quickly whether it fits my work.
- As a curious reader, I want to switch between the Readings' voices so that I can find the telling that makes sense to me.
- As the maintainer, I want the site to carry the new logo and a distinctive look so that Railspike is recognizable when shared.

## Core requirements
- The "what is Railspike" section defaults to the Original essay and is readable without interaction or scripting; the voice selector is an enhancement, and every voice stays labeled as a style, not an endorsement.
- The cross-section icon is the site logo, favicon, and social image, served from a web-optimized copy.
- The look is a modernized QDB feel (amber accent bar, monospace text, compact slash-separated navigation, bordered blocks) that works in light/dark and at phone width.
- Existing install/start-here content, links, canonical URL, and GitHub Pages hosting keep working with no build step or required third-party services.
- Nothing from the QDB captures (markup, quotes, assets) is published; `temp/` stays unpublished.

## Delivery scope
**Work type:** application change (static site).

**Allowed files:** `index.html` and web-optimized logo assets at the repository root, with the README logo reference updated only if the asset name changes. CI, deployment settings, scripts, and workflow instructions are not changed. Voice texts are copied from `temp/Railspike Readings.html` and edited only to fit the page.

## Completion boundary
Normal entry is the existing site URL. A visitor reads the Original explanation, switches voices using mouse or keyboard, follows install and docs links, and sees the new logo in the header, tab, and shared-link preview. Recovery: without scripting, the Original remains visible and other voices are reachable or omitted cleanly; the change is reverted by restoring the previous `index.html`. Release condition: maintainer review of the rendered page in light/dark and phone widths, then merge to `master` for Pages.

## Risks
- **Real-person pastiche:** several voices imitate named people (e.g. Jobs, Graham, Torvalds, dang) and could read as fabricated quotes or implied endorsement. Decide before Step 3 whether to ship them, label them prominently, or use a curated subset.
- **Content volume:** nine texts expand review and page weight. Confirm the voice list with the maintainer before Step 3; mitigate by shipping a subset or loading text only for the chosen voice.
- **Logo asset:** the supplied icon is ~1.3 MB and its color is stated final but unverified. Confirm and export a small optimized copy early in Step 3; keep the current logo file until replaced.
- **Hidden dependencies:** the Readings prototype uses web fonts from a third party. Use system fonts or self-hosted ones so the page stays self-contained and private.

## Shared component inventory
- `index.html` header/brand, nav, hero, steps, install block, footer: restyled and extended in place, no new page or framework.
- `railspike-logo.png`: referenced by `index.html` (favicon, header, og:image) and `README.md`; replace with the new optimized logo under a single canonical asset name.
- The Readings' tablist selector is the only new interactive component; reuse its structure, not its full-page layout.

## Simple user flow
1. Visitor opens the site and sees the logo, navigation, and the Original explanation.
2. Visitor optionally chooses another voice; the text changes, the Original stays one click away.
3. Visitor follows the start-here or install link into the repository docs.

## Success criteria
- The page renders correctly at 360 px and desktop widths in light and dark, with no horizontal scroll.
- The Original text is present with scripting disabled; selecting every shipped voice works by keyboard and mouse.
- The new logo appears as header logo, favicon, and og:image, with the logo asset under 100 KB.
- All internal and external links resolve; the repository link check passes; no request goes to a third-party host.
- The maintainer approves the rendered result against the QDB look and the Readings content.
