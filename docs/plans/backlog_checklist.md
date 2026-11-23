# Backlog checklist

Tracks the three backlog cycles. Update it when a step is approved, a PR opens or merges, or the maintainer accepts a cycle. Plans: [backlog_view Step 1](./backlog_view/backlog_view_step1_solution_assessment.md), [Step 2](./backlog_view/backlog_view_step2_feature_description.md), [Step 3](./backlog_view/backlog_view_step3_development_plan.md), [Step 4](./backlog_view/backlog_view_step4_implementation_summary.md). Mockup: https://claude.ai/artifact/3TP5kpHR5jL27mSZjCqmmn

Goal: one page of things to work on, sortable, where the maintainer or an agent starts one item or several, without duplicate work.

## Cycle 1 — `backlog_view` (staged; estimate 0.60)

The page, the sorts, the filters, Start with a short prompt, and the agent interface.

- [x] Step 1 approved (Option E), 2026-11-23
- [x] Step 2 approved, 2026-11-23
- [x] Step 3 approved; plans moved to `docs/plans/backlog_view/` and committed, 2026-11-23
- [x] `Impact` header in the item spec, check and index
- [x] Shared sorter: entered, touched, effort, impact, salient, each in both directions
- [x] Salient run over the real items before any markup
- [x] `./v3 feedback list` and `./v3 feedback start`
- [x] `docs/agents/backlog_to_pr.md`
- [x] `/backlog` page with sorts and filters, behind a feature flag
- [x] `/backlog/start` page with mode choice, prompt and Copy
- [ ] `/backlog.txt` and `/backlog/start.txt`
- [x] Tools entry with an icon in every icon set
- [ ] Works with scripts off; no sideways overflow at 375 and 1280 px in every theme
- [ ] Feedback item allocated; concept entries written
- [ ] PR opened
- [ ] Merged and deployed with the flag on
- [ ] Maintainer accepts: one real start from his phone ends in a PR

## Cycle 2 — `backlog_claims` (staged; rough estimate 0.40)

Start claims the items, so the page and other agents see who holds them.

- [ ] Step 1: where a claim lives. Our lean: a `claim:` line in a signed post, as pledges use `pledge:` lines. Compare with a mark on the item's cited post and with a new signed record.
- [ ] Decide when a claim ends: released by hand, ended by the item closing, stale after a set time
- [ ] Decide what a guest's unsigned claim counts for
- [ ] Steps 2 and 3 approved
- [ ] Start on the page claims the picked items
- [ ] The page and `/backlog.txt` show the holder; a "Hide claimed" filter
- [ ] Agents claim and release: an address, `./v3 feedback claim`, and a step in `backlog_to_pr.md`
- [ ] Two-session test: the second session sees the claim and stops
- [ ] PR opened, merged, deployed
- [ ] Maintainer accepts

## Cycle 3 — `backlog_parked` (one-shot unless it grows; rough estimate 0.15)

The plans in `docs/plans/backlog/` as their own list.

- [ ] "Parked plans" tab on `/backlog`, listing each plan with its last step
- [ ] "Continue this cycle" prompt for a plan
- [ ] The same list in `/backlog.txt` and `./v3 feedback list`
- [ ] Decide whether parked plans can be claimed like items
- [ ] PR opened, merged, deployed
- [ ] Maintainer accepts

## Later, not planned

- Fill in `Estimate` and `Impact` across items (22 have an estimate, none an impact) so the sorts mean something. A triage task.
- Change an item's effort, impact or workflow from the page.
- A list fresher than the last deploy.
- Start for agents with no shell.
- A navigation bar entry, if Tools proves too far away.

## Decisions so far

- Feedback items are the only source; site threads enter through triage. (2026-11-23)
- Anyone can read the page; it lives under Tools for now. (2026-11-23)
- Five sorts, with triage as a filter. (2026-11-23)
- `Impact` uses the same decimal scale as `Estimate`. (2026-11-23)
- The prompt is short, assumes the agent has the repository, names files to read, and tells the agent to go to a PR. (2026-11-23)
- Several picked items become one cycle. (2026-11-23)
- Start means prompt plus claim; the claim arrives in cycle 2. (2026-11-23)
- Parked plans are shown separately. (2026-11-23)
