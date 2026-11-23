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
- [x] `/backlog.txt` and `/backlog/start.txt`
- [x] Tools entry with an icon in every icon set
- [x] Works with scripts off; no sideways overflow at 375 and 1280 px in every theme
- [x] Feedback item allocated; concept entries written
- [ ] One real start by a fresh agent on a thin item. The item first proposed, PF-20261111-B02 (favicon), was built in the meantime (PR #378). The maintainer started PF-20261104-B18 and PF-20261104-B12 together by hand on 2026-11-23 with the prompt as deployed in #379; outcome pending.
- [x] PR opened: https://github.com/gulkily/v3/pull/377 (2026-11-23)
- [x] Merged (#377, 2026-11-23) and deployed with the flag on
- [x] Maintainer tried it on desktop and iPhone: sorts, Start and Copy work (2026-11-23)
- [ ] Maintainer accepts: one real start from his phone ends in a PR (he is trying the prompt)
- Follow-up one-shot `backlog_start_followups` ([record](./backlog_start_followups/backlog_start_followups_one_shot.md)), from his first look:
  - [x] Start bar opaque in Arena and the other see-through themes
  - [x] Three modes: Agent decides (first, default), One-shot, Staged cycle
  - [x] The prompt says please
  - [x] PR opened: https://github.com/gulkily/v3/pull/379 (2026-11-23)
  - [x] Merged (#379) and deployed, 2026-11-23: zenmemes.com reported version `cdc8abf4683c` and the new prompt on `/backlog/start.txt`
  - [ ] Maintainer's look at the Start bar in Arena on his iPhone
- Follow-up one-shot `backlog_prompt_access` ([record](./backlog_prompt_access/backlog_prompt_access_one_shot.md)), from his notes while starting two items:
  - [x] The prompt asks for a clone and a pull request, not patch files, and to stop and say what to connect
  - [x] A prompt for several items asks the agent to say so first when they do not belong in one pull request
  - [x] `backlog_to_pr.md`: access first (§1), grouping check (§4)
  - [x] PR opened: https://github.com/gulkily/v3/pull/381 (2026-11-23)
  - [ ] Merged and deployed
  - [ ] Asked, not answered: the same access line in the thread to pull request prompt
- Seen live, no change asked: the five lines of link rows at 375 px, Salient not putting ready items first, Effort and Impact sorting one way. Also seen, 2026-11-23: `/backlog/start.txt` is served with a two-day `Cache-Control: max-age`, so a browser can show a prompt from before a deploy.

## Cycle 2 — `backlog_claims` (staged; rough estimate 0.40)

Start claims the items, so the page and other agents see who holds them.

- [ ] Step 1: where a claim lives. Our lean: a `claim:` line in a signed post, as pledges use `pledge:` lines. Compare with a mark on the item's cited post and with a new signed record. The `claimed` reply state that `#issue` threads already offer (`docs/reference/agent_api.md`, Reply resolution) is the existing form of a mark on the cited post; compare it by name.
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
- The prompt says please. The agent picks one-shot or staged unless Start names one. (2026-11-23)
- Several picked items become one cycle. (2026-11-23)
- The prompt asks for a clone and a pull request, never patch files; an agent without access stops and says what to connect. (2026-11-23)
- An agent given several items says so before building when they do not belong in one pull request. (2026-11-23)
- Start means prompt plus claim; the claim arrives in cycle 2. (2026-11-23)
- Parked plans are shown separately. (2026-11-23)
