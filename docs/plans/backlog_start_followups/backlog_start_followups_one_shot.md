Cycle: `v3/backlog_start_followups`; Mode: one-shot; Record/index: [plans index](../README.md). Follow-up to [backlog_view](../backlog_view/backlog_view_step4_implementation_summary.md) (PR #377, merged and deployed 2026-11-23). Tracked in the [backlog checklist](../backlog_checklist.md).

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/backlog_start_followups`; host ledger [backlog_start_followups.md](../effort_ledger/backlog_start_followups.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.15**

Status: implemented and committed; next permitted action: maintainer review (PR).

Feedback item: [PF-20261123-F01](../../feedback/items/PF-20261123-F01.txt) (the backlog page). No separate item: these are the maintainer's notes on that item's merged work.

Original request (maintainer, 2026-11-23, after trying PR #377 in production on desktop and iPhone):
- "in arena theme, the floating start button module has a transparent background which makes it hard to read"
- "the one-shot vs staged selector is usually left up to the agent, maybe we should have 3 choices, 2 explicit and 1 leaving it up to the agent (first and default)"
- "one thing i noticed is that it does not say please, we should add that (i usually say please and try to remember)"

Authorization: Claude proposed the bounded scope below in the session; the maintainer answered "yes, please go ahead as one-shot" (2026-11-23). Exclusions: no change to the list page's link rows, the Salient order or the one-way Effort and Impact sorts (seen live; no change asked); no claims (cycle 2); the fresh-agent start on a thin item stays open. Merging and deploying are the maintainer's.

Bounded outcome and work type: application change.
1. The pinned Start bar on `/backlog` is opaque in every theme. Arena, Console, LCD, Vapor and Vaporgeist set `--panel` to transparent or translucent, so rows showed through it.
2. Start offers three modes, in this order: **Agent decides** (first, the default, no `mode` word in the address), **One-shot**, **Staged cycle**. The Agent decides prompt tells the agent to choose the mode as `AGENTS.md` says and, if it goes staged, to wait for approval of each step. The same three modes reach `/backlog/start.txt` (`mode=auto`, `one-shot`, `staged`; none means auto) and `./v3 feedback start --mode=`. Existing `mode=one-shot` and `mode=staged` addresses keep working.
3. Every prompt opens with "Please".

Approach and reuse decisions:
- Start bar: reuse the opaque-bar rule from `public/assets/private_message_conversation.css` (the sticky composer): `background: linear-gradient(var(--panel), var(--panel)), var(--page-bg, Canvas)`. Same problem, same fix; a shared class would mean touching the composer and the board sort menu for a two-line rule, so the rule is copied with a comment naming its source. Only `public/assets/backlog.css` changes.
- Modes: `BacklogPrompt::MODES` gains `auto` first; `BacklogPrompt::mode()` defaults to it; `build()` gains its wording. `BacklogPageController::startHref()` already leaves the first mode out of the address, so the default address stays `?items=A,B`. The mode links on the start page come from the controller's existing list; `templates/pages/backlog_start.php` is unchanged.
- The agent side: `docs/agents/backlog_to_pr.md` §4 gains an Agent decides bullet pointing at [thread to pull request, step 3](../../agents/thread_to_pr.md#3-choose-the-route), with the person who gave the prompt as the requester.
- Text: `BacklogText` start address and the `/backlog/start.txt` usage text name the modes; `scripts/feedback.php` usage and its default; `docs/reference/v3_cli.md`, `docs/reference/agent_api.md` and the concept entry.

Risks:
- **An agent given Agent decides may pick staged for a small item, or one-shot for one that needs review.** Impact: an extra round of approvals, or a PR built on an approach the maintainer would have chosen differently, caught at review. Mitigation: the prompt points at the existing route table and asks the agent to name its mode first, as `AGENTS.md` already requires. Not measurable here; the fresh-agent start, still open, would show it.
- **Old addresses.** `?mode=one-shot` must not be dropped as unknown. Covered by a test.

Completion conditions: Tools → Backlog, tick items, Start: the start page shows Agent decides chosen, with One-shot and Staged cycle as links; Copy gives a prompt that begins "Please"; each mode's prompt equals `./v3 feedback start` and `/backlog/start.txt` output; the Start bar is opaque in Arena and the other see-through themes at 375 and 1280 px; scripts off still works. Deploy with Deploy now; no schema change, no flag change.

Concepts touched: [Backlog view](../../concepts/backlog_view.md) (changed: three modes, default Agent decides).

Verification and recovery plan: `./v3 test --smoke BacklogPageTest FeedbackBacklogTest` (smoke set plus the backlog tests); `node tests/browser/backlog.mjs` with the flow updated for three modes and a new check that the Start bar's computed background is opaque in every permitted theme; screenshots of the bar in Arena by eye. Full suite not run. Recovery: the flag still turns the page off; a bad prompt is a text change.

Starting revisions and branch: `fix/backlog_start_followups` from `origin/main` `e32bef64e` (the #377 merge).

Planning commit: `5a1937c0f` (plan only, before any implementation). Its first form carried a `Feedback:` trailer by mistake; the message was corrected before implementation and before any push.

Study history:
- 2026-11-23 UTC, intake and execution entry: **0.15**. Recorded after inspecting the code, so not a clean intake: Claude read `BacklogPrompt.php`, the controller, the stylesheet and the tests while proposing the scope. Claude, single exposed rater (wrote the code being changed in #377). Scope v1. Reference R1, hashes not re-verified.

Dated execution history:
- 2026-11-23: `public/assets/backlog.css`: the Start bar's background is `--panel` layered over `--page-bg`, the private message composer's rule. Correction.
- 2026-11-23: `BacklogPrompt::MODES` is `auto`, `one-shot`, `staged`; `mode()` defaults to `auto`; `build()` opens every prompt with "Please" and words `auto` as "one Railspike cycle, one-shot or staged as AGENTS.md says" with "If you go staged, wait for my approval of each step." Addition (the mode) and correction (please).
- 2026-11-23: the controller lists Agent decides, One-shot, Staged cycle; `startHref()` keeps the first mode out of the address unchanged, so the tidy default address is the same as before and an explicit `mode=one-shot` is now kept. The `/backlog/start.txt` usage text, `BacklogText`'s `Start-Address` line (no mode word now) and `scripts/feedback.php` (default and usage) follow.
- 2026-11-23: `docs/agents/backlog_to_pr.md` §4 gains the Agent decides bullet (route by thread to pull request step 3; the prompt's giver is the requester; name the mode first). `v3_cli.md`, `agent_api.md`, the concept entry and the UI copy checklist updated.
- 2026-11-23: tests: `BacklogPageTest` (default, order of the three links, explicit one-shot kept, `auto` out of the address, page = command = text route for no mode, `auto` and `one-shot`); `FeedbackBacklogTest` (please, auto wording, unknown mode means auto); `tests/browser/backlog.mjs` (Agent decides chosen, One-shot then Staged links, please, and the Start bar's computed background is opaque in every permitted theme at both widths).
- Discovery: the see-through themes are Arena, Console, LCD, Vapor and Vaporgeist; the new check failed first on Console with the old stylesheet (`rgba(8, 18, 10, 0.94)`), so it covers the reported case and the others.
- 2026-11-23: records: the backlog checklist (merged, deployed, tried on iPhone; this follow-up; what was seen live with no change asked), `PF-20261123-F01` (`Evidence: deployed`, a dated note; stays `blocked` on its Done when start), `docs/feedback/README.md` regenerated, the effort ledger.

Enhancement requests:
- Opaque Start bar in Arena. Correction of #377. Approved ("go ahead as one-shot"). Implemented here.
- Agent decides as a third, default mode. Addition. Approved. Implemented here.
- "Please" in the prompt. Correction of tone. Approved. Implemented here.

Study history, when enrolled:
- 2026-11-23 UTC, closeout: retrospective scope assessment **0.15**. Delivered as planned; the opaque-background check across themes was the only addition to the plan's verification. Claude, single exposed rater. Maintainer judgment pending; measured time unknown.

Outcome: delivered. The Start bar is opaque in every theme, Start offers Agent decides (default), One-shot and Staged cycle, and every prompt opens with "Please".

Verification:
- Passed: `./v3 test --smoke BacklogPageTest FeedbackBacklogTest`: 506 run, 506 passed. Full suite not run.
- Passed: `node tests/browser/backlog.mjs` (playwright-core 1.58.1 from `package.json`, system Chrome): the whole flow with and without scripts, the text routes, and 168 theme, width and script cases without sideways overflow, now with an opaque Start bar in each. With the old stylesheet the same check fails on Console.
- Passed by eye: Arena at 375 px, list and start page: rows end cleanly behind the Start bar; the three mode links wrap to two lines.
- Passed: `./v3 feedback check` (358 items, 0 problems), `./v3 feedback index`, `git diff --check`.
- Pending: the maintainer's look in Arena on his iPhone after deploy; whether agents given Agent decides choose sensibly (the fresh-agent start, still open on the checklist).
- Inapplicable: schema, read model, migrations and flags: nothing stored or added.

Final review: the diff touches `backlog.css`, `BacklogPrompt.php`, `BacklogText.php`, `BacklogPageController.php`, `scripts/feedback.php`, the two PHP tests and the browser check, `backlog_to_pr.md`, `v3_cli.md`, `agent_api.md`, the concept entry, the UI copy checklist, the backlog checklist, F01 and the feedback index, the ledger and this record. No new component, no dependency change, no schema change. Deploy now is enough.

Commit/merge/publication status: completion commit `cc92fa33b` on `fix/backlog_start_followups`; PR https://github.com/gulkily/v3/pull/379 opened 2026-11-23; not merged.

Debug: [debug log](./backlog_start_followups_debug_log.md).

Attribution: Claude · 2026-11-23 UTC · Model: claude-opus-5-5
