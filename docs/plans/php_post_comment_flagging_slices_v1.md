# PHP Post And Comment Flagging Slices V1

This document outlines an implementation plan for letting approved users flag posts and comments, with automatic hiding for flagged `reply-agent` content. The feature should reuse the same canonical write, browser identity, approval, scoring, incremental read-model, and UI infrastructure as the existing `Like` feature.

## Goal

Approved users can flag any visible post or comment. A flag is an append-only canonical reaction record. The first approved flag against a post authored by `reply-agent` hides that post automatically from normal thread, post, activity, and API surfaces.

The implementation should preserve the current `Like` behavior:

- browser-side identity bootstrap happens in place
- one author can apply a given reaction to a target at most once
- only approved identities affect moderation/scoring outcomes
- canonical records remain the source of truth
- incremental read-model refresh is the hot path, with rebuild as the repair path

## Current Context

The repo already has:

- `TagScore` with scored tags for `like` and `flag`
- `POST /api/apply_thread_tag`
- `LocalWriteService::applyThreadTag()`
- canonical `records/thread-labels/*.txt`
- `ThreadLabelRecordParser`
- read-model reducers in `ReadModelBuilder` and `IncrementalReadModelUpdater`
- `public/assets/thread_reactions.js`, which handles browser identity setup and reaction submission
- thread UI that renders a `Like` button from `templates/pages/thread.php`
- post cards in `templates/partials/post_card.php`, including `data-agent-authored="reply-agent"`

Important constraint: `thread-label` V1 explicitly targets root threads only. `LocalWriteService::applyThreadTag()` rejects non-root targets, and both read-model reducers ignore thread-label records that do not target a known root thread. Post/comment flagging needs a targetable content reaction layer rather than simply sending reply IDs to the current endpoint.

## Product Semantics

- A post or comment can be flagged by an approved user.
- A non-approved user may create a canonical flag record, matching the current like/tag write behavior, but that flag does not count toward hiding or moderation state until the identity becomes approved.
- Duplicate flags from the same identity to the same target are idempotent.
- `reply-agent` content is automatically hidden once it has at least one approved `flag`.
- Human-authored content is not automatically hidden in V1. It records the flag and exposes moderation state for later tooling.
- Hidden posts remain canonical and recoverable in the repository/read model; hiding is a presentation/API filtering decision.
- The UI should say `Flag` before submission and `Flagged` after submission.

## Recommended Data Model

Add a sibling record family instead of overloading `thread-label`:

- canonical path: `records/post-reactions/<record-id>.txt`
- record ID prefix: `post-reaction`
- required headers:
  - `Record-ID`
  - `Created-At`
  - `Post-ID`
  - `Operation: add`
  - `Tags`
- optional headers:
  - `Author-Identity-ID`
  - `Reason`

The parser and reducer should intentionally mirror `ThreadLabelRecordParser` and the thread-label reducers. In V1, only scored tags from `TagScore` should be accepted through the public write endpoint, with UI exposure limited to `flag`.

Rationale: this keeps `thread-label` semantics stable for root-thread labels while reusing the same infrastructure shape for post/comment reactions.

## Read Model Changes

Add per-post moderation/reaction fields to `posts`:

- `post_tags_json TEXT NOT NULL DEFAULT '[]'`
- `post_score_total INTEGER NOT NULL DEFAULT 0`
- `approved_flag_count INTEGER NOT NULL DEFAULT 0`
- `is_hidden INTEGER NOT NULL DEFAULT 0`
- `hidden_reason TEXT NULL`

Reducer rules:

- Load all post-reaction records ordered by `Created-At`, then `Record-ID`.
- Ignore records targeting unknown posts.
- Collapse duplicate tags within one record.
- Maintain unique visible tag list per post.
- Count scored tags only when `Author-Identity-ID` belongs to an approved profile.
- Dedupe score contribution by `author_identity_id + tag + post_id`, matching the thread-like behavior.
- `approved_flag_count` is the number of distinct approved identities that flagged the post.
- `is_hidden = 1` only when `approved_flag_count > 0` and `author_label = 'reply-agent'`.
- `hidden_reason = 'approved_flagged_reply_agent'` for that automatic hide.

Incremental refresh should have an `applyPostReactionWrite($postId, $commitSha)` path analogous to `applyThreadLabelWrite()`.

## Backend API

Add:

```text
POST /api/apply_post_tag
```

Request:

```text
post_id=<id>
tag=flag
```

Server behavior:

- Resolve the viewer with `resolveViewerProfileFromIdentityHint()`, matching `handleApplyThreadTag()`.
- Inject `author_identity_id` from the resolved viewer profile.
- Call `LocalWriteService::applyPostTag()`.
- Return plain text in the same style as `/api/apply_thread_tag`:
  - `status=ok`
  - `post_id=...`
  - `tag=flag`
  - `post_score_total=...`
  - `approved_flag_count=...`
  - `is_hidden=yes|no`
  - `viewer_identity_id=...`
  - `viewer_is_approved=yes|no`
  - `wrote_record=yes|no`
  - optional `commit_sha=...`

`LocalWriteService::applyPostTag()` should mirror `applyThreadTag()`:

- validate target post exists
- normalize tag through `TagScore`
- require browser OpenPGP identity
- check duplicate reaction from same identity
- write canonical record
- commit it
- run incremental post-reaction refresh
- invalidate the target post artifact, its thread artifact, and board/activity artifacts affected by hiding

## UI Changes

Generalize `public/assets/thread_reactions.js` into a reusable reaction script or add a small post-reaction module that shares the same helper functions:

- identity setup through `window.__forumBrowserIdentity.ensureReadyIdentity`
- technical feedback details
- URL-encoded `POST`
- idempotent success handling

In `templates/partials/post_card.php`:

- Render a `Flag` button for each visible post/comment.
- Add `data-post-reactions-root` or put the post ID on the button.
- Disable/render `Flagged` when the current viewer already has a flag on that post.
- Reuse the existing feedback pattern near post actions.
- If an approved flag hides a `reply-agent` post, immediately hide or collapse the card after the API response says `is_hidden=yes`.

Thread-level `Like` should continue to work unchanged. Avoid renaming request/response fields in the existing endpoint unless the compatibility tests are updated deliberately.

## Hiding Surfaces

Filter `is_hidden = 1` from normal user-facing reads:

- thread pages
- direct `/posts/<id>` pages
- activity page
- `/api/get_thread`
- `/api/get_post`
- static artifact generation for those surfaces

For direct post URLs, prefer a small message page such as `This post has been hidden.` rather than a 404, because the canonical post still exists. Internal moderation/admin tooling can later add a way to inspect hidden content.

When hiding a reply-agent post, do not decrement canonical thread reply counts in V1 unless the product explicitly wants hidden replies to disappear from counts. The lower-risk first version is to keep counts canonical and filter rendered rows.

## Implementation Slices

### Slice 1: Canonical Record Family

Status: implemented.

- Add `PostReactionRecord` and `PostReactionRecordParser`.
- Add `CanonicalPathResolver::postReaction()`.
- Add repository loader support.
- Write parser tests for required headers, invalid tags, duplicate tag collapse, author identity validation, and path/ID matching.
- Add `docs/specs/post_reaction_record_v1.md`.

### Slice 2: Full Rebuild Reducer

Status: implemented.

- Extend the read-model schema with post reaction/hide columns.
- Index post-reaction records during rebuild.
- Apply approved-identity scoring and duplicate suppression.
- Mark flagged reply-agent posts hidden.
- Add rebuild tests covering:
  - approved flag hides reply-agent content
  - unapproved flag records but does not hide
  - duplicate approved flag counts once
  - human-authored flagged post remains visible

### Slice 3: Incremental Write Path

Status: implemented.

- Add `IncrementalReadModelUpdater::applyPostReactionWrite()`.
- Add `LocalWriteService::applyPostTag()`.
- Preserve rebuild fallback/stale-marker behavior from existing write flows.
- Invalidate affected artifacts after commit.
- Add parity tests comparing incremental post-reaction updates to a fresh rebuild.

### Slice 4: API And Browser Flow

Status: implemented.

- Add `/api/apply_post_tag`.
- Reuse browser identity setup from the like flow.
- Add post-card flag buttons and feedback.
- Track `viewerHasPostTag()` for initial `Flagged` rendering.
- Add smoke coverage for successful flag, duplicate flag, missing identity hint, and unapproved viewer behavior.

### Slice 5: Hidden Content Filtering

Status: implemented.

- Filter hidden posts from thread rendering and public APIs.
- Handle direct hidden-post URLs with an explicit hidden message.
- Ensure agent-generated reply lookup and `created_post_id` highlighting tolerate a post that was hidden after creation.
- Add tests for thread page, post page, activity page, and API filtering.

## Open Decisions

- Should hidden replies affect visible reply counts, or should counts remain canonical in V1?
- Should approved flags on human-authored posts affect thread score, only post score, or both?
- Should there be an approved-user-only server-side rejection for flag writes, or should V1 preserve the current like behavior where unapproved records are written but do not count?
- Should hidden reply-agent posts be fully removed from static artifacts or rendered as collapsed placeholders?

## Acceptance Criteria

- An approved user can flag a post or comment from the browser without pre-configuring identity.
- The flag write creates a canonical append-only reaction record.
- Repeating the same flag by the same identity is idempotent.
- An approved flag on a `reply-agent` post hides that post on thread pages and public APIs.
- An unapproved flag does not hide content until approval-derived state is refreshed.
- Existing thread `Like` behavior and tests continue to pass.
- Full rebuild and incremental update produce equivalent read-model state for post reactions.
