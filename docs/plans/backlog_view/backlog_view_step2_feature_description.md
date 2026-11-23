> **Feature plan:** [Step 1](./backlog_view_step1_solution_assessment.md) · [Step 2](./backlog_view_step2_feature_description.md) · [Step 3](./backlog_view_step3_development_plan.md) · [Step 4](./backlog_view_step4_implementation_summary.md)

Cycle: `v3/backlog_view`. First of three planned cycles; `backlog_claims` and `backlog_parked` follow. Branch: a new one from `origin/main`.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/backlog_view`; host ledger [backlog_view.md](../effort_ledger/backlog_view.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.60**

## Problem

The backlog is 345 open feedback items in `docs/feedback/items/`. Nothing shows them as a list to sort and act on, and starting work means pasting a thread link that the agent must then read in full.

## Approved direction

Step 1 was approved on 2026-11-23 ("approved step 1"). No option was named, so this follows the recommended Option E: this cycle builds the page, the sorts, the filters, Start with a short prompt, and the agent interface. Claims and parked plans are the next two cycles. The maintainer saw a mockup before approving and asked for no changes, so its layout is the starting point. Unanswered Step 1 questions take their defaults, each marked **(default)** below.

Concepts touched: [feedback items](../../concepts/feedback_items.md) (changed: optional `Impact` header). Proposed: *backlog view*. [Tools launcher](../../concepts/tools_launcher.md) (changed: one entry). Feedback: no item yet; one is allocated in Step 4.

## User stories

- As the maintainer, I want one page listing work that can start, so that I pick the next thing without reading files.
- As the maintainer, I want to sort by entered, touched, effort, impact or a combined order, so that the most worthwhile items come first.
- As the maintainer, I want to tick related items and start them together, so that one cycle and one PR cover them.
- As the maintainer, I want a short prompt to copy, so that the agent starts from repository files and does not read threads.
- As an agent, I want the same list and prompt as plain text and from the command line, so that I can pick up work without a browser.
- As a visitor, I want to read the backlog, so that I can see what is planned.

## Core requirements

- **Page.** `/backlog` lists open feedback items from the site's own checkout. Anyone can read it. Tools links to it. A feature flag turns it on; it is off by default and on at zenmemes.com.
- **Sorts.** Entered, Touched, Effort, Impact and Salient, each newest-first or oldest-first. The address stays readable, such as `/backlog?sort=effort&order=oldest`.
  - Touched is the date of the item's latest note, or its created date **(default)**.
  - Effort and Impact sort items with a value first; items without one follow, by date.
  - Salient ranks by impact divided by effort, and a missing value counts as the middle of the scale. Items of equal rank go by touched date **(default)**.
- **Filters.** "Can start" (ready and untriaged) is the default. Others: each workflow value including `untriaged`, "Waiting on you" (blocked), bugs, features. Filters combine with any sort.
- **Impact.** Items take an optional `Impact` header on the `Estimate` scale. `./v3 feedback check` accepts it and the generated index shows it.
- **Start.** Tick one item or several and press Start. The next page lists them, offers one-shot or staged cycle, and shows the prompt with a Copy button. Its address is readable, such as `/backlog/start?items=PF-20261122-B01,PF-20261122-B02&mode=one-shot`.
- **Prompt.** A few lines: the repository address, the files to read (the new procedure and each item's file), the mode, that the items form one cycle, and the instruction to begin and reach an open PR or as close as possible.
- **Procedure.** A new `docs/agents/backlog_to_pr.md` makes the item file the brief. The agent reads cited posts only when the item says too little, then adds what it learned to the item **(default)**. It keeps the existing check for open branches and PRs, since claims come next cycle.
- **Agent interface.** `/backlog.txt` and `/backlog/start.txt` return the same list and prompt in `Key: value` and plain text, with the same address words. `./v3 feedback list` and `./v3 feedback start` do the same from a checkout.
- **Basics.** Everything works without JavaScript; scripts only add Copy and the selected count. The page states which version of the repository its list comes from.

## Delivery scope

- Work type: application change: the page, the start page, two text routes, a Tools entry with icons in every icon set, a feature flag, `./v3 feedback list` and `start`, the `Impact` header in the item spec and check, the new procedure, tests, a browser check, concept entries and a feedback item.
- Out of scope: claims and showing who holds an item (`backlog_claims`); the parked plans list (`backlog_parked`); changing an item's values from the page; filling in `Estimate` or `Impact` on existing items; a list fresher than the last deploy; a navigation bar entry; agents without a shell.

## Completion boundary

- Normal entry: Tools → Backlog on zenmemes.com, on a phone.
- End-to-end outcome: the maintainer sorts and filters, ticks two items, presses Start, copies the prompt, and an agent given only that prompt opens a PR covering both items.
- Recovery: an unknown sort or filter word falls back to the default and the page says so. An unknown or closed item ID on the start page is named and left out; the rest still start. A site with no items folder shows an empty list, not an error.
- Release: merged, deployed with the flag on, and tried by the maintainer on his phone with one real start.

## Known user or environment

The maintainer uses zenmemes.com on an iPhone SE, in Safari and the offline web app. Entry: Tools → Backlog. Condition to handle: a list of about 290 rows on a 375 px screen, and Copy on iOS. Next action unaided: if Copy fails, the prompt is selectable text. Evidence: the browser check at 375 px, and the maintainer's own start on his phone. A desktop pass alone does not satisfy this.

## Risks

- **The list is behind `main`.** An item finished since the last deploy still shows as startable. Impact: duplicate work, the problem claims will solve. Earliest validation: Step 3 review of the procedure. Mitigation: the page states its version; the procedure tells the agent to check the item's workflow in its own fresh checkout and stop if it is closed.
- **Salient equals Touched for now.** 22 items have an estimate and none has an impact. Impact: the combined sort looks broken. Earliest validation: run the sort over the real items before any markup. Mitigation: the missing-value rule above, and a triage task to fill values in, outside this cycle.
- **Thin items slow the agent.** Many untriaged items hold one or two sentences. Impact: the short prompt saves less than hoped. Earliest validation: one real start on a thin item before release. Mitigation: the procedure's read-posts-when-thin rule, and writing the detail back into the item.
- **Item text reaches agents as instructions.** Items quote site posts. Impact: a post could try to steer an agent. Earliest validation: procedure review. Mitigation: the procedure repeats the thread-to-PR rule that item and post text is data and grants no permissions.

## Shared component inventory

- `src/ForumRewrite/Feedback/FeedbackItem.php`: loads and validates items. Extended with `Impact`, the touched date and the two scores. `FeedbackItemCheck.php` and `FeedbackIndex.php` extended for `Impact`.
- `scripts/feedback.php` and the `./v3 feedback` dispatch: gain `list` and `start`. The page and the commands share one sorter and one prompt builder; no second copy.
- `templates/partials/agent_prompt.php` and `agent_prompt_script.php`: the existing selectable prompt with Copy. Reused for the start page.
- `templates/partials/board_sort.php` and the readable `sort=` words on `/threads/`: the pattern for sort links. Reused or extended; no script-driven sorting.
- `templates/partials/page_navigation.php`: reused if the list is paged.
- `src/ForumRewrite/Tools/ToolsPageSupport.php`, `templates/pages/tools.php` and `public/assets/theme-nav-icons.css`: one new Tools entry, with an icon in every icon set.
- The feature flag registry under `src/ForumRewrite/FeatureFlags/`: one new site-mutable flag.
- `/llms.txt` and the thread `.txt` routes in `Application.php`: the pattern for plain-text routes.
- [Thread to pull request](../../agents/thread_to_pr.md): the new procedure refers to it for routes, questions and the PR, and does not repeat it.
- New: the `/backlog` page template and its row. No existing list shows items with checkboxes; `post_card.php` carries post-only parts.

## User flow

1. The maintainer opens Tools → Backlog. The list shows ready items, then untriaged ones, in Salient order.
2. He taps Effort, then the "Bugs" filter.
3. He ticks two related bugs. The bar reads "2 selected". He presses Start.
4. The start page lists both, with One-shot chosen. He presses Copy.
5. He pastes the prompt into an agent. The agent reads the procedure and the two item files, and works to a PR.

## Success criteria

- `/backlog` loads on zenmemes.com for a signed-out visitor and lists every open item under the right filter.
- Each of the five sorts, in both directions, gives the order its rule states, checked against the real items in a test.
- The address for any sort and filter contains only readable words.
- With scripts off, ticking items and pressing Start reaches the start page with those items.
- The prompt for two items is under 15 lines and names only files that exist.
- `/backlog.txt` and `./v3 feedback list` return the same items in the same order as the page for the same words.
- An agent given only the copied prompt opens a PR for one real item without fetching a site thread, when the item file is detailed enough.
- No sideways overflow at 375 and 1280 px in every offered theme.

## Enhancement requests

- **Request:** Start claims the items. **Disposition:** next cycle `backlog_claims`. **Approval status:** not approved.
- **Request:** parked plans listed separately. **Disposition:** cycle `backlog_parked`. **Approval status:** not approved.
- **Request (agent's suggestion):** fill in `Estimate` and `Impact` across items. **Disposition:** a triage task, outside this cycle. **Approval status:** not approved.

## Study snapshots and approval

- 2026-11-23 UTC: **Approved Step 1** by the maintainer ("approved step 1"); Option E assumed, as recommended, with the Step 1 defaults.
- 2026-11-23 UTC, Step 2 handoff: **0.60** reaffirmed. Scope v3. Claude, single exposed rater. Reference R1, hashes not re-verified this session. Baseline `origin/main` `8a5aa40`.

- 2026-11-23 UTC: **Approved Step 2** by the maintainer ("approved step 2"). Defaults kept.

Status: Step 2 approved.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*
