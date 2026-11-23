Cycle: `v3/backlog_prompt_access`; Mode: one-shot; Record/index: [plans index](../README.md). Follow-up to [backlog_start_followups](../backlog_start_followups/backlog_start_followups_one_shot.md) (PR #379, merged and deployed 2026-11-23). Tracked in the [backlog checklist](../backlog_checklist.md).

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/backlog_prompt_access`; host ledger [backlog_prompt_access.md](../effort_ledger/backlog_prompt_access.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.10**

Status: implemented and committed; next permitted action: maintainer review (PR).

Feedback item: [PF-20261123-F01](../../feedback/items/PF-20261123-F01.txt) (the backlog page). No separate item: these are the maintainer's notes on that item's merged work.

Original request (maintainer, 2026-11-23, after starting PF-20261104-B18 and PF-20261104-B12 by hand with the deployed prompt):
- "we should also maybe add a suggestion to check if the items make sense to belong together and push back if they don't?"
- "you have my permission to attach github.com/gulkily/v3. how do we integrate this into the prompts also? i see this a bit with claude and especially with chatgpt who 3 times out of 5 starts out giving me patch files and i have to cajole it into connecting to the repo and making a pr"

Authorization: Claude showed the prompt below and the scope in the session; the maintainer answered "this looks good to me. go ahead as a one-shot" (2026-11-23). Exclusions: the thread to pull request prompt and procedure (Claude asked whether to add the same access line there; the answer did not say, so it stays out and is listed under Enhancement requests); no claims (cycle 2); no change to modes, addresses or the pages' markup. Merging and deploying are the maintainer's.

Bounded outcome and work type: application change (prompt text and its procedure).
1. Every backlog prompt tells the agent to work in a clone and open the pull request itself, not to hand over patch files or pasted code, and to stop before writing code and say what to connect when it cannot clone or push. The lines sit above "Read first", because reading the named files already needs the clone.
2. A prompt for more than one item tells the agent to say so before building when the items do not belong in one pull request.
3. `docs/agents/backlog_to_pr.md` says the same: §1 opens with getting repository access, with one line each for Claude and ChatGPT; §4 says to raise a poor grouping before building, where it now says to build what fits and name the rest in the PR.
4. The backlog checklist records that PR #379 is merged and deployed.

The prompt for two items, Agent decides:

```text
Please work on these 2 v3 backlog items together as one Railspike cycle, one-shot or staged as AGENTS.md says.

Repository: https://github.com/gulkily/v3

Work in a clone of the repository and open the pull request yourself. Please don't give me patch files or pasted code. If you can't clone or push, stop before writing code and tell me what to connect.

Read first:
- docs/agents/backlog_to_pr.md
- docs/feedback/items/<ID>.txt
- docs/feedback/items/<ID>.txt

Begin now. If the items don't belong in one pull request, say so before you build. If you go staged, wait for my approval of each step. Go all the way to an open pull request, or as close as you can get.
```

Approach and reuse decisions:
- Only `BacklogPrompt::build()` words the prompt; the page, `/backlog/start.txt` and `./v3 feedback start` all call it, so one change reaches all three. No new component.
- A site with no configured repository address prints no `Repository:` line today. The access lines are printed either way: the patch-file habit does not depend on the address.
- The grouping sentence is added only when more than one item is picked, in all three modes.
- Procedure: `backlog_to_pr.md` §1 and §4 are edited in place. Vendor steps (how to connect a repository in Claude or ChatGPT) live in the procedure, not the prompt, because they go stale.

Risks:
- **The agent that most needs the access lines may still ignore them.** Impact: the maintainer cajoles as before; nothing gets worse. Not measurable here. The maintainer's start on B18 and B12 went out with the old prompt and is the baseline; his next start is the comparison.
- **"Say so before you build" could make an agent stop on items that do fit.** Impact: one extra question. Mitigation: the procedure says to raise it only when the items would not make one reviewable PR, and to continue with what fits once answered or when nobody answers.
- **Prompt length.** 9 lines to 11 for one item; the existing test caps it at 15.

Completion conditions: Tools → Backlog, tick two items, Start: the prompt carries the access lines and the grouping sentence; with one item, the access lines and no grouping sentence; the page, `/backlog/start.txt` and `./v3 feedback start` give the same bytes in every mode. Deploy with Deploy now; no schema change, no flag change.

Concepts touched: [Backlog view](../../concepts/backlog_view.md) (changed: what the prompt asks for).

Verification and recovery plan: `./v3 test --smoke BacklogPageTest FeedbackBacklogTest`; `node tests/browser/backlog.mjs` with the new wording asserted; `./v3 feedback check` and `index`; `git diff --check`. Full suite not run. The reported condition (ChatGPT and Claude starting with patch files) cannot be replayed here and is reported unverified. Recovery: the prompt is text; a bad line is a text change.

Starting revisions and branch: `fix/backlog_prompt_access` from `origin/main` `cdc8abf46` (the #379 merge).

Planning commit: `d0764b8dd` (plan and index entry only, before any implementation).

Study history:
- 2026-11-23 UTC, intake and execution entry: **0.10**. Recorded after inspecting `BacklogPrompt.php`, the procedure and the tests, so not a clean intake. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

Dated execution history:
- 2026-11-23: `BacklogPrompt` gains `ACCESS` and `TOGETHER`; `build()` prints `ACCESS` above "Read first" in every mode and adds `TOGETHER` after "Begin now" (after the Step 1 sentence in staged) when more than one item is picked. Addition.
- 2026-11-23: `docs/agents/backlog_to_pr.md`: §1 is now "Get repository access and a fresh checkout" (a pull request is the result, patches are not; check clone and push before writing code; what to ask for in Claude, ChatGPT and other tools; a patch only when asked). §4: check the grouping before building and say so first on a real mismatch; this replaces "build the ones that fit … and say so", which told the agent to decide alone. Change of an instruction.
- 2026-11-23: the concept entry, `v3_cli.md`, `agent_api.md` and the UI copy checklist follow.
- 2026-11-23: tests: `FeedbackBacklogTest` (access before "Read first" and the grouping sentence present for two items and absent for one, in all three modes; still under 15 lines); `BacklogPageTest` (both on the start page); `tests/browser/backlog.mjs` (both in the copied prompt).
- Discovery: PF-20261111-B02, the thin item the checklist proposed for the fresh-agent start, was built as PR #378 before this session. The maintainer started PF-20261104-B18 and PF-20261104-B12 instead, with the #379 prompt. Recorded on the checklist.
- Discovery: `#issue` threads already offer a `claimed` reply state. Added to the cycle 2 Step 1 line on the checklist as an option to compare.
- Discovery, no change: `/backlog/start.txt` is served with `Cache-Control: max-age=172800`. Noted on the checklist.
- 2026-11-23: records: the backlog checklist (#379 merged and deployed; this follow-up; two decisions), `PF-20261123-F01` (a dated note; stays `blocked`), the feedback index, the effort ledger.

Study history, closeout:
- 2026-11-23 UTC: retrospective scope assessment **0.10**. Delivered as planned. Claude, single exposed rater. Maintainer judgment pending; measured time unknown.

Outcome: delivered. Every backlog prompt asks for a clone and an open pull request instead of patch files, and to stop and say what to connect; a prompt for several items asks for a word before building when they do not belong together; the procedure says the same.

Verification:
- Passed: `./v3 test --smoke BacklogPageTest FeedbackBacklogTest`: 506 run, 506 passed. Full suite not run.
- Passed: `node tests/browser/backlog.mjs` (pinned playwright-core): the whole flow with and without scripts, the text routes, 168 theme, width and script cases.
- Passed: `./v3 feedback check`, `./v3 feedback index`, `git diff --check`.
- By hand: `./v3 feedback start` with two items (Agent decides) and one item (staged) print the prompts shown above.
- Unverified: whether agents that start with patch files now connect to the repository instead. It cannot be replayed here. The maintainer's start on B18 and B12 with the old prompt is the baseline; his next start is the comparison.
- Unverified: the Claude and ChatGPT lines in §1 were not run against those tools.
- Inapplicable: schema, read model, migrations, flags, icons.

Final review: the diff touches `BacklogPrompt.php`, the two PHP tests and the browser check, `backlog_to_pr.md`, `v3_cli.md`, `agent_api.md`, the concept entry, the UI copy checklist, the backlog checklist, F01 and the feedback index, the ledger, the debug log and this record. No new component, no dependency, no schema or flag change. Deploy now is enough.

Commit/merge/publication status: completion commit pending at the time of writing; PR to follow.

Debug: [debug log](./backlog_prompt_access_debug_log.md).

Enhancement requests:
- Access lines in the backlog prompt. Addition. Approved ("go ahead as a one-shot"). In this cycle.
- Grouping check in the prompt and procedure. Addition. Approved. In this cycle.
- The same access line in the thread to pull request prompt. Addition, proposed by Claude. Not approved (no answer). Not done; ask again.

Attribution: Claude · 2026-11-23 UTC · Model: claude-fable-5-2
