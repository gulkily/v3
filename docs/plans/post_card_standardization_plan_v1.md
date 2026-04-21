# Post Card Standardization Plan v1

## Purpose

This plan standardizes thread/post display cards so the site stops duplicating similar markup across page templates.

Goals:
- reduce repeated card markup
- keep rendering behavior consistent across board, tag, thread, and post pages
- make future UI changes cheaper
- separate shared card structure from page-specific composition

## Current State

Current duplicated rendering surfaces:
- [templates/pages/board.php](/home/wsl/v3/templates/pages/board.php)
- [templates/pages/tag.php](/home/wsl/v3/templates/pages/tag.php)
- [templates/pages/thread.php](/home/wsl/v3/templates/pages/thread.php)
- [templates/pages/post.php](/home/wsl/v3/templates/pages/post.php)
- [templates/partials/post_card.php](/home/wsl/v3/templates/partials/post_card.php)

Observed duplication patterns:
- thread list cards on board and tag pages are nearly the same
- post cards on thread and post pages overlap but are not composed from the same primitive
- content metadata lines are repeated in several places
- reply links and body rendering are manually repeated

## Problems

1. Small UI changes require touching multiple templates.
2. Pages drift in behavior and wording over time.
3. Shared decisions such as what metadata should appear on cards are hard to enforce consistently.
4. It is harder to introduce new card variants without copying existing markup again.

## Standardization Strategy

Introduce a small set of shared partials with clear ownership:

1. `thread_summary_card.php`
- Used for board and tag thread listings.
- Responsibilities:
  - subject/title link
  - created-at / author meta via existing helpers
  - optional body preview
  - optional reply count
- Inputs:
  - `thread`
  - `show_body_preview`
  - `show_reply_count`
  - optional variant flags

2. `post_card.php`
- Keep the existing partial name, but promote it into the canonical shared post-card primitive.
- Responsibilities:
  - post id line
  - created-at / author meta
  - body
  - reply action
- Inputs:
  - `post`
  - optional flags such as:
    - `show_permalink`
    - `show_reply_action`
    - `show_thread_context`

3. `thread_header_card.php`
- Shared for the top card on the thread page.
- Responsibilities:
  - thread title
  - created-at / author meta
  - labels
  - reply count
  - thread-level actions such as `Like`
- Inputs:
  - `thread`
  - `viewerProfile`
  - `viewerHasLiked`

4. Optional `content_meta_line.php`
- Only if the repeated meta-line logic keeps growing.
- Not required for the first refactor.

## Recommended Refactor Order

### Slice 1: Thread summary card

Create a new partial for thread summary cards and migrate:
- [templates/pages/board.php](/home/wsl/v3/templates/pages/board.php)
- [templates/pages/tag.php](/home/wsl/v3/templates/pages/tag.php)

Expected result:
- board and tag pages render the same summary structure from one partial

### Slice 2: Canonical post card

Refactor [templates/partials/post_card.php](/home/wsl/v3/templates/partials/post_card.php) into the canonical shared post-card component and migrate:
- thread-page post loop in [templates/pages/thread.php](/home/wsl/v3/templates/pages/thread.php)
- standalone post page in [templates/pages/post.php](/home/wsl/v3/templates/pages/post.php), either directly or via a small wrapper

Expected result:
- all post-body cards come from one shared partial

### Slice 3: Thread header card

Move the top-of-thread card from [templates/pages/thread.php](/home/wsl/v3/templates/pages/thread.php) into a shared partial.

Expected result:
- thread page becomes mostly composition:
  - thread header card
  - repeated post cards

### Slice 4: Cleanup and naming

After migration:
- remove dead duplicated markup
- align option names across partials
- document which partial to use for:
  - thread summaries
  - thread headers
  - post bodies

## Proposed Partial Interfaces

### `templates/partials/thread_summary_card.php`

Suggested inputs:

```php
[
    'thread' => $thread,
    'showBodyPreview' => true,
    'showReplyCount' => true,
]
```

Notes:
- default to showing body preview and reply count
- keep labels visible if that remains desired on tag pages
- do not reintroduce score display

### `templates/partials/post_card.php`

Suggested inputs:

```php
[
    'post' => $post,
    'showThreadContext' => false,
    'showReplyAction' => true,
]
```

Notes:
- thread page likely uses `showThreadContext = false`
- standalone post page likely uses `showThreadContext = true`

### `templates/partials/thread_header_card.php`

Suggested inputs:

```php
[
    'thread' => $thread,
    'viewerProfile' => $viewerProfile,
    'viewerHasLiked' => $viewerHasLiked,
]
```

Notes:
- keep the reaction JS hooks here
- keep thread-specific top matter out of the summary-card partial

## Non-Goals

Not part of this refactor by default:
- redesigning the visual style
- changing the content model
- changing reaction behavior
- moving all page-level wrapper cards into partials

## Test Plan

Update smoke coverage incrementally per slice:

1. Board/tag pages
- ensure thread summaries still render title, preview, and reply count

2. Thread page
- ensure thread header still renders labels and Like action
- ensure repeated posts still render reply links and body text

3. Post page
- ensure standalone post view still renders thread context and reply action

4. Static artifact smoke tests
- ensure refactor does not alter route behavior or break artifact generation

## Risks

1. Over-generalizing the partial interfaces too early.
- Mitigation:
  - start with only board/tag summary sharing

2. Mixing thread-summary and full-thread-header concerns.
- Mitigation:
  - keep separate partials for summary vs header

3. Introducing too many tiny partials that make templates harder to read.
- Mitigation:
  - prefer 2-3 meaningful shared partials over many micro-partials

## Recommendation

Start with:
- `thread_summary_card.php` for board + tag pages
- then normalize `post_card.php`

That gives the biggest reduction in duplication with the lowest risk, and it avoids refactoring the reaction-bearing thread header until the simpler shared card surfaces are stable.
