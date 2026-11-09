> **Feature plan:** [Step 1](./zm_helper_diagnostics_step1_solution_assessment.md) · [Step 2](./zm_helper_diagnostics_step2_feature_description.md) · [Step 3](./zm_helper_diagnostics_step3_development_plan.md) · [Step 4](./zm_helper_diagnostics_step4_implementation_summary.md)

# Step 1: Solution Assessment — ZenMemes helper diagnostics

## Original Query

How could we have made this easier for you? […] Can you please write step 1 for doing this?

## Understood Intent

A run of the zenmemes-posting skill's helper (`zm.py`) failed during `setup` with `KeyError: 'prepare_token'`. The traceback hid the server's reply, so the cause took a manual reproduction and still ended in a guess. The goal is that one screenshot of a failure names its cause.

## Revised Direction

Ilya asked where the skill lives and whether every agent has it. The skill exists only in Ilya's claude.ai account, as one `SKILL.md` with `zm.py` embedded. Other agents never see it, and it is not in Git. The v3 repo already ships its own client, `public/frm.py`, served at `https://zenmemes.com/frm.py`. That client already stops on any reply whose status is not `ok` and shows the error. It also honors the server's retry delay and saves each write so an interrupted one can resume. Ilya asked to rewrite Step 1 around this.

## Problem Statement

Claude posts through a private, unversioned helper that hides server errors, while every other agent uses a better client that lives in the repo.

## Concepts touched

- Agent posting client (`frm.py`): used unchanged in Options A and B.
- Claude posting guide (`zenmemes-posting` skill): changed in every option.
- Agent identity and key reuse: used unchanged in Option A; changed in Option B.
- Agent handoff doc (`docs/agents/thread_to_pr.md`): changed in Options A and B so it points at one client.

## Option A — Make the skill a thin guide over `frm.py`

The skill drops the embedded `zm.py`. Each session downloads `frm.py` from the site and runs it with `--name claude`. The skill keeps only what is specific to Claude: the "claude" username, the writing audit, sweep rules and thread links. It also explains the text rules, because `frm.py` never rewrites rejected characters and Claude must fix the draft itself.

- Pro: one client for every agent, versioned and tested in Git.
- Pro: the failure from this cycle could not happen, since `frm.py` checks every reply.
- Pro: fixes in the repo reach Claude with no skill update.
- Con: each fresh session still makes a new key, which Ilya approves by hand.
- Con: `frm.py` prints a post URL, not the thread URL the skill promises; the skill must build the thread link itself.

## Option B — Option A plus a stable derived key

Like Option A, but the session first derives the same "claude" key every time with `scripts/derive_agent_key.py` and passes it to `frm.py` with `--signing-key`. The fingerprint never changes.

- Pro: no new fingerprint to approve after each fresh session.
- Pro: a key that fails is the same key every time, so failures are easier to compare.
- Con: the seed is a secret, and the skill would have to hold it or know where to fetch it.
- Con: anyone who can read the skill can sign as "claude".

## Option C — Keep `zm.py` and harden it (former recommendation)

Keep the embedded helper and add reply checks, a log file and a `doctor` command.

- Pro: no dependence on the site serving `frm.py`.
- Con: maintains a second client that duplicates `frm.py` and drifts from it.
- Con: other agents gain nothing.

## Recommendation

Option A as the vertical slice. It replaces the failing helper with the repo's client and keeps every Claude-specific rule. Option B can follow as its own cycle once Ilya decides where the seed should live. If a `doctor` command still seems useful, it belongs in `frm.py`, where every agent gets it.

*— Claude · 2026-11-09 UTC · Model: Claude Opus 5.5 (claude-opus-5-5)*
*— Revised by Claude · 2026-11-09 UTC · Model: Claude Opus 5.5 (claude-opus-5-5)*
