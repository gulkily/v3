# Issue tagset quickfix record

Cycle: `v3/issue_tagset`; Mode: quickfix trial 2 (plan, changes and done report in one commit; short record per the trial-1 draft rules). Effort study: `railspike-effort`, [host ledger](../effort_estimation_trial.md). **Cycle estimate: 0.08**

Request (2026-11-17): the trial-1 notes thread got Claim/Resolve/Close buttons only because it was tagged #meta. Maintainer chose "add a separate work-item tagset": buttons follow a tag that means "this is work", #meta stays a topic. Tag name: `issue` (Claude's recommendation; not separately confirmed).

## Plan

- `resources/tagsets.txt`: the block's Parent becomes `issue`; `meta` unlocks nothing.
- Text that says "#meta thread" for marks: llms.txt (`LlmsTxtRenderer`), the flag description, `resources/tour.txt`, `docs/reference/agent_api.md`, code comments. Agent problem reports become "#meta #issue".
- Tests: TagUnlocks fixture thread gains `issue`; pledge and browser fixtures likewise.
- Production: the three threads with marks today (Agent Accessibility; "Make Gloss and Gloss Dark the ZenMemes defaults"; "What ZenMemes does that other spaces don't") get `#issue` by a hashtag reply from `claude`, before deploy, so their marks keep working.
- Not doing: Fastmod `issue` tagging, a migration script. Entry criteria met: one commit, config and text, no schema change, revert by reverting.

Verification plan: `./v3 test --smoke TagsetsTest TagUnlocksTest PledgeCampaignTest LlmsTxtTest` plus whatever smoke surfaces. Full suite not run.

## Done report

Delivered as planned. `tagsets.txt` parent is `issue`; llms.txt (both lines), agent API doc, flag description, tour, CSS and code comments say #issue; `TagsetsTest` now also checks that `meta` unlocks nothing. Production: the three marked threads carry `#issue` (hashtag replies from `claude`, 2026-11-17), confirmed with `get_thread`; no other thread had marks.

Verification: `./v3 test --smoke TagsetsTest TagUnlocksTest PledgeCampaignTest`: 526 run, 526 passed; `LlmsTxtRendererTest`: 14 passed. Browser fixture `tests/browser/tag_unlocks.mjs` updated but not run. Full suite not run.

Trial notes: the short record held. One rule clash: the draft rule "record plus ledger row, nothing else" vs. AGENTS.md's UI copy checklist; project rules won (one checklist line). The production retag was a step outside the commit; a quickfix plan should name such steps, as this one did.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
