> **Feature plan:** [Step 1](./engagify_theme_step1_solution_assessment.md) · [Step 2](./engagify_theme_step2_feature_description.md) · [Step 3](./engagify_theme_step3_development_plan.md) · [Step 4](./engagify_theme_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/engagify_theme`; [host ledger](../effort_estimation_trial.md#cycle-v3engagify_theme). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.75 (revised; original 0.70)

## Original Query

I'd like to build a parody theme that has common anti-features in the UI, such as cookie notices, sign up for our newsletter, install our app, etc. Make a list of things to include.

List the ones that (will be most recognizable × easiest to implement).

I love the rate us, let's make a mockup, please.

OK, this has the right idea. The theme should be a ZenMemes theme that can be selected. It should make it easy to switch away when the user is done. This is also where we can start testing page-specific themes, meaning themes that are the default theme for a particular page.

## Understood Intent

Add a selectable theme, working name Engagify, that buries the site under familiar dark patterns as a joke. The [mockup](https://claude.ai/artifact/PSdJJVjmfHFpLiNqTgQALA) sets the tone: a rate-us prompt that diverts bad ratings to a private box, then nags again after "Remind me later". The ranked list from the conversation supplies the rest: cookie banner, newsletter popup, "Open in app" bar, fake notification prompt, chat bubble, fake sign-up toasts, a countdown, a paywall fade and stacked sticky bars.

Leaving the theme must take one obvious click from any page. The same cycle adds a page theme: a theme that a specific page shows by default. Engagify is the first page theme, for example on the anti-cookie notice thread.

## Problem

Themes today are CSS only and apply site-wide, so a theme cannot show dismissable popups or claim a single page.

Concepts touched: proposed "page theme" (created, every option); [theme navigation icons](../../concepts/theme_navigation_icons.md) (used unchanged). No matching feedback item in `docs/feedback/items/`. [PF-20261104-B08](../../feedback/items/PF-20261104-B08.txt) concerns the anti-cookie notice's wording only.

## Shared by every option

- Engagify is a normal entry in `ThemeRegistry.php`, so the theme menu, cycle button and pre-paint allow-list pick it up.
- A page theme beats the site default and the viewer's saved theme, but only on its own page. Otherwise the joke never reaches anyone who has picked a theme.
- An "Exit" control in the Engagify chrome restores the viewer's own theme. On a page theme, the page remembers that choice in the browser, so a revisit does not trap the viewer again.
- Popups that arrive after a delay break "pages must look final on first paint" on purpose. They appear only while Engagify is active. This exception needs the maintainer's approval.

## Option A — Theme script plus a page theme list

**Estimate: 0.70**
Main driver: a second asset kind per theme and a page theme in the pre-paint script. Uncertainty: medium, mostly in baked thread pages.

A theme may ship `theme-engagify.js` beside its CSS. The page loads the script only while that theme is active and unloads its popups on exit. The operator lists page themes in configuration, such as one thread path mapped to `engagify`. The renderer marks the page with its page theme, and the pre-paint script applies it.

- Pro: real buttons for Exit, "Remind me later" and star ratings, as in the mockup.
- Pro: other themes and pages stay untouched; the script never loads elsewhere.
- Pro: page themes need no record format or schema change.
- Con: a site setting, not a post, decides each page theme.
- Con: baked thread pages need a rebuild after the list changes.

## Option B — Option A with the page theme in the thread record

**Estimate: 1.00**

Moved to `v3/theme_directive` (planned on branch `plan/theme_directive`) in Revision 1; kept here as first written.
Main driver: a new root header through parser, write path, read model and composer. Uncertainty: medium to high.

The theme script is the same as in Option A. A thread's root record carries a header such as `Theme: engagify`, like the event headers. The thread page shows that theme by default.

- Pro: the author chooses the theme, and it travels with the content into exports.
- Con: existing threads cannot gain a header without a new post.
- Con: one theme name in a record ties content to a theme that may later be renamed.
- Con: a read-model column and a composer field, at roughly the size of a whole cycle.

## Option C — CSS-only theme plus a page theme list

**Estimate: 0.35**
Main driver: decorative pseudo-elements in one stylesheet. Uncertainty: low.

Every nag is a pseudo-element drawn by `theme-engagify.css`, with no script. Page themes work as in Option A.

- Pro: follows the theme guide exactly; smallest change.
- Con: nothing can be dismissed or rated, so the bit loses its punchline.
- Con: decorations must not cover real controls, which limits overlays to edges.

## Recommendation

Choose Option A. It delivers the mockup's interaction, keeps the theme guide's rule that other themes stay untouched, and gives page themes a small first form. Option B's record header can follow once page themes prove useful.

First vertical slice: Engagify selectable from the theme menu with the rate-us flow, cookie banner, newsletter popup and Exit. Second slice: the page theme list, with Engagify on one thread. Further nags from the ranked list follow in later stages if time allows.

Open question for Step 2: which page gets Engagify first. The anti-cookie notice thread is the suggested candidate.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Revision 1 — theme directive split out

The maintainer asked to move the page theme set from inside a thread into its own cycle, `v3/theme_directive` (planned on branch `plan/theme_directive`). That cycle compares a `theme:` reply directive with a derived record and with this assessment's Option B header. This cycle keeps Option A: the theme script and the page theme list in configuration. Option C remains the alternative. The recommendation and slices are unchanged.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Revision 2 — which nags this cycle builds

The maintainer agreed to fix the list of nags now, rather than leave it to Step 3. Each nag is its own small piece of `theme-engagify.js`, so later additions stay small.

- Core, required: the rate-us prompt as in the mockup, the cookie banner, the newsletter popup with a guilt-trip decline, the "Open in app" bar, the fake notification prompt, and Exit.
- Stretch, only if Step 3 fits them: fake sign-up toasts, a countdown timer, a chat bubble, a paywall fade, stacked sticky bars and "Summarize with AI" buttons.
- Out of scope: everything else from the long list, as a follow-up.

This replaces the first slice above, which named only the rate-us prompt, cookie banner, newsletter popup and Exit. Option A remains the recommendation.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Revision 3 — theme scripts as a general mechanism

The maintainer weighed a CSS-only alternative. It stores state in checkboxes, radio buttons and `<details>` inside a hidden markup partial on every page. He chose theme scripts instead, because other themes will need them later.

Option A therefore treats a theme script as a general feature, not an Engagify special case. Any entry in `ThemeRegistry.php` may declare a script beside its stylesheet. The page loads it only while that theme is active. The theme development guide documents the contract, including how a script cleans up when the viewer switches themes.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Revision 4 — page-only themes

The maintainer asked for Engagify to stay out of the theme menu, yet still serve as a page's default theme. This replaces the original "can be selected" requirement.

Site-specific themes such as `chouse` and `qdb` work differently. A site that does not list one in `permittedThemes` cannot use it at all. Engagify needs a new state: page-only. A page-only theme never appears in the theme menu or the cycle button, and a viewer cannot save it as their theme. It loads only on a page whose page theme names it.

Exit therefore leaves the page theme on that page and shows the viewer's own theme. The page remembers that choice in the browser, as before. Option A remains the recommendation.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-07T11:11:04Z, `railspike-effort` / `v3/engagify_theme`, intake and Step 1 handoff. Scope v1: Option A as recommended above. Starting revision `f343b6269a3b786e4ebe1ab383096f2109461933`. Reference R1, protocol P2; frozen R1 hashes not re-verified for this checkpoint. Single exposed Claude rater. Before rating, the rater read `ThemeRegistry.php`, the theme part of `TemplateRenderer.php` and `layout.php`, and the theme development guide. Pristine intake is missing. Intake and Step 1 projected total **0.70**. Option estimates 0.70, 1.00 and 0.35 are alternatives and do not add up. No implementation, measured labor or accepted outcome.

Planning saved on branch `plan/engagify_theme` at the session stop hook's request. This commit does not approve Step 1.

2026-11-07T11:45:50Z, Revision 1 checkpoint: Option B moved to `v3/theme_directive` at the maintainer's request. Scope v1 and recommended Option A unchanged; projected total **0.70** reaffirmed. Option estimates 0.70 and 0.35 for A and C unchanged; B's 1.00 retained as history. Same revision, R1/P2 and single exposed rater. No approval implied.

2026-11-07T11:48:38Z, Revision 2 checkpoint: the maintainer fixed the core and stretch nag lists. Scope v2: Option A with five core nags plus Exit; stretch nags are conditional and excluded from the estimate. Projected total **0.70** reaffirmed; the two added core nags are offset by stretch work moving out of the estimate. Same revision, R1/P2 and single exposed Claude rater. No approval implied.

2026-11-07T11:50:41Z, Revision 3 checkpoint: the maintainer chose theme scripts as a general mechanism over CSS-only state. Scope v3: Option A with a reusable theme script contract and guide update. Projected total 0.70 → **0.75**, reason changed scope (general contract, cleanup on theme switch, documentation). Same revision, R1/P2 and single exposed Claude rater. No approval implied.

2026-11-07T11:52:09Z, Revision 4 checkpoint: the maintainer made Engagify page-only, hidden from the theme menu and cycle button. Scope v4: Option A with a page-only theme state. Projected total **0.75** reaffirmed; the new state is small, and Exit no longer has to restore a saved theme. Same revision, R1/P2 and single exposed Claude rater. No approval implied.

Step 1 approved by the maintainer on 2026-11-07 ("approved step 1"), with Revisions 1–4. This approval covers the delayed-nag exception to the first-paint rule.

Revision 4 reversed after Step 2 approval (2026-11-07T11:56:40Z): the maintainer asked to keep Engagify selectable. The reversal is recorded in Step 2's Revised Direction; Revision 4 stays here as history.

## Enhancement Requests

- Page themes declared in a thread's root record. Disposition: Option B, deferred to a follow-up cycle unless chosen now. Revision 1: moved to its own cycle at the maintainer's request, widened to a `theme:` directive; approval pending there; `v3/theme_directive` (planned on branch `plan/theme_directive`).
- Remaining nags from the ranked list (push prompt, chat bubble, toasts, countdown, paywall, sticky bars, AI button). Disposition: in scope as later stages if Step 3 fits them; otherwise a follow-up. Revision 2: the "Open in app" bar and notification prompt are core; the rest are stretch; approved by the maintainer in conversation.
- A CSS-only nag mechanism using hidden form controls (raised as Option D in conversation). Disposition: declined by the maintainer in favor of general theme scripts; Revision 3.
- Hide Engagify from the theme menu and use it only as a page theme. Disposition: in-scope refinement during Step 1; Revision 4.
