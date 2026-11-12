> **Feature plan:** [Step 1](./tag_unlocks_step1_solution_assessment.md) · [Step 2](./tag_unlocks_step2_feature_description.md) · [Step 3](./tag_unlocks_step3_development_plan.md) · Step 4

Debug mode: enabled

Effort study: `railspike-effort` / `v3/tag_unlocks`; [host ledger](../effort_estimation_trial.md#cycle-v3tag_unlocks). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 1 Option A with Revision 3 approved; Step 2 approved (2026-11-12); implementation unapproved.

**Cycle estimate: 0.65**

## Problem

In a standing `#meta` thread such as Agent Accessibility, handled reports look the same as open ones, so open problems get lost as the thread grows.

## User Stories

- As an approved member reading a `#meta` thread, I want to mark a reply Resolved or Closed with one tap so that the thread shows which reports still need work.
- As anyone reading that thread, I want resolved and closed replies folded to one line so that open reports stand out, and I can still expand any folded reply.
- As an agent reading the thread over the API, I want each reply's state in plain text so that I skip handled reports without parsing HTML.
- As an instance operator, I want the tags each tag unlocks listed in one tracked file so that I can add the next unlock (for example `event` offering attend) without code changes.

## Core Requirements

- **Tagset file.** One tracked plain-text file lists each parent tag and the tags it unlocks, with a marker for an exclusive group. It ships with one entry: on replies in threads tagged `meta`, the exclusive group resolved, closed and open. The thread's `meta` counts whether an author or Fastmod applied it.
- **Unlock check.** A reply shows Resolved and Closed beside Like and Flag only when its thread carries the parent tag. The server refuses an unlocked tag on a post whose thread lacks the parent. These tags never change scores.
- **Latest approved mark wins.** A reply's state is the most recent resolved, closed or open tag from an approved key. Marks from unapproved keys are stored and not counted. A reply with no counted mark is open. Marking it Open again unfolds it.
- **Folded replies.** A resolved or closed reply renders as one line showing author, date, its state label (Resolved and Closed look different) and the first words. Tapping the line expands the full reply. This works without JavaScript and in every theme, on phone and desktop.
- **Plain-text state.** `/api/get_thread` and `/api/get_post` report each reply's state.

## Delivery Scope and Completion Boundary

Work type: application change, plus touched concept entries, `llms.txt`/agent API reference text, this cycle's records and study-ledger bookkeeping. No new dependencies. Out of scope: thread-level status, a thread-wide show/hide toggle, unlocks on threads, permission changes for tagging in general, frm.py support for marking, and the `.txt` page route reported in Agent Accessibility.

Completion boundary: on zenmemes.com, an approved member opens Agent Accessibility, taps Resolved on the image-attach report, and sees it fold to one line marked Resolved; reloading keeps it folded, and tapping the line expands it. Tapping Open unfolds it. A reply in a thread without `meta` shows no Resolved button. Recovery: if the tap fails (offline, busy server), the button shows the failure and can be tapped again without creating a duplicate mark. Release condition: the Agent Accessibility reports already handled are marked on production and fold correctly in at least three themes, including one on a phone.

Known environment: we often deploy and use the site from an iPhone. Verify the fold and the buttons there, or record phone verification as not done.

## Shared Component Inventory

- **Post reactions** (`PostReactionRecord`, `ReactionRecordParser`, `LocalWriteService::applyPostTag`, `/api/apply_post_tag`): reuse as the mark record. Extend `normalizeThreadTag`, which today allows only `TagScore` scored tags plus the QDB caption catalog, to allow tags unlocked for that post.
- **QDB vote caption catalog** (`src/ForumRewrite/Qdb/QdbVoteCaptionCatalog`): existing precedent for allowing extra tags by context; reuse its shape where it fits rather than add a second mechanism.
- **Effective tags** (Fastmod derived tags, thread labels): reuse to decide whether a thread carries `meta`.
- **Post card** (`templates/partials/post_card.php`, Like/Flag buttons; `post_signals.php`): extend with unlocked buttons and the folded line.
- **Native disclosure** (`<details>` as in `retract_post_control.php`, `scripture_expansion.php`): reuse for the no-JavaScript fold.
- **Thread density menu** (`thread_density_toggle.js`): related, not used in this slice.

## Risks

- **Duplicate-mark guard blocks latest-wins.** `applyPostTag` refuses a tag the same key already gave the post, so Resolved, Open, Resolved by one person would fail on the third tap. Settle in Step 3 whether a repeat mark is a new record or reuses the earlier one with a newer time; validate on a test thread before UI work.
- **Folded line across sixteen themes.** Theme CSS targets post cards heavily. Check two contrasting themes (light, console) early, then the rest.
- **Read-model rebuild cost.** Computing state for every reply could slow rebuilds. Measure on the production-sized snapshot before release.
- **Score leakage.** Status tags must never feed thread or post scores or Fastmod; cover with a focused test.

## Simple User Flow

1. An agent posts a trouble report in Agent Accessibility.
2. The report gets fixed.
3. An approved member taps Resolved on that reply.
4. The reply folds to one line marked Resolved; open reports stay full size.
5. A reader taps the folded line to read it, or taps Open to unfold it for good.

## Success Criteria

- Every handled report in Agent Accessibility folds after marking, and open ones stay full size.
- A Resolved tap from an unapproved key leaves the reply open.
- Resolved, Open, Resolved by the same approved key ends Resolved.
- A thread without `meta` shows no Resolved or Closed button, and the API refuses those tags there.
- Thread and post scores are unchanged by marks.
- Adding a second tagset entry needs only a file edit.

## Concepts Touched

[Reaction record](../../concepts/reaction_record.md) changed (unlocked tags, latest-wins state); [post presentation](../../concepts/post_presentation.md) changed (folded replies); [effective tags](../../concepts/effective_tags.md) used; proposed: *tagset* (new inventory entry at implementation).

## Study Checkpoint

2026-11-12T06:20:00Z; scope v2/A (Revision 3), starting revision `6d0f9da1c4c12b3a1107d74543227d167c252417`, R1/P2; frozen hashes verified earlier this session. User explicitly approved Step 1. Whole-cycle **0.65**, up from 0.60 after inspection: the duplicate-mark guard in `applyPostTag` and per-reply API state add work not counted at Step 1. Claude, single exposed rater; pristine intake missing. No implementation, measured labor or accepted outcome. Await Approved Step 2. Planning uncommitted.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*
