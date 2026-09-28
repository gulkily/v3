# Tag Scoring Outline v1

## Goal

Add support for tags that carry score, while keeping the existing tag system intact.

Initial scored tags:
- `like => +1`
- `flag => -100`

Behavior:
- Approved users can apply the same tags as today, and their scored tags affect score.
- Unapproved users can also apply the same tags, but their scored tags do not affect score.
- Combined, this allows items to accumulate score without blocking general tag participation.

This outline also covers one-click tag buttons. The desired interaction is in-place update with no full page reload, similar to a typical upvote button.

## Current State

The current system already has:
- thread label records written from hashtags in post bodies
- author identity attached to thread-label records when available
- approved/unapproved user state in the read model
- thread label activity in the read model and UI
- tag browsing pages and tag detail pages

That means the feature can build on existing canonical records and read-model derivation instead of requiring a brand-new data model.

## Chosen Product Semantics

### 1. Scoring target

Chosen:
- score threads, not individual posts

Why:
- current label system is thread-based
- lower implementation risk
- matches current canonical thread-label flow

### 2. Scored tags are configured server-side

Introduce a small config map such as:

```php
[
    'like' => 1,
    'flag' => -100,
]
```

This means:
- `like` is still a normal tag and contributes `+1`
- `flag` is still a normal tag and contributes `-100`
- all other tags remain non-scoring

### 3. Canonical writes stay tag-first

Users continue to apply tags in the same conceptual way:
- hashtags in compose text
- later, one-click tag buttons

We do not need users to write numeric scores directly.

The canonical record remains about:
- which thread
- which tags
- who applied them
- when

Score is derived later from that data.

### 4. Approval rule

Chosen:
- approved users' scored tags affect score
- unapproved users' scored tags remain visible as tags but do not affect score

This preserves open participation while reserving weighted score for approved users.

### 5. Deduplication

Chosen:
- count at most once per approved identity per thread per scored tag

Example:
- approved user applies `like` three times to the same thread
- only one `+1` is counted

This avoids trivial inflation and matches reaction/vote semantics better than repeated counting.

### 6. One-click buttons

Chosen initial scope:
- one-click buttons for scored tags only
- thread page only at first

Initial button set:
- `Like`

Likely later expansion:
- `Flag`

### 7. Score display

Chosen initial scope:
- show score as a total only
- no score breakdown in the first version

### 8. Interaction model

Chosen:
- one-click buttons should update in place with no full page reload

This means the UI should behave like a typical vote/reaction control:
- click action
- send request in background
- update button state inline
- update displayed score inline
- avoid full page navigation

Recommended first-pass behavior:
- optimistic UI is optional
- but final success state should update without reload
- failure should restore prior UI state and show a compact inline error

## Proposed Technical Model

### 1. Score is derived in the read model

During rebuild and incremental update:
- collect tag applications by thread
- check whether each tag is scored
- check whether the applying identity is approved
- if approved, apply that tag's numeric value to the thread score
- if unapproved, keep the tag visible but do not apply score

Likely derived state:
- thread total score
- optionally a per-tag score breakdown later
- optionally per-identity dedupe tracking during rebuild

### 2. Tags remain visible even when they do not score

Chosen behavior:
- unapproved users can visibly apply `like` and `flag`
- those tags appear in the thread tag set
- score does not change

### 3. One-click actions reuse the same tag machinery

Preferred design:
- one-click buttons ultimately write the same kind of thread-label record as hashtags
- no separate vote-only canonical model unless later proven necessary

That keeps:
- hashtags
- explicit tag actions
- scoring

all aligned around the same source of truth.

## Suggested Slices

### Slice 1: Scoring backend

Add read-model scoring without changing how tags are written.

Scope:
- scored-tag config
- rebuild logic to derive score
- incremental update logic to derive score
- store score in thread read model
- render thread score on board/thread/tag pages

Result:
- hashtags like `#like` and `#flag` already work
- approved users affect score
- unapproved users do not

### Slice 2: Explicit tag-apply write path

Add a direct action for applying a tag to a thread without requiring body text.

Scope:
- write API or POST route for "apply tag"
- reuse existing canonical thread-label record generation
- same approval/scoring behavior as hashtag path
- return a compact machine-readable result suitable for in-place UI updates

Suggested response fields:
- `status`
- `thread_id`
- `tag`
- `score_total`
- optionally `viewer_applied`

Result:
- UI can trigger tag application directly
- backend works for both full-page forms and JavaScript interactions

### Slice 3: One-click buttons

Add buttons that apply selected scored tags immediately.

Chosen first target:
- `Like` button on thread page

Scope:
- render `Like` button on thread page
- attach client-side handler
- call explicit tag-apply endpoint
- update score text inline on success
- update button state inline on success
- avoid navigation/reload

Later extension:
- add `Flag` button after the base interaction is stable

### Slice 4: Polish

Possible follow-up:
- richer button state such as "already liked"
- stronger idempotence guarantees in UI
- score breakdown display
- more in-place scored-tag buttons such as `Flag`
- board-page or post-page scored-tag buttons if still desired later

## UI Notes

### Board page

Possible additions:
- show `Score: N` on thread cards

### Thread page

Planned first target:
- show `Score: N`
- show `Like` button
- update both inline without reload

Likely later additions:
- `Flag` button
- clearer applied/disabled state

### Tag pages

Possible additions:
- show score next to each thread in tag listings

## Implementation Notes

### Read model

Likely read-model changes:
- add a score column to `threads`
- optionally add scored-tag breakdown later if needed
- optionally add viewer-applied state later if UI needs richer rendering without extra work client-side

### Rebuild path

Extend thread-label indexing to:
- resolve author approval state
- accumulate scored contributions
- dedupe per approved identity per scored tag per thread

### Incremental path

Need parity with full rebuild.

If incremental complexity gets too high for first pass:
- acceptable temporary fallback is full rebuild on scored-tag writes

### Explicit tag-apply endpoint

The one-click button requirement means we likely need a dedicated endpoint rather than relying only on compose-body hashtags.

Preferred behavior:
- client sends thread/tag action in background
- server writes canonical thread-label record
- server refreshes derived state
- server returns updated score and relevant UI state

### Backward compatibility

Existing tags and pages should continue to work:
- ordinary labels remain ordinary labels
- existing thread-label records remain valid
- old hashtag-based application continues to work
- only configured scored tags affect score

## Confirmed Decisions

These are now assumed unless changed later.

1. Score attaches to threads only.
2. Scored tags count once per approved identity per thread per scored tag.
3. Initial scored tags are:
   - `like => +1`
   - `flag => -100`
4. Unapproved users' scored tags remain visibly listed as tags but do not affect score.
5. One-click buttons appear first on thread pages only.
6. One-click buttons are for scored tags only at first.
7. Score is shown as a total only at first.
8. Implementation should be sliced.
9. Ideal button behavior is in-place update with no full page reload.

## Recommended Implementation Order

1. Add scored-tag config for `like` and `flag`.
2. Add thread-level score derivation in the read model.
3. Render score total on board/thread/tag pages.
4. Add explicit tag-apply backend for scored tags.
5. Add thread-page `Like` button with in-place update.
6. Add `Flag` button after the `Like` flow is stable.
