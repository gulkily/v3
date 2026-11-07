> **Feature plan:** [Step 1](./theme_directive_step1_solution_assessment.md) · [Step 2](./theme_directive_step2_feature_description.md) · [Step 3](./theme_directive_step3_development_plan.md) · [Step 4](./theme_directive_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/theme_directive`; [host ledger](./effort_estimation_trial.md#cycle-v3theme_directive). Standing P2 enrollment. Split from [`v3/engagify_theme`](./engagify_theme/engagify_theme_step1_solution_assessment.md), its Option B; counted as a distinct cycle with that relationship retained.

Extended estimates: enabled

Cycle estimate: 0.40

## Original Query

break out the theme: directive into its own cycle

Context from the parent cycle's query: "This is also where we can start testing page-specific themes, meaning themes that are the default theme for a particular page."

## Understood Intent

Let a thread choose its own page theme from inside the forum. A line such as `theme: engagify` sets that thread's page theme, in the same spirit as the `title:` and `event:` directives. The Engagify cycle keeps its page theme list in configuration. This cycle adds the directive as a second way to set a page theme, so that choosing one no longer requires an operator to edit settings.

## Problem

After the Engagify cycle, only a site setting can give a thread a page theme, and nobody can set one from the thread itself.

Concepts touched: [page theme](../concepts/page_theme.md) (introduced by `v3/engagify_theme`; extended, every option); [thread title](../concepts/thread_title.md) (precedent; Option B reuses its pattern); [calendar](../concepts/calendar.md) event directive (precedent; Option A reuses its pattern). No matching feedback item in `docs/feedback/items/`.

## Shared by every option

- This cycle depends on the page theme mechanism from `v3/engagify_theme`. It begins after that cycle's page theme stage lands.
- Only the thread's author or an operator can set a thread's theme. From anyone else, the line stays plain text.
- The value must name a theme in `ThemeRegistry.php`. An unknown name is ignored.
- Exit works as it does for any page theme: the viewer's choice to leave is remembered in the browser.
- A directive beats the configuration list for its thread. Precedence is settled in Step 2.

## Option A — Derived on read, like `event:`

**Estimate: 0.40**
Main driver: one directive reader with an authority check and a lookup on the thread page. Uncertainty: low to medium, mostly in baked thread pages.

The thread page finds the latest `theme:` line in its posts, counting only unretracted, verified posts by the thread's author or a current operator. The root post's own line counts. Nothing is stored, so a rebuild cannot disagree with the posts, and retracting the post withdraws the theme.

- Pro: no record family and no schema change.
- Pro: existing threads gain a theme through an ordinary reply.
- Con: authority is judged when the page is read, so a theme set by a former operator stops applying.
- Con: lists and cards do not know a thread's theme; only the thread page does.

## Option B — Derived record, like `title:`

**Estimate: 0.75**
Main driver: a new record family with spec, write path, read-model column and withdrawal. Uncertainty: medium.

When an author with authority posts a `theme:` line, the same commit writes a thread-theme record that names the reply, as the title directive does. The read model stores the effective theme on the thread row.

- Pro: authority is fixed when the line is written, and exports carry the record.
- Pro: any page could read the theme cheaply, though none needs it yet.
- Con: a record spec, schema version bump and rebuild test, for a joke theme.

## Option C — Root header `Theme:`

**Estimate: 0.55**
Main driver: a header through parser, write path, read model and composer. Uncertainty: medium.

The parent cycle's Option B: the thread's root record carries `Theme: engagify`.

- Con: existing threads cannot gain a theme without a new thread.
- Con: the composer gains a field few people will use.

## Recommendation

Choose Option A. It follows the most recent directive precedent, needs no format or schema change, and lets the anti-cookie notice thread gain Engagify with one reply. If themes ever need to show in lists, Option B's record can come later.

Vertical slice: the directive on one thread, with authority, retraction withdrawal and Exit verified. The whole cycle is a single slice.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-07T11:45:50Z, `railspike-effort` / `v3/theme_directive`, intake and Step 1 handoff. Split from `v3/engagify_theme` Option B at the maintainer's request; distinct cycle ID with the parent link retained. Scope v1: Option A as recommended above. Starting point assumed: `v3/engagify_theme` page themes delivered; planning revision `d379f5600ab55c99e8b8055d6f0520eda0504595`. Reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater, who also wrote the parent's estimates. Before rating, the rater read `TitleDirective.php`, `EventStatus.php`, the thread-subject record spec and the thread title and calendar concepts. Pristine intake missing. Intake and Step 1 projected total **0.40**. Option estimates 0.40, 0.75 and 0.55 are alternatives and do not add up. No implementation, measured labor or accepted outcome.

2026-11-07T14:41:57Z, branch move: the maintainer asked for this cycle to stand on its own. Planning moved from `plan/engagify_theme` to `plan/theme_directive`, branched from `feat/engagify_theme`, whose page themes are now built (Stage 3). Scope v1, Option A and projected total **0.40** unchanged; no re-rating. Step 1 still awaits approval.

## Enhancement Requests

None yet.
