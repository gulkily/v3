> **Feature plan:** [Step 1](./hn_theme_step1_solution_assessment.md) · [Step 2](./hn_theme_step2_feature_description.md) · [Step 3](./hn_theme_step3_development_plan.md) · [Step 4](./hn_theme_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/hn_theme`; host ledger [hn_theme.md](../effort_ledger/hn_theme.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.65**

## Problem

Every current theme looks unlike Hacker News. A Hacker News reader on a ported thread has to learn a new page before reading the discussion. They also lose the habits they use to move through a long thread.

## Approved direction

Step 1 was approved on 2026-11-19 with Option C and the name **Ochre**. The maintainer will apply Ochre to ported threads by hand for now. The default on ported threads is deferred (see Enhancement requests).

Concepts touched: [page theme](../../concepts/page_theme.md) (used unchanged: a `theme: ochre` reply from an operator); [theme script](../../concepts/theme_script.md) (used: Ochre gets its own script); [ported thread](../../concepts/ported_thread.md) (read only). Proposed addition: an `ochre_theme.md` concept entry. Feedback: no matching item in `docs/feedback/items/`.

## User stories

- As a Hacker News reader on a ported thread, I want a page that looks familiar, so that I can start reading at once.
- As that reader, I want to fold away a subtree, so that I can skip a branch I'm done with.
- As that reader, I want parent and next links on each comment, so that I can move through a deep thread without scrolling.
- As the maintainer, I want to set Ochre on a thread with one reply, so that I choose which threads use it.
- As a reader with poor vision, I want every text colour to pass WCAG AA, so that I can read grey metadata.

## Core requirements

- Ochre is a light theme in the theme menu: a narrow beige page, one orange top bar, small Verdana text, grey metadata lines and thin indentation. Its dark counterpart is Dark. It has no Hacker News logo, wordmark or name.
- Body text, metadata, links and the top bar's text meet 4.5:1 contrast. The bar's text is dark on orange, because white on that orange fails.
- On a thread, each comment has a `[–]` toggle. It hides the comment's body and all its replies, shows how many it hid, and turns into `[+N]`. Pressing it again shows them.
- Each reply's metadata line links to its parent and to the next comment at its depth. The board numbers its rows.
- Ochre adds no shared markup. Its script builds the toggles and links from the existing post cards. Picking another theme removes all of them without a reload.

## Delivery scope

- Work type: application change: a stylesheet, a theme script, a `ThemeRegistry` entry, a concept entry, the theme guide and tests.
- Out of scope: the default on ported threads, remembering collapsed subtrees across reloads, collapse in other themes, and a "context" link. A thread page already shows each comment in context.

## Completion boundary

- Normal entry: a reader picks Ochre from the theme menu, or an operator replies `theme: ochre` on a thread.
- End-to-end outcome: the board, Recent, a thread, a ported thread and Tools read correctly in Ochre at 380px and on desktop. Collapse and the parent and next links work on a ported thread with deep nesting.
- Recovery: picking another theme, or `theme: none`, restores the usual look with no leftover toggles. With JavaScript off, Ochre is the look alone and every comment stays visible.
- Release: merged to main and tried on production by the maintainer.

## Risks

- **Script and page-theme exit disagree.** A reader leaving a page-themed thread could keep stray toggles. Validate early with the existing theme-script VM test, extended for Ochre. Mitigate by following Bubblegum's start and stop pattern.
- **Collapse misreads the tree.** Post cards are a flat list with `data-reply-depth`, and continuation posts from the same author merge. Validate on a synthetic ported thread at depth 8 or more before styling. Mitigate by treating a subtree as the run of following cards that are deeper.
- **Compact layout breaks shared controls.** Composers, the Activity drawer and the board controls must stay reachable. Validate with the guide's "what must survive" list at 380px.

## Shared component inventory

- `ThemeRegistry.php`: add one entry with `'script' => true`. No new mechanism.
- `theme_toggle.js` and `forumThemeScripts`: load and stop Ochre's script. Reused unchanged.
- `templates/partials/post_card.php` (`data-post-id`, `data-reply-depth`, `data-author`, the anchor ID) and `thread_row.php` (`thread-row`): read as they are. Ochre numbers rows with CSS counters.
- `ThreadTheme::forThread` and `ThemeDirective`: reused for the hand-applied `theme: ochre`.
- Bubblegum's window controls (`theme-bubblegum.js`): not reused. They collapse a single card, depend on a flag and keep their own storage. A subtree toggle is a different action, so Ochre keeps its own small script.

## User flow

1. An operator replies `theme: ochre` on a ported thread.
2. A reader opens the thread and sees Ochre before first paint.
3. They press `[–]` on a long branch, and it folds to one line reading `[+12]`.
4. They follow "parent" from a deep reply, then "next" to its sibling.
5. They pick Gloss from the menu. The toggles and links disappear, and a revisit stays on Gloss.

## Success criteria

- Contrast checks pass for every Ochre text colour, including the top bar.
- A browser test collapses and expands a subtree at depth 8 or more, and follows parent and next.
- The theme-script VM test shows Ochre's stop leaves no added element.
- The guide's checklist passes in Ochre at 380px and on desktop on the five page types, and other themes are unchanged.

## Enhancement requests

- **Request:** show Ochre by default on ported threads (Step 1's Option B rule). **Disposition:** separate work, deferred by the maintainer, who will apply the theme by hand for now. **Approval status:** not approved. **Follow-up:** a later cycle, which must also decide every site versus opt-in and make `theme: none` cancel the default.

## Study snapshots and approval

- 2026-11-19 UTC, Step 2 handoff: **0.65** projected total. Option C's 0.80 minus the deferred default rule, and less risk now that no shared markup changes. Claude, single exposed rater. Scope v2 (Option C without the ported default). Reference R1, hashes not re-verified this session. Baseline `origin/main` `a1cefffa` (PR #342 merged). Awaiting `Approved Step 2`.
- 2026-11-19 UTC: **Approved Step 2** by the maintainer, after reviewing an Ochre mockup kept outside the repository.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*
