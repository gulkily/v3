> **Feature plan:** [Step 1](./mitrapclub_about_copy_step1_solution_assessment.md) · [Step 2](./mitrapclub_about_copy_step2_feature_description.md) · [Step 3](./mitrapclub_about_copy_step3_development_plan.md) · [Step 4](./mitrapclub_about_copy_step4_implementation_summary.md)

## Original Query

Please write Step 1 of @docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md for the next piece, which we'll move to a new chat.

## Understood Intent

- "The next piece" is read as **Cycle 2 ("De-genericize copy")** from `docs/plans/mitrapclub_theme_and_features_checklist.md` — the next unstarted item after Cycle 1 ("Branding fixes"), which already shipped on `feature/mitrapclub-branding-fixes`.
- Context for a fresh chat picking this up: the `mitrapclub` site profile (a new site_id on this repo's shared multi-site forum app, same pattern as `chouse`/`qdb`) already exists on `main`. Its `/about/` page currently shows three sections — "A continuous social graph," "How participation works," "Portable by design" — plus a fixed intro sentence, all hardcoded identically across every profile (`src/ForumRewrite/ProfilePresentationContent.php::about()`, `templates/pages/about.php`). They describe this app's OpenPGP identity/approval/backup machinery in generic, infra-level language, not voiced for a rap club's visitors.
- This Step 1 covers only that about-page copy question — not the other remaining checklist cycles (visual identity, events experience, media embeds), which stay unscheduled.

## Problem Statement

The `mitrapclub` about page's identity/participation/backup sections and intro sentence are generic infra copy shared verbatim by every site profile, not voiced for a rap club's visitors.

## Solution Options

- **Option A: Hide these sections for `mitrapclub`.** Add a presentation-slot gate (same pattern as the existing `showHackableSection` boolean) so the about page for `mitrapclub` shows only the intro, club-community content, and social links — no identity/backup/API sections.
  - Pros: smallest visible footprint for a casual fan/visitor audience; cheapest to build (one boolean gate, no new copy to write); precedent already exists in the codebase.
  - Cons: removes real information about how posting/identity/moderation works from the one page most likely to explain it to a new visitor, even though a club board still needs members to understand that to participate.
- **Option B: Rewrite the copy club-voiced, keep the content.** Write `mitrapclub`-specific versions of the intro sentence and all three sections that explain the same identity/approval/backup mechanics, but in club voice instead of infra-treatise prose — same pattern as Cycle 1's busy-page rewrite.
  - Pros: visitors still learn how posting/identity/moderation actually works (relevant on a board where members approve each other), now in a voice that fits the club; consistent with the precedent just set in Cycle 1.
  - Cons: more writing than Option A (four pieces of copy instead of one boolean); slightly more plumbing if the fixed intro sentence needs to become per-profile data rather than a hardcoded string.
- **Option C: Keep the copy, de-emphasize it.** Leave all text unchanged; reorder the about page so club-specific content (intro + community + social links) leads, and collapse the three infra sections behind a "technical details" disclosure.
  - Pros: smallest code change — pure template reorder/collapse, no new copy and no new data plumbing; nothing is lost for any profile.
  - Cons: doesn't actually solve "not club-voiced" — the underlying text is still generic infra prose, just less prominent.

## Recommendation

**Option B.** It's the one that actually fixes "not club-voiced" rather than just hiding or burying the problem, keeps genuinely useful participation/identity information visible (this is a board where members approve each other, so that context matters), and extends the same club-voiced-copy pattern Cycle 1 already established for the busy page. Options A and C are worth keeping in mind as fallbacks if Step 2/3 finds the per-profile plumbing for a rewritten intro sentence more invasive than expected.

**Vertical-slice viability:** Yes. Entry is a visitor loading `/about/` on `mitrapclub`; outcome is the full about page read end-to-end in club voice, with identity/participation/backup information still present; no recovery path needed (static content); no regression to `zenmemes`/`chouse`/`qdb`, whose copy stays untouched.

Waiting for "Approved Step 1" before drafting Step 2.
