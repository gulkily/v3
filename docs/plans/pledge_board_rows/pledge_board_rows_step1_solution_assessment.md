> **Feature plan:** [Step 1](./pledge_board_rows_step1_solution_assessment.md) · [Step 2](./pledge_board_rows_step2_feature_description.md) · [Step 3](./pledge_board_rows_step3_development_plan.md) · [Step 4](./pledge_board_rows_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_board_rows_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_board_rows`; [ledger](../effort_ledger/pledge_board_rows.md). Standing P2 enrollment. Parent: `v3/pledge_cards`.

Extended estimates: enabled

Status: Step 1 written 2026-11-17. Approved by the maintainer 2026-11-17 ("approved step 1", Option A, after asking for a brief A/B comparison). The maintainer approves every step in this cycle.

**Cycle estimate: 0.35**

# Step 1: Solution Assessment — Pledge board rows

Cycle 5 in [pledges_checklist.md](../pledges_checklist.md), parked until now. Base: `origin/main` at `d33114d2`. [PR #319](https://github.com/gulkily/v3/pull/319) (the three polish one-shots) is still open, so this cycle starts from main without it. #319 changes the post card and a reload in the browser after approval. It does not touch `thread_row.php`, the board or `StaticArtifactInvalidator.php`, so it should not conflict.

## Original Query

We're continuing pledge campaigns on v3 (github.com/gulkily/v3). Cycles 1–3, 8, 9, 10 and 11 are merged, and the three polish one-shots are in PR #319. If #319 has merged, start from it; if not, start from main anyway and note it in Step 1. The epic was replanned on 2026-11-17 as an MVP. Keep it small, and cut anything that isn't needed for people to find and follow a campaign.

Please clone the repo, fetch a fresh origin/main, and read AGENTS.md, docs/railspike/RAILSPIKE.md, docs/plans/pledges_checklist.md, docs/plans/pledges_spec.md (the "Board" bullet and decision 5 on approved-only counting) and docs/concepts/pledge_campaign.md.

Start a staged Railspike cycle called pledge_board_rows (cycle 5 in the checklist, parked until now). Follow AGENTS.md and RAILSPIKE.md with debug mode on. Enroll it in the railspike-effort study with parent v3/pledge_cards, using a per-cycle ledger in docs/plans/effort_ledger/.

The goal: a campaign is easy to find. Right now it's only reachable through its link. Its row on the board shows its progress, e.g. "620 / 1000 coffees", plus the status word when there is one ("Goal reached", "Complete").

Step 1 should answer:
- Where the row's numbers come from. Thread pages compute totals when a page is read (PledgeCampaign::fromPosts). Should list pages do the same per row, or keep the totals in the read model? Say what each costs on a board with hundreds of threads, and how each stays correct when a pledge, fill, withdrawal, review, retraction or approval changes them. Approval must update the board too: see StaticArtifactInvalidator and #317.
- Which lists show it: board, recent, tag pages, profile, the phone layout. Pick the fewest that make campaigns findable.
- What the row shows at 375 px, in every theme, without crowding the title. Totals count only approved members' pledges, the same as the thread page.

Compare two or three approaches. A rule that belongs to a later cycle gets one sentence, not groundwork.

Write Step 1 and share it with us as an attached file in the chat. We approve each step ourselves ("approved step N"). Keep pledges_checklist.md updated as the cycle progresses. Run only the smoke set plus tests for what changed, never the full suite. Fix any test that fails on the base commit. Put a bash checkout snippet in every PR, and check that a PR is still open before pushing to its branch.

## Problem

A campaign thread scrolls off the board like any other thread, and nothing on its row says it is a campaign or how far along it is.

Concepts touched: [pledge campaign](../../concepts/pledge_campaign.md) in every option; [static publication](../../concepts/static_publication.md) in A and B.

No matching item in `docs/feedback/items/`.

## What the code does today

- ZenMemes uses the `list` board. `/` and `/threads/` both render `board_list.php`, 50 rows a page plus pinned threads, each row from `thread_row.php`. The phone layout is the same template; only CSS changes.
- Each board row already carries the opening post's text, so a `goal:` line can be found with no extra query.
- `ListThumbnails::attach()` adds per-row data to the visible page only. `EventStatus::forThreads()` derives state for many threads in one query. Both are precedents.
- Every pledge, fill, withdrawal and review is a reply, and `invalidateReply()` deletes the baked `/index.html`. A retraction deletes every top-level page.
- Approval (#317) deletes the baked pages of threads the person posted in, but not `/index.html` or `/threads/`. A reply does not delete `/threads.html` either. Baked pages are served only to visitors with no cookies.

## Option A — Read when shown, for campaign rows on the visible page (recommended)

**Estimate: 0.35**

The board picks the campaign rows on the page it is about to show, by their `goal:` line. It loads those threads' posts and runs `PledgeCampaign::fromPosts()` on each, as the thread page does. Rows that are not campaigns cost nothing.

- Cost: at most 50 rows, and usually zero or one campaign among them. Hundreds of threads elsewhere on the board add no work. A batched load keeps it to one query for the page.
- Correct by construction: the row and the thread page call the same function on the same rows, so approved-only counting matches.
- Freshness: replies and retractions already clear `/index.html`. This cycle adds `/index.html`, `/threads.html` and `/threads/index.html` to what approval and a reply clear.
- No schema change and no rebuild.
- A campaign with thousands of replies costs that many rows on each board view; no such campaign exists yet.
- Driver: the batched post load and the two invalidation additions. Uncertainty: low.

## Option B — Totals kept in the read model

**Estimate: 0.70**

New columns on the `threads` table hold goal, pledged and status. They are filled at rebuild and updated on each write.

- Cost at read: none.
- It is a read-model schema change: `SCHEMA_VERSION`, the schema spec and a migration test, then a rebuild on deploy.
- Approval changes no post, yet it changes what counts. Approval and operator changes would need to recompute every campaign the person touched. A missed path leaves a wrong number until the next rebuild.
- Two places decide what counts unless the writer calls `fromPosts()` too, which brings back Option A's work at write time.
- Driver: every write path that changes counting. Uncertainty: medium.

## Option C — The goal only, no progress

**Estimate: 0.15**

The row shows "Goal: 1000 coffees", read from the opening post already in the row.

- No query and nothing to keep fresh.
- Campaigns become findable, but the row never shows progress or "Complete", which the request asks for.
- Driver: one row line. Uncertainty: low.

## Which lists

The board only: `/` and `/threads/`, phone and desktop alike. That is where people look for threads.

- Recent lists posts, not threads, so it has no row to extend.
- Tag pages and card-layout boards use `thread_card.php`; other instances use it, and ZenMemes does not.
- Profile and search also use `thread_row.php`, so they could show the line later by attaching the same data. This cycle leaves them alone.

## The row at 375 px

One extra line in the row's first column, under the title, where the event line sits: "620 / 1000 coffees · Goal reached".

- The title keeps its own line, and the reply count keeps its column.
- The numbers use the meta size, and the status word reuses the thread page's `pledge-status` look. Every theme draws it with its own ink.
- A long unit wraps rather than pushing the reply count.
- No bar: a second bar style is not needed to find a campaign.
- With the `pledges` flag off, the row is unchanged.

## Recommendation

Option A. It answers both questions in the request with one function the thread page already trusts. It needs no schema change, and its only new freshness work is three paths in two places. It is a full vertical slice: a visitor sees the line on the board, at 375 px and on desktop, in every theme.

Questions for Step 2:

- "620 / 1000 coffees", as in the spec, or "620 of 1000" to match the thread page? Suggested: the spec's slash, to keep the line short.
- Show delivered on the row? Suggested: no; "Complete" already says the goal was delivered.

Enhancement requests: none. The maintainer asked whether this is only a display change and for a brief A versus B comparison; both were answered in the chat, and nothing changed.

## Study snapshots

- Intake (after reading `PledgeCampaign.php`, the board controller and templates, `StaticArtifactInvalidator.php` and #317's approval path; no pristine intake): **0.35** for Option A. Baseline `origin/main` at `d33114d2`; R1, P2; Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC.
- Step 1 handoff: **0.35** for Option A, scope v1. Options: A 0.35, B 0.70, C 0.15, not summed. R1 hashes not re-verified in this session.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
