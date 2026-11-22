> **Feature plan:** [Step 1](./hn_theme_step1_solution_assessment.md) · [Step 2](./hn_theme_step2_feature_description.md) · [Step 3](./hn_theme_step3_development_plan.md) · [Step 4](./hn_theme_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/hn_theme`; host ledger [hn_theme.md](../effort_ledger/hn_theme.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.45

## Original Query

I want to add a theme that is more accessible to HN users.

## Understood Intent

A reader who arrives from Hacker News, often through a ported thread, should find ZenMemes familiar at a glance: a compact page, mostly text, with the reply tree read the way they already read Hacker News. "Accessible" is read here as familiar to Hacker News readers. The theme should also pass contrast checks that Hacker News's own grey text does not.

## Problem

Every current theme looks unlike Hacker News, so a Hacker News reader landing on a ported thread has to learn a new page before they can read the discussion. Nothing lets a ported thread show itself in a look its readers already know.

Concepts touched: [page theme](../../concepts/page_theme.md) (used unchanged, or extended in Option B); [ported thread](../../concepts/ported_thread.md) (extended in Options B and C, which depend on PR #342); [theme script](../../concepts/theme_script.md) (used in Option C only). Feedback: no matching item found in `docs/feedback/items/`.

## Shared by every option

- A new CSS-only theme in `ThemeRegistry`, light mode, text only, no navigation icons. It has a narrow beige page, one orange top bar holding the site name and navigation, Verdana at a small size, grey metadata lines, and thin indentation for nesting. Its dark counterpart is Dark.
- No Hacker News logo, wordmark or name in the chrome. The look is evoked, not copied, so it can't be mistaken for Hacker News. The name is the maintainer's choice; "Orange" is a placeholder.
- Contrast meets WCAG AA (4.5:1 for body and metadata text). That means a darker grey than Hacker News's `#828282` on its beige, which comes to about 3.5:1.
- The theme follows the theme guide's scoped-CSS rules and its "what must survive" list. It is checked at 380px and desktop width, on the board, Recent, a thread, a ported thread and Tools.

## Option A — A selectable theme only

**Estimate: 0.30**
Main driver: one stylesheet across the main page types, plus the contrast checks. Uncertainty: low.

The theme appears in the theme menu beside the others. A reader who wants it picks it.

- Pro: the smallest change, with no rules about who sees it.
- Con: the people it is for, Hacker News readers arriving once, never open the theme menu, so most of them never see it.

## Option B — Ported threads show it by default

**Estimate: 0.45**
Main driver: Option A plus one rule in `ThreadTheme::forThread`: a thread whose root has `ported_from` uses the new theme unless the thread sets its own. Uncertainty: low.

A ported thread opens in the new theme for every reader, the way a page theme already works. The reader's saved theme is untouched. Picking another theme on that page leaves it, and the browser remembers that exit for the page. A `theme:` reply from the thread's author or an operator still wins. A site-profile setting or a flag can turn the default off.

- Pro: reaches exactly the readers it is for, at the moment they arrive.
- Pro: reuses the page-theme machinery, including the remembered exit, with one new rule.
- Con: it depends on PR #342 being merged first.
- Con: a member who prefers their own theme sees this one on every ported thread until they leave it once per thread.

## Option C — Option B plus Hacker News reading habits

**Estimate: 0.80**
Main driver: a theme script for collapsing subtrees, and denser board and thread markup that every other theme must still render. Uncertainty: medium.

Besides the look, the theme adds the habits: a `[–]` toggle on each comment that collapses its subtree, a numbered compact board, and links on the metadata line for parent, next and context.

- Pro: closest to how Hacker News readers actually move through a thread.
- Con: the habits need markup and script, not only CSS. The guide asks theme scripts to undo themselves cleanly, and every other theme has to keep rendering the shared markup.
- Con: collapsing would be useful in every theme, so it would be better as its own cycle open to every theme than tied to this one.

## Recommendation

Option B. The theme matters most to someone arriving on a ported thread, and B is the only option that puts it in front of them without asking them to find a menu. It reuses the page-theme rules already built for Engagify and `theme:`. Collapsing subtrees from Option C is worth its own later cycle for every theme.

Vertical slice: on iPhone Safari, open a ported thread while signed out. It shows the new theme before first paint, nested, with readable contrast. Choosing Gloss from the menu leaves it, and a revisit stays on Gloss. A thread that is not ported shows the reader's usual theme.

## Open questions for the maintainer

- The theme's name ("Orange" is a placeholder).
- For Option B, should ported threads use it on every site with porting on, or only where a site profile or a flag opts in?

## Enhancement requests

None recorded.

## Study snapshots and approval

- 2026-11-19 UTC, intake after code inspection (`ThemeRegistry`, `ThreadTheme::forThread`, `page_theme.md`, `theme_development_guide.md`, `theme_navigation_icons.md`): 0.45 projected total for the recommended scope. No pristine intake. Claude, single exposed rater; R1 hashes not re-verified this session; baseline `origin/main` `7d9b4b4d`.
- Step 1 handoff: 0.45 for Option B (alternatives A 0.30, C 0.80; not summed). No implementation. Awaiting `Approved Step 1`.
- 2026-11-19 UTC: **Approved Step 1** by the maintainer with Option C and the name Ochre. The default on ported threads is deferred; the maintainer applies the theme by hand for now. Recorded in [Step 2](./hn_theme_step2_feature_description.md#enhancement-requests).

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*
