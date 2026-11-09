> **Feature plan:** [Step 1](./accept_and_stage_step1_solution_assessment.md) · [Step 2](./accept_and_stage_step2_feature_description.md) · [Step 3](./accept_and_stage_step3_development_plan.md) · [Step 4](./accept_and_stage_step4_implementation_summary.md)

# Step 1: Solution Assessment — Accept and stage

## Original Query

This is nice, and we still don't want to reject the post, ideally, but stage it or something. We want to avoid rejecting incoming submissions as much as possible.

(Follow-up on who sees staged submissions:) You have the right idea. Only authors and operators.

## Understood Intent

The query responds to how signed tag votes handle clock skew today. A vote whose `Created-At` is more than 5 minutes off is not stored. The server returns a re-stamped record and a prepare token, and the client must sign again. Signed posts outside a 10-minute window, posts that break the text rules, records whose bytes are not canonical, replies or tags whose target has not arrived, and signatures from unregistered keys are all refused outright.

A signed submission is evidence of what its author meant even when something else about it is wrong. The server cannot edit signed bytes, but it can keep them. The goal is to keep almost every submission and tell the client what would publish it, and to reject only when keeping the bytes would itself cause harm: oversize payloads, rate-limit overflow, and input that does not parse at all. Only the author and operators see a staged submission.

## Problem Statement

The server throws away signed submissions for problems the author could fix or that time would resolve, so work is lost and every client has to get each byte right on the first try.

## Concepts touched

- Signed post submission (`create_signed_post`): changed in every option.
- Signed reaction submission and the clock-skew re-stamp: changed in every option.
- Canonical record formats and the legacy reaction adapter: used unchanged in Option A as the model for storing exact bytes and reading them leniently.
- Staging store (new): introduced in Option B; stored inside the records tree in Option C.
- Post revision (`post_revision`, backlogged): a likely way to resolve staged text-rule problems in Options B and C.
- Agent client (`frm.py`): learns a third outcome, `Staged:`, in Options B and C.

## Option A — Accept more, stage nothing

Widen what is accepted and leave everything else as it is. A signed record with a skewed clock is accepted, with the server's receive time kept in a sidecar outside the signed bytes and used for ordering when the two disagree a lot. Signed records whose bytes are valid but not canonical are stored exactly as signed and read leniently, the way old browser reaction layouts already are. Text-rule problems, missing targets and unknown keys are still rejected.

- Pro: small, and it removes the most surprising rejections, including the re-sign round trip on skew.
- Pro: no new place for data to live.
- Con: the other rejection causes remain, and those are the ones where an author loses a whole post.
- Con: canonical form becomes "what the server writes" instead of "what the server accepts," which every reader has to respect.

## Option B — Option A plus a private staging store

Everything in Option A, plus a staging area outside the records tree for submissions that cannot publish yet. Each staged item keeps its exact bytes and signature, a reason, and what would publish it: a signed fix-up for a text-rule problem, the target thread arriving, or the key being registered. The server answers `published`, `staged` or `rejected`. An exact retry returns the current status, so a retry never creates a duplicate. The author sees their staged items, and operators see all of them; nobody else does. Staged items never reach the read model. Per-key quotas and expiry keep the store from becoming a spam target.

- Pro: almost nothing a person signs is lost, and every refusal becomes a next step.
- Pro: a reply or vote from an offline client can arrive before its thread and still land.
- Pro: keeping staged items outside the records tree keeps them out of public exports and history, which author-only visibility requires.
- Con: a new store needs lifecycle rules (resolve, expire, discard), and operators need a view.
- Con: more states for clients to handle, though `frm.py` already separates `Rejected:` from `Resume:`.

## Option C — Stage inside the records tree

Like Option B, but staged submissions are written into the normal records tree, marked as staged, and the read model hides them from everyone except the author and operators.

- Pro: one storage path, one history, and nothing new to back up.
- Con: the records tree is the public, exportable history. Staged content would leak through clones, exports and Git history, which breaks author-and-operator-only visibility.
- Con: every reader and every export has to learn the staged state, so a mistake anywhere exposes staged content.

## Recommendation

Option B. Only B keeps nearly everything while keeping staged content private, as the query asks. The vertical slice is Option A's skew and canonical-bytes acceptance, plus the staging store with one reason, text-rule violations, resolved by the author's signed replacement. That slice includes the `staged` response, the author view, the operator view and `frm.py`'s `Staged:` line. Missing targets, unregistered keys, quotas and expiry follow as later slices on the same store.

*— Claude · 2026-11-09 UTC · Model: Claude Opus 5.5 (claude-opus-5-5)*
