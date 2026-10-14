> **Feature plan:** [Step 1](./links_browser_accessibility_step1_solution_assessment.md) · [Step 2](./links_browser_accessibility_step2_feature_description.md) · [Step 3](./links_browser_accessibility_step3_development_plan.md) · [Step 4](./links_browser_accessibility_step4_implementation_summary.md)

Debug mode: disabled

Status: Archived at user request on 2026-10-13; deferred, not approved or implemented.

## Original Query

I have discovered that our website is not very accessible to the Links browser. It would be a pretty huge undertaking to make it look nice, huh? Please write Step 1 of railspike for this.

## Understood Intent

Assess how to make the website readable, navigable, and pleasant in Links, and whether that requires a major redesign. Assume terminal/text mode initially; the affected site profile, routes, and Links configuration remain to be confirmed.

## Problem Statement

The reported Links experience is poor, but we need to distinguish presentation and reading obstacles from features that depend on browser scripting before judging the size of the work.

## Existing Foundation

The current [navigation](../../../templates/partials/nav.php), [board](../../../templates/pages/board.php), and [thread cards](../../../templates/partials/thread_card.php) already contain server-rendered links and content; [Forte's board](../../../templates/pages/forte_board.php) adds panes and decorative menus, while [authentication](../../reference/authentication_modes.md) involves browser identity and signing. These are source observations, not a reproduced Links diagnosis.

## Option A — Improve the existing reading experience

Make the normal entry → board → thread → return journey readable and navigable in Links, improving shared presentation and providing clear explanations where actions cannot work.

- **Pros:** Reuses existing content and routes; benefits other limited browsers; smallest plausible complete user journey.
- **Cons:** Shared changes need modern-browser checks; complex themed pages may resist a clean reading order; does not deliver full interactive parity.

## Option B — Offer an explicit text reading mode

Provide a discoverable, simplified presentation for the same reading journey, sharing content and access rules with the main site.

- **Pros:** More control over clutter, ordering, and concise navigation; can preserve elaborate visual themes.
- **Cons:** Adds a presentation variant to maintain and test; entering and retaining the mode must itself work in Links; a CSS-only theme would not resolve interaction barriers.

## Option C — Support the whole application in Links

Extend compatibility across reading, posting, account access, reactions, private messages, and other interactive workflows.

- **Pros:** Broadest usable experience for Links users.
- **Cons:** Substantial multi-cycle effort; browser-held keys and encrypted workflows require separate design decisions, not just presentation changes.

## Recommendation and Vertical-Slice Viability

Start with **Option A**, using actual Links checks on the affected site to decide whether Option B is necessary. Making reading pleasant is not inherently a huge undertaking: content and ordinary links already exist. Full application parity is much larger, and no reliable time estimate is possible before reproducing the failures.

The first slice should let a visitor enter normally, browse and paginate a board, read a complete thread, and return through usable links, with clear empty/error states and explanations for unavailable actions. Preserve existing access restrictions; do not make private content public to support this journey. Confirm the target deployment's access mode before proceeding, since a sign-in barrier could invalidate a public-reading slice. Aim for work within one day or eight development stages; narrow the target profile if needed. Verify in Links and a modern browser before claiming compatibility. Identity redesign, encrypted messaging, and full theme parity belong in separate cycles.

Review or revise this assessment; **Approved Step 1** authorizes Step 2.
