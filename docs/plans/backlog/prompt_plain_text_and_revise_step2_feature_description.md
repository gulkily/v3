> **Feature plan:** [Step 1](./prompt_plain_text_and_revise_step1_solution_assessment.md) · [Step 2](./prompt_plain_text_and_revise_step2_feature_description.md) · [Step 3](./prompt_plain_text_and_revise_step3_development_plan.md) · [Step 4](./prompt_plain_text_and_revise_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./prompt_plain_text_and_revise_debug_log.md))

Effort study: `railspike-effort` / `v3/prompt_plain_text_and_revise`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Follows `v3/prompt_copy_quiet`.

Extended estimates: enabled

Status: **backlogged 2026-11-16 at the maintainer's request** ("save this for the backlog please"). Step 1 approved (Option B, author's version). Step 2 drafted and revised to scope v2 but not approved; nothing is implemented. To resume: review this step, approve or revise it, then Step 3. The visual half shipped as the one-shot [prompt_plain_text](../prompt_plain_text/prompt_plain_text_one_shot.md) (PR #277, merged), which this builds on.

**Cycle estimate: 0.4** (scope v2)

# Step 2: Feature Description — Revising a prompt (versions and Edit)

Scope v2, 2026-11-16, at the maintainer's request ("let us split off the communal editing part into a cycle and one-shot the visual improvements?"): the Prompts page's plain-text display, Copy and "Show all" left this cycle for the one-shot [prompt_plain_text](../prompt_plain_text/prompt_plain_text_one_shot.md). This cycle is versions and Edit, and builds on that one-shot's prompt display. The cycle keeps its identifier.

## Problem

A shared prompt cannot be improved without retyping it, and a thread gives no way to tell which text is the current prompt.

Approach (Step 1): Option B. Edit posts a reply marked as a version. Every version stays in the thread. The Prompts page shows the newest version written by the prompt's author.

## User stories

- As a prompt's author, I want to press Edit on my prompt, change it and post so that the Prompts page shows my improved text under the same title, likes and thread.
- As a member, I want to press Edit on any version and post my change so that I can suggest an improvement without retyping the prompt.
- As a reader of a prompt's thread, I want every version shown as a prompt with its own Copy so that I can take whichever one I prefer.
- As an agent, or anyone typing by hand, I want to post a version by writing a reply with one marker line so that every client can do it.

## Core requirements

- On the thread page of a `#prompt` thread, the opening post and every version show as a prompt (the same box and Copy) with an Edit button. Edit opens the page's reply form as a prompt editor, filled with that version's text. Posting it creates a reply to that version, marked as a version.
- The mark is a line the editor writes for you: a reply whose first line is `prompt:` and nothing else is a version, and everything under that line is the prompt. Copy and Edit take the text under the line. In a thread that is not a prompt, the line is plain text.
- The Prompts page shows, for each prompt, the newest version posted by the author of the opening post, or the opening post when there is none. A card showing a later version says "edited" with a link to the thread. Search matches the text shown. Title, tags, likes and replies stay the thread's.

## Smaller rules

- Anyone who can reply can press Edit and post a version. Only the opening post's author changes what the Prompts page shows. Other people's versions stay in the thread; the author takes one up by pressing Edit on it and posting.
- A version counts for the Prompts page only when its author is named and verified, as a pledge must be.
- Retracting a version removes it. If it was the current one, the Prompts page goes back to the author's previous version.
- Leaving the editor (Cancel, or closing the reply form) returns the reply form to an ordinary reply with its own draft untouched.
- A version reply with nothing under the marker line is an ordinary reply.
- Quoted lines are skipped when looking for the marker, as for `theme:` and `pledge:`.
- The reply's own page, paned layouts and text output show a version as written, marker line included.
- Posts are still never changed. Nothing here edits a post in place.

## Delivery scope

Work type: application change.

Two slices, each a working page on its own: (1) versions and Edit on the thread page; (2) the Prompts page shows the author's newest version.

Not in this cycle: the Prompts page's plain-text display (the one-shot above); promoting someone else's version to be the current one or a prompt of its own (recorded below); a side-by-side comparison of versions; changing a prompt's title or tags through Edit; version counts on cards; Edit on the Prompts page itself; the Waiting list (it lists titles only).

## Completion boundary

- Entry: a prompt's title or Discuss link on the Prompts page for its thread; Edit on a version there.
- End-to-end outcome: Ilya opens his prompt's thread, presses Edit, changes a sentence and posts. The thread shows the new version as a prompt under the first. The Prompts page shows the new text under the same title and like count, marked "edited".
- Recovery: a version that fails to send or is written offline follows the reply form's existing path (the failure shows on the form and the reply waits in the Outbox). A mistaken version is retracted from its own page and the earlier text returns. An operator turns the agent page flag off to remove the Prompts page; version replies then read as ordinary replies.
- Release: on zenmemes.com, from his iPhone SE, Ilya edits one of his own prompts from its thread and sees the Prompts page show the edit.

## Risks

- **"Same author" across keys.** Impact: an author who edits with a second key under the same username may not be recognised, and the Prompts page keeps the old text. Validate early: check what the thread's rows carry as the author for two keys under one username before Step 3. Mitigation: use the rule Retract and pledge withdrawal already use, and state the limit if it is per key.
- **The reply form as an editor on a phone.** Impact: a long prompt in a small reply box is hard to edit. Validate early: try a 40-line prompt in the reply form on a 375px screen. Mitigation: the editor mode lets the box grow with its text; nothing else about the form changes.
- **Losing a reply in progress.** Impact: pressing Edit replaces what someone had typed in the reply form. Validate early: a browser test that types a reply, presses Edit, cancels, and finds the reply back. Mitigation: the editor keeps the ordinary draft aside and restores it.

## Shared component inventory

- Copyable prompt (`templates/partials/agent_prompt.php`, its script in `templates/pages/agent.php`, `public/assets/agent.css`), as the one-shot leaves it: used unchanged on the Prompts page and reused on the thread page rather than a second prompt display. Its script moves to where both pages can load it.
- Prompt library query (`src/ForumRewrite/ReadModel/PromptLibrary.php`) and `AgentPageController`: extended to pick the current version.
- Line readers (`src/ForumRewrite/Support/ThemeDirective.php`, `PledgeDirective.php`, `WithdrawDirective.php`): pattern reused for one new reader of the `prompt:` line.
- Thread page posts (`templates/partials/thread_root_card.php`, `post_card.php`, `templates/pages/thread.php`): extended to show a prompt or version through the copyable prompt partial.
- Reply form (`templates/partials/reply_form.php`, the inline composer in `thread.php`, the [shared composer](../../concepts/shared_composer.md), signed and Outbox paths): used with one addition, a prompt-editor mode that fills the box and sets the reply's parent. Sending is unchanged.
- Retraction and leaf filtering (`RetractedLeafFilter`): used unchanged for removing a version.
- Feature flag: the existing agent page flag. No new flag.

New: one line reader and the editor mode.

Concepts touched: proposed new entry, **prompt** (a `#prompt` thread, its versions and its current version); [shared composer](../../concepts/shared_composer.md), changed (editor mode); [post presentation](../../concepts/post_presentation.md), changed (a prompt or version shows as copyable text); [post body formatting](../../concepts/post_body_formatting.md) and [post retraction](../../concepts/post_retraction.md), used unchanged. The [retraction handoff](../retraction_simplification_handoff.md) applies only in that retraction is used as it stands.

No matching item exists in `docs/feedback/items/`.

## Simple user flow

1. Mara opens Prompts and finds a prompt she wants to improve.
2. She follows its title to the thread. The opening post shows the prompt with Copy and Edit.
3. She presses Edit. The reply form opens holding the prompt. She tightens a paragraph and posts.
4. Her version appears in the thread as a prompt with its own Copy and Edit. The Prompts page still shows the author's text.
5. Ilya, the author, presses Edit on Mara's version, adjusts a word and posts.
6. The Prompts page now shows Ilya's new text, marked "edited", with the same title and likes.

## Success criteria

- Copy on a version puts the prompt on the clipboard and never the marker line.
- Editing a prompt from its thread takes one press to open and one to post, with no typed syntax.
- After the author posts a version, the Prompts page shows that text; after they retract it, the page shows the one before.
- A version by anyone else never changes the Prompts page.
- A reply typed before pressing Edit is still there after cancelling.

## Enhancement requests

- **Request:** "later we can promote replies to top level separately" (maintainer, with the Step 1 decisions). **Disposition:** separate work after this cycle. **Approval:** raised by the maintainer as later work; not started.

## Study snapshots

- Step 2 handoff: 0.5 → **0.55** projected total. New understanding, same scope v1: the marker line and keeping it out of Copy and Edit, the editor setting aside a reply in progress, and one prompt display shared by two pages. Baseline `65b98d0`, R1, P2; Claude single exposed rater, 2026-11-16 UTC. R1 hashes not re-verified in this session. Intake and Step 1 values stay as recorded in Step 1.
- Step 2 revision, scope v2, 2026-11-16T09:50Z: 0.55 → **0.4**. The listing display left for a one-shot; versions, Edit and the current version remain. Same rater.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*

*— Revised by Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
