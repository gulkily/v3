> **Feature plan:** [Step 1](./prompt_plain_text_and_revise_step1_solution_assessment.md) · [Step 2](./prompt_plain_text_and_revise_step2_feature_description.md) · [Step 3](./prompt_plain_text_and_revise_step3_development_plan.md) · [Step 4](./prompt_plain_text_and_revise_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./prompt_plain_text_and_revise_debug_log.md))

Effort study: `railspike-effort` / `v3/prompt_plain_text_and_revise`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Follows `v3/prompt_copy_quiet`.

Extended estimates: enabled

Status: Step 1 approved by the maintainer 2026-11-16 (Option B). His reply answered the three decisions the handoff asked for ("1: B", "2: agree, author's version (later we can promote replies to top level separately)", "3: yes to \"show all\"") without the words "Approved Step 1"; it was read as the approval, and the Step 2 handoff says so. Backlogged 2026-11-16 with Step 2 unapproved; see [Step 2](./prompt_plain_text_and_revise_step2_feature_description.md).

**Cycle estimate: 0.5** (recommended scope, Option B)

# Step 1: Solution Assessment — Prompts as plain text, and revising a prompt

## Original Query

Let us adjust how the prompts page works. In the listing page, there are too many textareas, there should be not this many textareas, they are distracting. I'm brainstorming still, but I don't even know where we need a textarea, except when replying. It should just be selectable text and a copy button in the corner. For the threads page, there should be an extra button to edit the prompt, and that should turn the reply form into an editor for the prompt (is that any different than just editing) and it should pre-populate it with the version of the prompt I clicked the button on.

## Understood Intent

Two changes, one settled and one open.

Settled: on the Prompts page (`/agent/`), a prompt is shown as ordinary selectable text with the Copy button in its corner. No read-only text fields anywhere on the page, including the site's own three prompts. A text field appears only where someone writes.

Open: on a prompt's thread page, an Edit button on a version of the prompt opens the reply form filled with that version's text, ready to change and post.

The question in the request, "is that any different than just editing": yes, on this site it is. A post here is a signed text file in the Git repository and is never changed after it is written; there is no edit-in-place for anything (the `post_revision` idea was shelved at Step 1). So this Edit can only produce a new post that carries the changed prompt and leaves the old one where it was. That also means anyone can press it, not just the author, and it makes "which version is the prompt?" a real question. The options below differ on exactly that.

## Problem

The Prompts page reads as a wall of form fields, and a shared prompt cannot be improved without retyping it, nor can anyone tell which of several versions in a thread is the current one.

## What every option shares

The listing change is the same in all three and can ship first on its own: prompt text as selectable text in a quiet box, Copy in the top right corner (same button, same check after copying), long prompts cut off after a set height with a "Show all" control instead of a scroll box inside the page. Copy works without selecting; selecting works without script.

## Options

### Option A — Edit is a filled-in reply; the first post stays the prompt

**Estimate: 0.3**

The thread's first post gets an Edit button. Pressing it opens the reply form with the prompt's text in it; posting makes an ordinary reply. The Prompts page keeps showing the first post. Driver: the listing rework. Uncertainty: low.

- Smallest change; nothing new to learn or store.
- A reply holding a revised prompt looks like any other reply. It has no Copy or Edit of its own, so "the version I clicked on" can only ever be the first post.
- An improved prompt never reaches the Prompts page unless someone reposts it as a new thread.

### Option B — Edit posts a marked version; the author's newest version is the prompt

**Estimate: 0.5**

Edit opens the reply form as a prompt editor, filled with the version it was pressed on. What it posts is a reply marked as a version of the prompt. Every version in the thread, the first post included, shows as a prompt: selectable text, Copy and Edit. The Prompts page shows the newest version written by the prompt's author; versions by other people stay in the thread as suggestions the author can take up by pressing Edit on them and posting. Likes, replies and the title stay with the thread. Driver: marking versions and picking the current one for the listing. Uncertainty: medium, in how the mark is written (a hashtag such as `#version` in the reply is the existing mechanism for labeling by reply) and in showing "edited" on the card.

- Matches the request as written: Edit on any version, filled with that version.
- Every version is kept and visible, in keeping with posts never changing; the thread is the prompt's history.
- One thread per prompt, so likes are not split across copies.
- The author stays in charge of what the Prompts page shows under their name.
- Needs a rule for "current version" and a visible mark on version replies.

### Option C — Edit starts a new prompt thread linked to the old one

**Estimate: 0.45**

Edit opens the new-thread form filled with the prompt's title and text, tagged `#prompt`, with a line linking back to the prompt it came from. The result is its own prompt with its own likes and its own card. Driver: the link back and showing "based on" on cards. Uncertainty: medium, in how crowded the Prompts page gets.

- No rule for "current version" needed; each prompt stands alone and Top sorting picks winners.
- The Prompts page fills with near-duplicates, and likes on the original do not carry over.
- Does not use the reply form, which the request asks for.

## Recommendation

Option B, in three slices, each shippable alone:

1. Prompts page: plain text with Copy in the corner, no text fields (shared by all options).
2. Thread page: Edit on the prompt opens the reply form filled in and posts a marked version; versions show with Copy and Edit.
3. Prompts page shows the author's newest version, with a small "edited" note linking to the thread.

Two choices to make when approving:

- Whose version counts as current on the Prompts page: the prompt's author only (recommended), or any approved member's newest version.
- Long prompts on the Prompts page: cut off with "Show all" (recommended), or always shown in full.

## Concepts touched

- Prompt (a thread tagged `#prompt`): changed in B (gains versions and a current version); used unchanged in A; C adds "based on" between prompts.
- Copyable prompt field: changed in all options (text, not a field).
- Reply form: used in A and B, with a prompt-editor mode in B; C uses the new-thread form instead.
- Reply-carried labels (hashtags in a reply): used in B for the version mark.
- Post immutability: used unchanged; it is why Edit makes a new post.

## Decisions (maintainer, 2026-11-16)

- Option B.
- The current version on the Prompts page is the author's newest version only.
- Long prompts on the Prompts page are cut off with "Show all".

## Revised direction (2026-11-16)

After Step 2 was drafted the maintainer split the work: "let us split off the communal editing part into a cycle and one-shot the visual improvements?" The listing change every option shares (slice 1 of the recommendation) is now the one-shot [prompt_plain_text](../prompt_plain_text/prompt_plain_text_one_shot.md). This cycle continues as Option B's versions and Edit (slices 2 and 3). The assessment above is kept as written.

## Enhancement Requests

- **Request:** "later we can promote replies to top level separately" (with decision 2): a way to make someone else's version in a thread the one that counts, or a prompt of its own. **Disposition:** separate work, after this cycle. **Approval:** the maintainer raised it as later work; not started.

Attribution: Claude (claude-fable-5-2), 2026-11-16 UTC.
