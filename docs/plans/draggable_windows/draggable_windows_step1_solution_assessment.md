> **Feature plan:** [Step 1](./draggable_windows_step1_solution_assessment.md) · [Step 2](./draggable_windows_step2_feature_description.md) · [Step 3](./draggable_windows_step3_development_plan.md) · [Step 4](./draggable_windows_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./draggable_windows_debug_log.md)). Source: the `.railspike_debug_on` marker at the repository root.

Effort study: `railspike-effort` / `v3/draggable_windows`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 1 approved by the maintainer 2026-11-17 UTC (Option A, as recommended).

**Cycle estimate: 0.60**

# Step 1: Solution Assessment — Draggable windows

## Original Query

Please write step 1.

## Understood Intent

The request continues a chat from 2026-11-15 about the Bubblegum theme, summarized on ZenMemes in the [#draggable thread](https://zenmemes.com/threads/thread-20261116080850-fa850374). The maintainer liked "windows that behave like windows" best. Windows pop open, the title-bar buttons collapse a post, and a desktop mode lets you drag windows around. The chat also asked what Pollyanna's `dragging.js` offers. Its behavior is worth borrowing: a collapse that survives a reload, active and inactive title bars, a Float / Cascade / Arrange / Reset panel, a window list, and new windows that open beside the one you clicked. Its code is not, because it has no keyboard or touch support.

## Problem

Bubblegum's cards look like desktop windows, but nothing about them acts like a window: the title bar and its three buttons are a picture that ignores clicks.

Today the title bar is `.card::before` in `theme-bubblegum.css`, with `pointer-events: none` and the buttons drawn as one SVG. Working buttons therefore need real elements. Pages are baked to static files and must look final on first paint, so a remembered collapse must be applied before paint, as `layout.php` does for reading margins. Pages must still work with JavaScript off.

Concepts touched: [theme script](../../concepts/theme_script.md), used in A and C, not in B. [Post presentation](../../concepts/post_presentation.md) and [visitor intro](../../concepts/visitor_intro.md), used unchanged in every option. A new concept, "window controls", is added in every option.

No matching item exists in `docs/feedback/items/`. The nearest, `PF-20261104-B10` ("Cannot collapse event section"), is a different control.

## Option A — A Bubblegum theme script with Float mode

**Estimate: 0.60**

Bubblegum gets a theme script, as Engagify has. When Bubblegum is active, the script puts real buttons over each post's drawn title bar. Collapse hides the post body and keeps the title bar, and the browser remembers it per post. Clicking or tabbing into a window makes it active, and the others fade. A small control turns on Float mode on desktop, where title bars can be dragged and Reset puts every window back. Windows pop open with a short animation, which "reduce motion" turns off. Picking another theme removes all of it without a reload.

- Reuses the theme script registry in `theme_toggle.js`, which already starts and stops a theme's extras.
- Everything is opt-in twice: the reader picks Bubblegum, then turns on Float mode. The normal board still scrolls as it does now.
- Collapse works on phones, so it can be part of an iPhone SE demo. Dragging stays desktop-only.
- A new flag can turn it all off if it breaks, like `FORUM_READING_MARGINS_ENABLED`.
- Only Bubblegum gets it. Word97, Chicago and Midnight also draw windows but would wait for a later cycle.
- Estimate details: driver: dragging with pointer events without breaking text selection, links and scrolling; uncertainty: applying a remembered collapse before paint from a theme script that loads later.

## Option B — A shared window layer for every window theme

**Estimate: 0.95**

The server renders real title-bar buttons in each post card's markup, hidden by default. Each window theme (Bubblegum, Word97, Chicago, Midnight) styles them and turns them on. A site-wide script handles collapse, focus and Float mode for whichever window theme is active.

- One behavior for all four retro themes, and the buttons exist without JavaScript.
- Changes `post_card.php`, `thread_root_card.php` and `quote_card.php`, which every theme renders.
- Each theme then needs its own button styling and screenshot checks on desktop and phone, which roughly doubles the verification.
- The buttons do nothing with JavaScript off, so they would sit in the markup unused for those readers.
- Close to the 1.0 ceiling. Probably needs a split anyway.
- Estimate details: driver: markup and styling in four themes; uncertainty: how Word97 and Chicago title bars fit real buttons.

## Option C — Option A without dragging, Float mode as a follow-up

**Estimate: 0.40**

The same theme script, but this cycle ships only the pop, working collapse buttons and active windows. Float mode, dragging and Reset become their own small cycle once the maintainer has tried the rest.

- The safest slice. The riskiest part waits until the first part has been seen in use.
- Leaves out the part the #draggable thread is named for.
- Two cycles in total cost a little more than one, because of shared setup and review.
- Estimate details: driver: before-paint collapse and keyboard support; uncertainty: low.

## Recommendation

Option A. It ships the whole idea as one releasable slice: pick Bubblegum, collapse a post, reload and see it still collapsed, turn on Float mode, drag a window, press Reset. Putting dragging behind Float mode removes most of the risk that made Option C attractive in the earlier chat. Option C stays the fallback if Step 2 finds that before-paint collapse needs more work than expected. Option B can come later, reusing A's script, once a second window theme wants it.

Questions for Step 2:
- Which windows get buttons: posts only, or also the visitor intro and other cards on Tools and profile pages?
- Does Float mode remember window positions across reloads, or only collapse?
- Is a window list or taskbar for collapsed posts part of this cycle, or the next one? Cascade and Arrange as well?
- Should a collapsed post on a thread page show its first line in the title bar, as a hint of what it holds?

Enhancement requests: none in this request.

## Study snapshots

- Intake (exposed: estimated in the 2026-11-15 chat before reading the code, at 0.70 then 0.60 after reading Pollyanna): 0.60 projected total for Option A. Baseline `c98b5221` (`origin/main`), scope v1, R1, P2; Claude single exposed rater, 2026-11-16 UTC.
- Step 1 handoff: 0.60 reaffirmed after reading `theme-bubblegum.css`, the theme script registry and the reading margins precedent. Option estimates A 0.60, B 0.95, C 0.40, not summed. R1 hashes not re-verified in this session.
- Pending ledger update: this cycle's row in `effort_estimation_trial.md` is not written yet; it belongs in the planning commit after Step 3 approval.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*
