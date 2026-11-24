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
- [x] One real start by a fresh agent on thin items. The item first proposed, PF-20261111-B02 (favicon), was built in the meantime (PR #378). The maintainer started PF-20261104-B18 and PF-20261104-B12 together by hand on 2026-11-23 with the prompt as deployed in #379; the agent chose one-shot, built both as cycle `word97_action_fonts` and opened PR #380, which he merged the same day. That is the start PF-20261123-F01's Done when asks for; whether F01 is now done is his call, so the item is not changed here.
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
  - [x] Merged (#381), 2026-11-23
  - [ ] Asked, not answered: the same access line in the thread to pull request prompt
- Seen live, no change asked: the five lines of link rows at 375 px, Salient not putting ready items first, Effort and Impact sorting one way. Also seen, 2026-11-23: `/backlog/start.txt` is served with a two-day `Cache-Control: max-age`, so a browser can show a prompt from before a deploy.

## Cycle 2 — `backlog_claims` (staged; estimate 0.45, first rough figure 0.40)

Start claims the items, so the page and other agents see who holds them. Plans: [Step 1](./backlog_claims/backlog_claims_step1_solution_assessment.md), [Step 2](./backlog_claims/backlog_claims_step2_feature_description.md), [Step 3](./backlog_claims/backlog_claims_step3_development_plan.md), [Step 4](./backlog_claims/backlog_claims_step4_implementation_summary.md). Concept: [work claim](../concepts/work_claim.md). Feedback: PF-20261123-F02.

- [x] Step 1: where a claim lives. Chosen: a `claim:` line in a signed reply in one claims thread (Option A), over a mark on the item's cited post and a new signed record. (2026-11-23)
- [x] When a claim ends: a release by its author or an operator, a retraction, or the item becoming blocked or closed; stale after 3 days as a label
- [x] A guest's unsigned claim: shown, never counted; a claim from an unapproved key waits for approval
- [x] Steps 2 and 3 approved (2026-11-23)
- [x] Start on the page claims the picked items, in the background, only after pressing Start
- [x] The page and `/backlog.txt` show the holder; a "Hide claimed" filter (`claimed=hide`)
- [x] Release from a row, for the holder and operators
- [x] Agents claim and release: `/backlog/claims.txt`, `./v3 feedback claim` and `release`, a `Claims:` line in a site-made prompt, and §2 of `backlog_to_pr.md`
- [x] Bare claim and release replies stay out of Unread
- [x] Two-session test on a local site, with two keys and the real Quill client: the second session is told who holds the item and posts nothing
- [x] PR opened: https://github.com/gulkily/v3/pull/395 (2026-11-23)
- [ ] The maintainer posts a thread titled "Backlog claims" on zenmemes.com and gives its ID; a one-line follow-up puts it in the zenmemes profile (`backlogClaimsThread`). Until then claims are off and the site behaves as before.
- [ ] Merged, deployed, thread named
- [ ] Two-session test against zenmemes.com
- [ ] The live `Cache-Control` on `/backlog.txt` and `/backlog/start.txt` checked after deploy (the two-day header came from the host, not the repository)
- [ ] Maintainer's own Start from his iPhone, with and without a stored key
- [ ] Maintainer accepts
- Seen while building, no change made: "Hide claimed" puts the link rows on a sixth line at 375 px; a browser key with no chosen name is labelled "guest", close to the wording for a claim with no name; Recent still lists every claim.

## Cycle 3 — `backlog_parked` (one-shot unless it grows; rough estimate 0.15)

The plans in `docs/plans/backlog/` as their own list.

- [ ] "Parked plans" tab on `/backlog`, listing each plan with its last step
- [ ] "Continue this cycle" prompt for a plan
- [ ] The same list in `/backlog.txt` and `./v3 feedback list`
- [ ] Decide whether parked plans can be claimed like items
- [ ] PR opened, merged, deployed
- [ ] Maintainer accepts

## Later, not planned

- [x] Backlog moved from Reading to a new Developer group on the Tools launcher, with Platform Docs (one-shot `tools_developer_group`, [record](./tools_developer_group/tools_developer_group_one_shot.md), 2026-11-24).
- Asked 2026-11-24, not started: a close button for items that are already done, and what to do about the backlog holding many finished items. Counted that day: 282 untriaged, 3 ready, 66 blocked (62 of them built and `locally-verified`, waiting to be accepted), 6 done; 17 of the untriaged or ready items mention a pull request or merge in their notes.

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
- A claim is a `claim:` line in a signed reply in the site's claims thread, read when the backlog is shown; nothing else is stored. (2026-11-23)
- Start warns about a held item and never refuses. Only approved keys' claims count; guest claims are shown. (2026-11-23)
- A site with no claims thread named behaves exactly as before claims existed. (2026-11-23)
- Start means prompt plus claim; the claim arrives in cycle 2. (2026-11-23)
- Parked plans are shown separately. (2026-11-23)
