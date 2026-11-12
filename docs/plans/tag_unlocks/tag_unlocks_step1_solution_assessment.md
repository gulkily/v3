> **Feature plan:** [Step 1](./tag_unlocks_step1_solution_assessment.md) · [Step 2](./tag_unlocks_step2_feature_description.md) · [Step 3](./tag_unlocks_step3_development_plan.md) · Step 4

Debug mode: enabled

Effort study: `railspike-effort` / `v3/tag_unlocks`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.60

## Original Query

On ZenMemes, I would like for certain tags to unlock additional tags. Check Pollyanna tagsets for prior art, please.

Yes, so what I'm mainly looking for right now is for meta posts. I want things like "in progress" or "open" or "closed", etc.

If we choose option A, would children of a meta item also have meta tags and the subtags?

https://zenmemes.com/threads/thread-20261109051954-8e24340fcf0d516d00a73c054ce1791e
Just so that we're not suffering from an XY problem, here is what I'm looking at, period. This is the agent accessibility thread, and I want to be able to mark things done or closed or whatever once they are done or closed and not have them crowd the thread, maybe have them collapse the comment.

Anyone who can tag can mark a reply.
Resolved and closed are different.

i think we better limit it to approved keys for now... idk though, the risk is low for now.

## Understood Intent

Revision 1: the target is individual replies, not threads. The [Agent Accessibility](https://zenmemes.com/threads/thread-20261109051954-8e24340fcf0d516d00a73c054ce1791e) thread is a standing `#meta` inbox where each reply reports one problem. The maintainer wants to mark a reply done or closed once it is handled. Handled replies then collapse so open ones stand out. The thread itself stays open. The general wish remains: a tag decides which extra tags people can add.

Revision 2: anyone who can tag may mark a reply, so marking uses the same gate as tagging today (any identity; approval is recorded, not required). Resolved and closed are two distinct states, not synonyms.

Revision 3: for now only approved keys change a reply's state. Following the accept-and-stage principle, the server still stores a resolve or close tag from an unapproved key; it is just not counted. Loosening this later to anyone who can tag only changes which records count.

## Prior Art

- **Pollyanna, built.** For each tag an item already has, `item_label_buttons.pl` looks for a file `tagset/<tag>` and shows its lines as extra buttons. Examples: `#flag` offers reasons (spammy, nsfw, troll), `#event` offers attend/interested/decline, and the hardware theme nests tagsets (`#component` offers cpu, ram, ssd). A `label_parent` table rolls child tags up into the parent's tag page. Tagsets are plain newline lists, and themes can override them.
- **Pollyanna, gaps.** It has no exclusive groups, so "open" and "closed" could both stand. It has no loop or depth check, and every unlocked tag is an independent vote.
- **v3 today.** Thread and post reactions share one allowlist: only `like`, `flag`, `upvote` and `downvote`, plus the QDB vote caption catalog on QDB. Post reactions already exist (Flag on a reply), so a reply can carry its own tags. That catalog already gates extra tags by context. `#meta` arrives as an authored board tag or as a Fastmod effective tag.
- **v3 history.** The removed work tracking gave `#work` threads `open/active/done/dropped` through `status:` replies. Only approved members counted, and the latest valid line won. The planned work page added status buttons that posted those replies.

## Problem Statement

In a running `#meta` thread, handled reports look the same as open ones, so the open ones are hard to find as the thread grows.

## Concepts touched

- All options: [reaction record](../../concepts/reaction_record.md) (Options A and B: used, accepted post tags change), [post presentation](../../concepts/post_presentation.md) (changed: resolved replies collapse).
- Option A: proposed *tagset* (a tag and the tags it unlocks, optionally exclusive).
- Option C: [shared composer](../../concepts/shared_composer.md) (used to post the directive reply).

## Solution Options

### Option A: Tagset file unlocks resolve tags on replies

**Estimate: 0.60**

A small tracked file lists each parent tag and the tags it unlocks, Pollyanna style, with a marker for an exclusive group. The first entry gives `meta` the exclusive group resolved and closed, plus open to undo. In a thread tagged `#meta`, each reply shows Resolved and Closed beside Like and Flag. A tap writes an ordinary signed post reaction. The server accepts these tags only on posts inside a `#meta` thread. The latest one from an approved key sets the reply's state; tags from unapproved keys are stored but not counted. Resolved and closed replies render as one collapsed line (author, date, state, first words), each with its own label, that expands on tap. The page stays readable without JavaScript.

- Pros: one tap per report; the collapse makes open reports stand out; the next unlock needs only a file edit; reuses post reactions.
- Cons: introduces latest-wins semantics into reactions, which today only count; collapsed lines change page layout across sixteen themes; the file format is a new contract.
- Driver: the collapsed reply line in every theme, plus latest-wins resolution in the read model. Uncertainty: phone layout of the collapsed line.

### Option B: Hard-coded Resolve on replies

**Estimate: 0.45**

Add resolved and closed to the accepted post tags in code, show the buttons only in `#meta` threads, and collapse resolved replies the same way.

- Pros: same visible result as A; less to design.
- Cons: the next unlock needs code again; special-cases one tag in generic reaction handling.
- Driver: the collapse, as in A.

### Option C: `resolved:` directive replies

**Estimate: 0.50**

A reply containing a `resolved:` line naming another reply marks it, following the removed work-tracking pattern. A button posts that reply, and marked replies collapse.

- Pros: each resolution sits in the thread with its author and an optional note.
- Cons: every resolution adds a reply to the thread you want to declutter; the note is lost when the directive reply also collapses.
- Driver: directive parsing and the button-to-reply flow.

## Recommendation

**Option A**, shipped with only the `meta` entry. It gives one-tap resolution, keeps the thread short, and builds the general unlock rule you asked about. Option B is the fallback if Step 2 trims scope. Thread-level status from the first draft is dropped; a whole-thread state can reuse the same file later.

Answered:

- Who may mark a reply: approved keys for now. Unapproved keys' marks are kept but don't count, so this can be loosened later.
- Resolved and closed are different states, each with its own label on the collapsed line.

Still open (proposed defaults, adopted unless changed):

- Thread-wide toggle for collapsed replies: none in the first slice; each collapsed reply expands on its own.
- Fastmod's effective `#meta` unlocks the buttons too, since the Agent Accessibility thread's `meta` label should be enough whatever its source.

**Vertical-slice viability:** in Agent Accessibility, an approved key taps Resolved on the image-attach report. That reply collapses to one line marked resolved, and tapping the line expands it. Open reports stay full size. A reply in a thread without `#meta` shows no Resolved button, and the server refuses the tag there. A Resolved tap from an unapproved key leaves the reply open. Verify on phone and desktop across themes.

## Enhancement Requests

- Pollyanna's unbuilt idea (a tag on a person lets them apply other tags) overlaps the filed per-feature permission request. Disposition: noted, out of scope here.
- A whole-thread status (open, in progress, closed) for `#meta` threads. Disposition: the same tagset file could add it in a later cycle.

## Revision history

- v1 (2026-11-11): thread-level status for `#meta` threads; options A 0.60, B 0.45, C 0.35.
- v2 (2026-11-11): the maintainer pointed to the Agent Accessibility thread; scope moved to per-reply resolution with collapse.
- v3 (2026-11-12): anyone who can tag may mark; resolved and closed are distinct states; defaults proposed for the two open questions.
- v4 (2026-11-12): only approved keys change state for now; unapproved marks are stored, not counted.

## Study Snapshots

2026-11-11T06:45:00Z; scope v1; starting revision `6d0f9da1c4c12b3a1107d74543227d167c252417`; reference R1 (all three frozen source hashes verified this session). Claude, single exposed rater. Pollyanna tagsets, v3 reaction handling and the removed work tracking were inspected before enrollment, so no pristine intake is claimed.

- Intake after inspection: **0.60 projected total** for Option A with the `meta` entry.
- Step 1 handoff: **0.60**, Option A. Alternatives B 0.45 and C 0.35, non-additive. Not hours or measured labor; no approval to advance.

2026-11-11T07:55:00Z; Step 1 Revision 1; scope v2 (changed scope: per-reply resolution with collapse, not thread status). Same revision, R1, rater and exposure.

- Projected total **0.60** reaffirmed for Option A: the collapse adds theme work, and dropping thread status removes about the same. Alternatives B 0.45, C 0.50, non-additive.

Status: Step 1 Revision 3 approved (2026-11-12). Planning records uncommitted.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

*Revision 1 — Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

*Revision 2 — Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

*Revision 3 — Claude · 2026-11-12 UTC · Model: claude-opus-5-5*
