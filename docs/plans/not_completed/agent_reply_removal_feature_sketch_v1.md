# Agent Reply Removal Feature Sketch V1

## Goal

Allow an approved user to remove a reply authored by `reply-agent` from normal forum surfaces with a small, unobtrusive control. The user may optionally provide an explanation for the removal.

This sketch intentionally stops before implementation.

## Current Context

- Agent replies are normal canonical post records in `records/posts/`.
- The reply-agent identity is represented by the approved username `reply-agent`.
- Thread pages render posts through `templates/partials/post_card.php`, which already detects agent-authored posts from `author_label === reply-agent`.
- Approved-user state is derived in the read model and is already used to gate user approval and post-analysis visibility.
- Writes go through `LocalWriteService`, are committed to git, refresh read-model state, and invalidate static artifacts.
- There is no existing post deletion or moderation-removal record family.

## Proposed Product Behavior

- Show a very small removal affordance only on posts authored by `reply-agent`.
- Show it only when the current viewer resolves to an approved profile.
- Keep the default thread reading experience quiet:
  - the visible control should be a compact button or `details` summary near the existing post actions
  - the explanation field should stay hidden until the user opens the control
- On submit:
  - require `post_id`
  - accept optional ASCII explanation text
  - require the viewer to be approved
  - require the target post to exist
  - require the target post author to be the canonical reply-agent identity
  - reject attempts to remove ordinary user posts
- After success, redirect back to the thread with a short notice and remove the post from normal display.

## Canonical Data Shape

Use an append-only removal/tombstone record instead of deleting the original post file.

Suggested path:

```text
records/post-removals/<record-id>.txt
```

Suggested record shape:

```text
Record-ID: post-removal-20260506123000-ab12cd34
Created-At: 2026-05-06T12:30:00Z
Post-ID: reply-20260506122900-1234abcd
Removed-By-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954
Reason: Optional short ASCII reason

Optional longer ASCII explanation.
```

Reducer semantics:

- Records are ordered by `Created-At`, then `Record-ID`.
- A valid removal hides the target post from normal read surfaces.
- A valid removal only applies when:
  - the remover identity is approved at the time of the removal
  - the target post exists
  - the target post is authored by `reply-agent`
- Duplicate removal records for the same post are harmless; the first valid one wins for displayed removal metadata.

This preserves auditability and keeps repository rebuilds deterministic.

## Read Model Changes

Add removal-derived state during rebuild:

- Parse `records/post-removals/`.
- Derive a set of valid removed post IDs.
- Exclude removed posts from:
  - thread post lists
  - standalone `/posts/<id>` display, likely returning a removed-post message or 404-equivalent
  - board and activity rows
  - author/profile counts where they currently count visible authored content
  - agent reply generation context so removed agent replies are not included as prior discussion
- Recompute thread summaries after exclusions:
  - `reply_count`
  - `last_post_id`
  - `last_activity_at`

V1 can avoid adding removal metadata to every post row if all normal queries exclude removed posts. A later admin/audit page could expose removal records.

## Write Path

Add a `LocalWriteService::removeAgentReply()` style method that:

- runs under the existing exclusive write lock
- validates the target canonical/read-model post
- validates approved viewer identity
- normalizes optional explanation with existing ASCII body/line rules
- writes the post-removal record
- commits it with a message like `Remove agent reply <post-id>`
- refreshes derived state
- invalidates:
  - `/`
  - target thread page
  - target post page
  - activity artifacts, if applicable

The generated-response row should retain `agent_post_id`. That prevents the existing idempotent agent reply flow from immediately recreating the same reply after removal.

## HTTP/UI Changes

Add a route such as:

```text
POST /posts/<post-id>/remove-agent-reply
```

or:

```text
POST /api/remove_agent_reply
```

For V1, a non-JS form is enough:

- render inside `templates/partials/post_card.php`
- gated by `isAgentPost && viewerIsApproved`
- use a compact `details` disclosure or icon-sized button
- include a textarea named `explanation`
- include a submit button labeled `Remove`

Thread rendering already resolves `viewerProfile`; pass a boolean like `viewerCanRemoveAgentReplies` into the post-card partial.

## Tests To Add

- Approved viewer sees removal control on agent-authored replies.
- Unapproved or anonymous viewer does not see the control.
- Approved viewer can remove an agent-authored reply.
- Removal creates a canonical post-removal record and git commit.
- Removed agent reply disappears from thread page, post page, activity, and thread reply count.
- Removing a user-authored reply is rejected.
- Removing a missing post is rejected.
- Optional explanation is preserved in the canonical record.
- Removed agent reply is not regenerated for the same target/content hash.
- Fresh rebuild matches the immediate post-write read model.

## Open Approval Points

- Should removed post permalinks return 404, or a small "removed agent reply" page?
- Should the explanation be visible anywhere in V1, or only preserved canonically?
- Should the route be form-first (`/posts/<id>/remove-agent-reply`) or API-first (`/api/remove_agent_reply`)?
- Should any approved user be allowed to remove any agent reply, or should this be limited to admins/seed-approved users?
