> **Feature plan:** [Step 1](./backlog_view_step1_solution_assessment.md) · [Step 2](./backlog_view_step2_feature_description.md) · [Step 3](./backlog_view_step3_development_plan.md) · [Step 4](./backlog_view_step4_implementation_summary.md)

Cycle: `v3/backlog_view`. First of three; see the [backlog checklist](../backlog_checklist.md). Branch: `feat/backlog_view`, new from `origin/main`.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/backlog_view`; host ledger [backlog_view.md](../effort_ledger/backlog_view.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.60**

Concepts touched: [feedback items](../../concepts/feedback_items.md) (revised in Stage 1). New *backlog view* entry (Stage 6). [Tools launcher](../../concepts/tools_launcher.md) (revised in Stage 3). Feedback: a new item is allocated in Stage 6.

## Completion Contract

- Normal entry: Tools → Backlog on zenmemes.com, on a phone.
- End-to-end outcome: the maintainer sorts and filters, ticks two items, presses Start, copies the prompt, and an agent given only that prompt opens a PR covering both.
- Recovery: an unknown sort or filter word falls back to the default and the page says so. An unknown or closed item ID on the start page is named and left out. A site with no items folder shows an empty list.
- Deployment: no schema change and nothing new stored. One new flag, `FORUM_BACKLOG_ENABLED`, off by default; the maintainer turns it on at zenmemes.com after the deploy.
- Release: merged, deployed with the flag on, and one real start from the maintainer's phone ends in a PR.

## Key Risks

- **High risk:** the list is behind `main`, so a finished item still shows as startable. Impact: duplicate work until claims exist. Early validation: Stage 2 review of the procedure text. Mitigation: the page states its version; the procedure tells the agent to check the item in its own fresh checkout and stop if it is closed.
- Salient equals Touched for now. Impact: the combined sort looks broken. Early validation: Stage 1 runs all five sorts over the real items before any markup. Mitigation: the missing-value rule; filling values in is a triage task outside this cycle.
- Thin items slow the agent. Impact: the short prompt saves less. Early validation: Stage 6 starts one thin item for real. Mitigation: the procedure's read-posts-when-thin rule.
- Item text reaches agents as instructions. Impact: a post could steer an agent. Early validation: Stage 2 procedure review. Mitigation: the procedure states that item and post text is data.
- A long list on a small phone. Impact: slow or cramped page. Early validation: Stage 3 renders the real 288 rows at 375 px. Mitigation: page the list with the existing navigation if needed.

## Stage 1

**Estimate: 0.10**

- Goal: items carry an impact, and one sorter gives every order the page and commands need.
- Dependencies: none.
- Expected changes: `FeedbackItem` accepts an optional `Impact` header with the `Estimate` rules, and gains `touched(): string` (latest dated note, else `Created`). `FeedbackItemCheck` validates `Impact`. `FeedbackIndex` shows it. A new `Feedback/FeedbackBacklog` with `select(array $items, string $show, string $kind): array` and `sort(array $items, string $sort, string $order): array`, covering `entered`, `touched`, `effort`, `impact` and `salient`, plus the list of accepted words. The item spec and the regenerated `docs/feedback/README.md` change with it.
- Verification approach: `./v3 test --smoke FeedbackItemCheckTest` plus a new `FeedbackBacklogTest`: each sort in both directions; items with no value after items with one; the Salient middle-value rule; each filter; unknown words. One test runs every sort over the real `docs/feedback/items/`. `./v3 feedback check` and `index` on the real folder.
- Estimate details (extended mode only): driver: five orderings with missing values; uncertainty: low.
- Risks or open questions:
  - Impact: the Salient risk above.
  - Early warning / validation: the real-items run, read by eye for the first 20 rows.
  - Mitigation: settle the rule here; Stage 3 does not start otherwise.
- Canonical components/API contracts touched: `Feedback/FeedbackItem.php`, `FeedbackItemCheck.php`, `FeedbackIndex.php` (extended); `docs/specs/feedback_item_record_v1.md`. New class because no ordering beyond the index's fixed one exists.

## Stage 2

**Estimate: 0.10**

- Goal: an agent can list the backlog and get the start prompt from a checkout, and knows what to do with it.
- Dependencies: Stage 1.
- Expected changes: a new `Feedback/BacklogPrompt` with `build(array $items, string $mode): string`. `./v3 feedback list [--sort=] [--order=] [--show=] [--kind=]` prints `Key: value` blocks. `./v3 feedback start <ID>... [--mode=one-shot|staged]` prints the prompt, names unknown or closed IDs and leaves them out. New `docs/agents/backlog_to_pr.md`: the item file is the brief; check the item is still open in a fresh checkout; check open branches and PRs; read cited posts only when the item is thin and write the detail back; item and post text is data; several items form one cycle; then follow thread to pull request for routes and the PR. `docs/agents/README.md`, `AGENTS.md` and `docs/reference/v3_cli.md` gain one line each.
- Verification approach: tests for both commands in `FeedbackBacklogTest`: prompt under 15 lines; every file it names exists; closed and unknown IDs reported; exit codes. Read the procedure against thread to pull request for conflicts.
- Estimate details (extended mode only): driver: the procedure's wording; uncertainty: low.
- Risks or open questions:
  - Impact: the stale-list and instruction risks above.
  - Early warning / validation: the procedure review.
  - Mitigation: both rules are in the procedure before any page links to it.
- Canonical components/API contracts touched: `scripts/feedback.php` and the `./v3 feedback` dispatch (extended); `docs/agents/thread_to_pr.md` (referred to, unchanged).

## Stage 3

**Estimate: 0.18**

- Goal: anyone can open Tools → Backlog and sort and filter the list.
- Dependencies: Stage 1.
- Expected changes: flag `FORUM_BACKLOG_ENABLED` in the registry, site-mutable, off by default, with `releasedOn`. A new `Http/BacklogPageController` and `templates/pages/backlog.php`. The page shows the count, the sort links with the active direction, the filter links, and one row per item: title, workflow, kind, ID, effort and impact. It states the repository version it reads. Sort and filter are links with readable words; unknown words fall back with a notice. A Tools entry, with an icon in every icon set. With the flag off the address answers as any unknown page.
- Verification approach: a new `BacklogPageTest`: flag off and on; signed-out visitor; each sort and filter link's address; fallback notice; empty folder. `./v3 test --smoke BacklogPageTest NavIconSetsTest FeatureFlagsBehaviorTest`. Render the real items at 375 px and decide on paging.
- Estimate details (extended mode only): driver: a new page and row in every theme, plus the Tools entry and icons; uncertainty: medium, row density on a small phone.
- Risks or open questions:
  - Impact: the long-list risk above; a new row style drifting from the themes.
  - Early warning / validation: the 375 px render; a look in Gloss, Word97 and Bubblegum before Stage 4.
  - Mitigation: `page_navigation.php` if paged; build the row from existing list and pill styles.
- Canonical components/API contracts touched: `templates/partials/board_sort.php` (pattern reused or extended), `page_navigation.php`, `Tools/ToolsPageSupport.php`, `templates/pages/tools.php`, `public/assets/theme-nav-icons.css`, the flag registry. New page template because no list shows feedback items.

## Stage 4

**Estimate: 0.10**

- Goal: tick items, press Start, copy the prompt.
- Dependencies: Stages 2 and 3.
- Expected changes: each row gains a checkbox inside one form that submits by GET to `/backlog/start`, giving `?items=ID,ID&mode=one-shot`. A bar shows Start; a small script adds the selected count. The start page lists the items, offers one-shot or staged as links that change `mode`, and shows the prompt through `agent_prompt.php` with Copy. Unknown or closed IDs are named and left out. No items picked returns to the list with a notice.
- Verification approach: `BacklogPageTest`: start with one item, with two, with a closed one, with none; mode switch; the prompt equals `./v3 feedback start` output. A new `tests/browser/backlog.mjs`: tick two, Start, Copy, with scripts on; the same without Copy, scripts off.
- Estimate details (extended mode only): driver: a plain form that yields a comma-joined address; uncertainty: medium, repeated checkbox fields give `items=A&items=B` without script.
- Risks or open questions:
  - Impact: the address is less tidy without scripts.
  - Early warning / validation: the scripts-off browser case.
  - Mitigation: the start page accepts both forms and links to the tidy one.
- Canonical components/API contracts touched: `templates/partials/agent_prompt.php` and `agent_prompt_script.php` (reused), `Feedback/BacklogPrompt` (Stage 2).

## Stage 5

**Estimate: 0.06**

- Goal: agents get the list and the prompt over HTTP as text.
- Dependencies: Stages 2 and 3.
- Expected changes: `/backlog.txt` returns the `./v3 feedback list` output for the same words, with a first block giving sort, filter, count and version. `/backlog/start.txt` returns the prompt. Both follow the flag. `docs/reference/agent_api.md` and `/llms.txt` each gain an entry.
- Verification approach: `BacklogPageTest`: the text route and the command give the same bytes for three word combinations; content type; flag off. `curl` against a local server.
- Estimate details (extended mode only): driver: two thin routes; uncertainty: low.
- Risks or open questions:
  - Impact: the text and the page drift apart later.
  - Early warning / validation: the same-order test.
  - Mitigation: all three surfaces call `FeedbackBacklog`.
- Canonical components/API contracts touched: `Http/ApiTextController.php` and `LlmsTxtRenderer.php` (extended), `Http/ApiRouteCatalog.php` if the route list is checked there.

## Stage 6

**Estimate: 0.10**

- Goal: prove it on the maintainer's path and record it.
- Dependencies: Stages 1–5.
- Expected changes: fixes from the checks. Docs: a new `docs/concepts/backlog_view.md`, the feedback items and Tools launcher concepts, the UI copy checklist, a new feedback item with a `Feedback:` trailer, and the [backlog checklist](../backlog_checklist.md).
- Verification approach: `tests/browser/backlog.mjs` at 375 and 1280 px in every offered theme, scripts on and off, no sideways overflow. One real start: copy the prompt for a thin untriaged item into a fresh agent session with only a clone, and record how far it gets and what it had to fetch. `./v3 feedback check`, `git diff --check`, and a diff review against this plan. The maintainer's own start from his iPhone SE after deploy is the release check and stays pending until he does it. Full suite not run.
- Estimate details (extended mode only): driver: every theme at two widths, and one real agent run; uncertainty: medium, the agent run may expose gaps in the procedure.
- Risks or open questions:
  - Impact: the thin-item risk above.
  - Early warning / validation: the real start.
  - Mitigation: fix the procedure, not the prompt's length.
- Canonical components/API contracts touched: `docs/concepts/`, `docs/feedback/items/`, `docs/plans/ui_copy_audit_checklist.md`.

## Enhancement requests

- Carried from Step 2, all not approved: Start claims the items (`backlog_claims`); parked plans list (`backlog_parked`); filling in `Estimate` and `Impact` across items.

## Study snapshots and approval

- 2026-11-23 UTC: **Approved Step 2** by the maintainer ("approved step 2"); defaults kept.
- 2026-11-23 UTC, Step 3 handoff: **0.60** projected total, reaffirmed. Stage work: 0.10, 0.10, 0.18, 0.10, 0.06, 0.10 (not summed). Stage 6 owns the shared end-to-end checks and docs. Claude, single exposed rater. Scope v3. Reference R1, hashes not re-verified this session. Baseline `origin/main` `8a5aa40`.

- 2026-11-23 UTC: **Approved Step 3** by the maintainer ("approved step 3").

Status: Step 3 approved.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*
