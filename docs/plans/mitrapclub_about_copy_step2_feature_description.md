> **Feature plan:** [Step 1](./mitrapclub_about_copy_step1_solution_assessment.md) · [Step 2](./mitrapclub_about_copy_step2_feature_description.md) · [Step 3](./mitrapclub_about_copy_step3_development_plan.md) · [Step 4](./mitrapclub_about_copy_step4_implementation_summary.md)

## Problem

The `mitrapclub` about page's intro sentence and three sections explaining identity/approval ("A continuous social graph," "How participation works") and backup/API ("Portable by design") are hardcoded identically for every site profile, in generic infra language that doesn't speak to a rap club's visitors.

## User Stories

- As a `mitrapclub` visitor, I want the about page's intro and its explanation of how posting/moderation/backup works to speak in the club's voice, so the page feels like it's actually about the club rather than generic platform infra.
- As a member of another profile (`zenmemes`/`chouse`/`qdb`), I want the about page to keep reading exactly as it does today, so this change has no effect on my site.

## Core Requirements

- The about page's intro sentence and the three sections' headings/paragraphs become per-profile content (extending the existing `ProfilePresentationContent::about()`/`EDITORIAL` mechanism that already drives `communityHeading`/`communityParagraphs`), not hardcoded strings in `templates/pages/about.php`.
- `zenmemes`/`chouse`/`qdb` get their exact current wording as their per-profile values — no visible change to those profiles.
- `mitrapclub` gets new club-voiced copy for all four pieces (intro + 3 sections), preserving the same underlying meaning (how identity/posting/approval/backup actually work) rather than removing the information — including the functional inline links (`/users/`, `/activity/`, `/tools/backup/`, `/api/`, `/llms.txt`) those paragraphs currently carry.
- No structural change to the about page beyond content: same section order, same `chouse`-only hackable-section gate, same social-links block from the prior feature.

## Delivery Scope

- **Work type:** Application change.

## Completion Boundary

- **Normal entry:** a visitor loads `/about/` on any profile.
- **End-to-end outcome:** `mitrapclub`'s about page reads fully in club voice (intro + all three sections) while still explaining identity/posting/backup; other profiles render byte-identical to today.
- **Needed recovery:** none beyond today's behavior — static content, no new failure mode.
- **Release condition:** `./v3 test` passes in full; `zenmemes`/`chouse`/`qdb` about-page HTML is unchanged.

## Risks

- Moving 4 pieces of copy into per-profile data means every existing profile needs explicit matching values (even if identical to today), growing `ProfilePresentationContent::EDITORIAL`. Earliest validation: Step 3 sizing. Mitigation: keep new fields plain (`heading` + `list<string>` paragraphs), matching the existing `communityHeading`/`communityParagraphs` shape exactly.
- Writing genuinely good club-voiced copy for identity/moderation/backup mechanics (not just flavor text like the busy page) is a content-quality judgment call, not a pass/fail test. Earliest validation: your review of the rendered about page in Step 4. Mitigation: keep the meaning faithful to today's text; treat wording as iterable, not blocking.
- Confirmed: `templates/pages/about.php` currently hardcodes the three sections' headings and paragraphs directly in markup (only `introduction` and `communityHeading`/`communityParagraphs` are already data-driven) — including inline links to `/users/`, `/activity/`, `/tools/backup/`, `/api/`, `/llms.txt`. Making them per-profile needs a template change, not just new data, and those links must be preserved or deliberately relocated, not dropped. Earliest validation: Step 3 stage sizing. Mitigation: pass headings/paragraphs through `aboutContent` the same way `communityHeading`/`communityParagraphs` already work; keep the same section structure and `data-about-section` attributes.
- Scope creep toward Option A (hiding) or Option C (reordering) from Step 1. Mitigation: Step 3 holds to Option B's shape — rewrite, don't hide or reorder.

## Shared Component Inventory

- `src/ForumRewrite/ProfilePresentationContent.php::about()` / `EDITORIAL` — reuse and extend this existing per-profile content mechanism (already carrying `communityHeading`/`communityParagraphs`, `busy*`, `socialLinks`); add an intro-text field and three heading/paragraphs pairs.
- `templates/pages/about.php` — reuse the existing about-section markup, selectors, and section order; swap the three sections' hardcoded headings/paragraphs for data-driven ones from `aboutContent`, same as `communityHeading` already does.
- `src/ForumRewrite/Http/AboutPageController.php` — unchanged; already passes `ProfilePresentationContent::about(...)` straight through.

## Simple User Flow

1. Visitor opens `/about/` on `mitrapclub`.
2. Reads an intro, community section, and now club-voiced identity/participation/backup sections, plus the existing social-links block.
3. Visitor on `zenmemes`/`chouse`/`qdb` opens `/about/` and sees no change.

## Success Criteria

- `mitrapclub`'s `/about/` renders the new club-voiced intro and all three section's copy, with the same functional links preserved.
- `zenmemes`/`chouse`/`qdb` about pages render identical HTML to before this change.
- `./v3 test` passes in full.

Waiting for "Approved Step 2" before drafting Step 3.
