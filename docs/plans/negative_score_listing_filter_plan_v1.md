# Negative Score Liked Listing Filter Plan V1

## Goal

Threads whose root post has `post_score_total < 0` should not appear on the Liked board listing pages.

This applies only to:

- `/`
- `/threads/?view=liked`
- `/threads/?view=liked&sort=newest`
- `/threads/?view=liked&sort=oldest`
- `/threads/?view=liked&sort=top`

Thread content listings should not be affected. A negative-score post or reply should still appear inside its thread unless it is already hidden by the existing `is_hidden = 1` moderation path.

This is a presentation/query filtering change, not a schema change.

## Current Context

The board route uses `Application::renderBoard()`, which calls:

```php
$this->fetchBoardThreads($view, $sort)
```

`fetchBoardThreads()` currently:

1. normalizes the board view and sort
2. loads all board threads through `fetchThreads()`
3. filters each thread through `matchesBoardView()`
4. sorts with `compareBoardThreads()`

The Liked view is selected by `view=liked`, and all three sort modes reuse the same filtered thread list:

- `newest`
- `oldest`
- `top`

The root route `/` defaults to the Liked view.

The thread page uses a separate path:

```php
renderThread() -> fetchThreadPosts()
```

That path should remain score-neutral.

## Product Semantics

- Negative-score root posts are suppressed only from the Liked board listing.
- The All board listing remains unchanged.
- Tag pages remain unchanged.
- User pages remain unchanged.
- Activity pages remain unchanged.
- Related-content suggestions remain unchanged.
- Direct post pages remain unchanged.
- Thread pages remain unchanged, including reply lists.
- Existing `is_hidden = 1` filtering remains unchanged everywhere it already applies.

This means negative score is not a hidden-post state. It is only a Liked-listing exclusion.

## Implementation Slice

Update `Application::matchesBoardView()` in `src/ForumRewrite/Application.php`.

Current behavior:

```php
return match ($view) {
    'all' => true,
    'liked' => in_array('like', $thread['thread_labels'] ?? [], true),
    default => true,
};
```

Recommended behavior:

```php
return match ($view) {
    'all' => true,
    'liked' => in_array('like', $thread['thread_labels'] ?? [], true)
        && ((int) ($thread['root_post_score_total'] ?? 0)) >= 0,
    default => true,
};
```

To support that, update `Application::fetchThreads()` so the root post score is selected and hydrated:

```sql
posts.post_score_total AS root_post_score_total
```

Then update `hydrateThreadRow()` to cast it:

```php
$thread['root_post_score_total'] = (int) ($thread['root_post_score_total'] ?? 0);
```

Do not add `post_score_total >= 0` to `fetchThreads()` itself. That would also affect All, tags, API list output, RSS, and any other caller that relies on the generic thread list.

Do not change `fetchThreadPosts()`.

## Why This Shape

Filtering in `matchesBoardView()` keeps the behavior tied to the Liked view only. Because the sort mode is applied after view filtering in `fetchBoardThreads()`, the rule automatically applies to Newest, Oldest, and Top without duplicating logic in each comparator.

Selecting the root post score in `fetchThreads()` keeps the existing thread-list data shape explicit and avoids additional per-thread database lookups.

## Tests

Add route-level coverage for a liked thread whose root post has a negative score.

Fixture setup:

- root post has an approved `flag`, producing `post_score_total = -100`
- root post remains `is_hidden = 0`
- thread also has `like` in `thread_labels`

Assert it is absent from:

- `/`
- `/threads/?view=liked&sort=newest`
- `/threads/?view=liked&sort=oldest`
- `/threads/?view=liked&sort=top`

Assert it remains present in:

- `/threads/?view=all`
- `/threads/<thread_id>`
- `/posts/<root_post_id>`

Add a reply-level regression fixture:

- reply post has `post_score_total < 0`
- reply post remains `is_hidden = 0`

Assert:

- `/threads/<thread_id>` still includes the reply

Useful existing test files:

- `tests/LocalAppSmokeTest.php` for rendered route assertions
- `tests/ReadModelPostReactionsTest.php` for score derivation behavior

## Non-Goals

Do not change these paths:

- `Application::fetchThreadPosts()`
- `Application::fetchVisibleAuthoredPosts()`
- `Application::fetchVisibleAuthoredThreads()`
- `Application::fetchActivity()`
- `RelatedContentSearchService::findRelatedContent()`
- `Application::fetchPost()`

Do not introduce a new hidden state or mutate `is_hidden` for negative-score human-authored posts.

## Verification

Run:

```sh
php tests/run.php
```

If static artifacts are used, rebuild them after implementation because the Liked board output can change.

## Open Decision

The root route `/` currently defaults to the Liked view. This plan treats `/` as in scope because it is a Liked listing page by default.
