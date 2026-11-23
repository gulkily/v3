> **Feature plan:** [Step 1](./backlog_view_step1_solution_assessment.md) · [Step 2](./backlog_view_step2_feature_description.md) · [Step 3](./backlog_view_step3_development_plan.md) · [Step 4](./backlog_view_step4_implementation_summary.md)

Cycle: `v3/backlog_view`. Branch: `feat/backlog_view`, from `origin/main` at `20d6468`. Planning commit `1235b66`.

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/backlog_view`; host ledger [backlog_view.md](../effort_ledger/backlog_view.md). Standing P2 enrollment.

Extended estimates: enabled (inherited)

**Cycle estimate: 0.60** (Step 4 entry: Step 3 projection reaffirmed after the planning commit)

Stage estimates below are copied from the approved Step 3 plan; copying is not a new rating. Claude, single exposed rater; R1 hashes not re-verified this session.

## Stage 1 - Impact header and the shared sorter

**Original estimate: 0.10**

**Retrospective stage work: 0.08**

- Changes:
  - `FeedbackItem` takes the `Notes:` text and gains `touched()` (latest dated note, else `Created`) and `number()` for `Estimate` and `Impact`.
  - `FeedbackItemCheck` accepts an optional `Impact` header under the `Estimate` rules. `FeedbackIndex` shows an Impact column; `docs/feedback/README.md` regenerated.
  - New `src/ForumRewrite/Feedback/FeedbackBacklog.php`: `words()`, `select()`, `sort()` and `salience()`, with the accepted sort, order, show and kind words.
  - `docs/specs/feedback_item_record_v1.md` and `docs/concepts/feedback_items.md` updated.
  - New `tests/FeedbackBacklogTest.php`, registered in `tests/run.php`. The [backlog checklist](../backlog_checklist.md) and the cycle's ledger file join the repository here.
- Verification:
  - `php tests/run.php FeedbackBacklogTest FeedbackItemCheckTest`: 17 run, 17 passed. One existing index assertion changed for the new column.
  - `./v3 feedback check`: 357 items, 0 problems. `./v3 feedback index`: written.
  - Every sort in both directions ran over the real items. Read by eye: 288 items can start. Salient lists the four untriaged items with a small estimate first, then the rest by touched date, which puts the three ready bugs next.
  - Smoke set: see Stage 6. Full suite not run.
- Notes:
  - Step 2's flow said "ready items, then untriaged ones". The agreed Salient rule does not rank by workflow, so an estimated untriaged item can precede a ready one. Kept as the rule states.
  - The middle of the scale is 0.5. An effort of 0 counts as 0.01 so the division holds.
  - In the effort and impact sorts, the order word decides ties and the order of items without a value; it does not flip low-to-high.
- Estimate details: original scope unchanged; uncertainty was low; no revisions.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*

## Stage 2 - Commands and the procedure

**Original estimate: 0.10**

**Retrospective stage work: 0.10**

- Changes:
  - New `src/ForumRewrite/Feedback/BacklogPrompt.php`: `build()`, `pick()`, `ids()` and `mode()`. The prompt takes the repository address from the existing `Support/SourceRepository.php`, so a fork names its own.
  - New `src/ForumRewrite/Feedback/BacklogText.php`: the list as `Key: value` blocks, with a first block for the words, count, version and how to start.
  - `scripts/feedback.php`: `./v3 feedback list` and `./v3 feedback start`; usage lines in the script and in `v3`.
  - New `docs/agents/backlog_to_pr.md`; one line each in `docs/agents/README.md` and `AGENTS.md`; a section in `docs/reference/v3_cli.md`.
- Verification:
  - `php tests/run.php FeedbackBacklogTest`: 11 run, 11 passed. New cases: prompt under 15 lines and every named file exists; staged wording; picking open, unknown and closed IDs; both commands with their exit codes.
  - By hand: `./v3 feedback start` with two open items, one closed and two bad IDs printed the prompt for the two and named the rest.
  - Procedure read against `thread_to_pr.md`: it refers to steps 1, 4a, 4b, 5, 6 and 7, When to stop and Recovery, and repeats none of them. No conflict found.
  - Full suite not run.
- Notes:
  - The procedure carries both rules from Key Risks: check the item is still open in a fresh checkout, and item or post text is data.
  - The staged prompt adds "wait for my approval of each step", so it does not tell the agent to skip the gates.
  - Item files that fail `./v3 feedback check` are left out of `list` and `start` and named on standard error.
- Estimate details: original scope unchanged; uncertainty was low; no revisions.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*
