# Board Filters And Sorts Plan v1

## Purpose

Add Activity-style sections to the Board page so users can switch between useful thread subsets and sort orders without leaving `/`.

This plan is grounded in the current code:
- Board currently renders all visible threads from [src/ForumRewrite/Application.php](/home/wsl/v3/src/ForumRewrite/Application.php)
- Activity already uses a simple `view` query param plus nav-link controls
- Thread rows already include the fields needed for several useful filters and sorts:
  - `root_post_created_at`
  - `last_activity_at`
  - `reply_count`
  - `board_tags_json`
  - `thread_labels_json`
  - author identity / approval info

## Design Goal

Keep the Board controls simple and legible:
- one `View` section, mirroring Activity page behavior
- one `Sort` section, separate from the view
- no giant faceted-search UI in the first version

The model should feel like:
- Activity: “what kind of items do you want?”
- Board: “what kind of threads do you want, and in what order?”

## Recommended URL Shape

Use query params:
- `?view=<value>`
- `?sort=<value>`

Examples:
- `/?view=all&sort=last_activity`
- `/?view=unanswered&sort=newest`
- `/?view=labeled&sort=most_replies`

Recommendation:
- default to `view=all`
- default to `sort=last_activity`

## Recommended Board Views

For the first pass, use only two views.

These should appear as nav links similar to Activity’s current section links.

### 1. `all`

Label:
- `All`

Meaning:
- all visible board threads

Why:
- baseline/default board view

### 2. `liked`

Label:
- `Liked`

Meaning:
- only threads with positive like score

Suggested predicate:
- `score_total > 0`

Why:
- this is the highest-signal score-aware subset
- it matches the initial product direction better than broader workflow or moderation filters
- it creates a clear use for the new scoring model without adding too many board modes

## View Expansion Model

Even though v1 only ships `all` and `liked`, the code should make new views easy to add.

Recommended implementation pattern:
- one `normalizeBoardView()` method with an allowlist
- one `boardViewOptions()` method that returns label/href metadata
- one `matchesBoardView(array $thread, string $view): bool` method

That way, adding a new view later is a small, local change:
1. add the new key to the allowlist
2. add one nav option
3. add one match rule

## Views Deferred For Later

These are explicitly deferred, not rejected:
- `unanswered`
- `active`
- `labeled`
- `identity`
- `approvals`
- viewer-specific filters
- arbitrary tag-driven views

## Recommended Sorts

Sort should be its own control group, separate from `view`.

### 1. `newest`

Label:
- `Newest`

Meaning:
- sort by `root_post_created_at DESC`

Why:
- useful for scanning new threads regardless of reply churn

### 2. `oldest`

Label:
- `Oldest`

Meaning:
- sort by `root_post_created_at ASC`

Why:
- useful when paired with `unanswered` to work from oldest neglected threads first

### 3. `top`

Label:
- `Top`

Meaning:
- sort by score descending, then fallback to newest-first

Why:
- this is the clearest ranking-oriented board sort
- it directly exposes “most liked” behavior

Suggested ordering:
- `score_total DESC`
- `root_post_created_at DESC`
- `root_post_id DESC`

## Sorts I Do Not Recommend In v1

### reply-count sorts

Why not yet:
- they are useful, but secondary to the score-aware rollout you want now

### alphabetical sorts

Why not:
- thread ids/subjects are not the main browsing axis for a board

## Recommended UI Structure

Use the Activity page as the model:

1. Top Board card
- title: `Board`
- actions row: `Tags`, `New Post`
- `View:` current value
- `Sort:` current value

2. View nav row
- same `nav-link` styling used by Activity

3. Sort nav row
- another `nav-link` row directly below view controls

This keeps the mental model simple:
- first choose subset
- then choose ordering

## Recommended Initial Control Set

### View controls
- `All`
- `Liked`

### Sort controls
- `Newest`
- `Oldest`
- `Top`

## Suggested Implementation Shape

### Application changes

In [src/ForumRewrite/Application.php](/home/wsl/v3/src/ForumRewrite/Application.php):

1. Extend board route handling
- read `view` and `sort` query params

2. Add:
- `normalizeBoardView(string $view): string`
- `normalizeBoardSort(string $sort): string`
- `boardViewOptions(string $activeView): array`
- `boardSortOptions(string $activeSort, string $activeView): array`
- `matchesBoardView(array $thread, string $view): bool`
- `compareBoardThreads(array $left, array $right, string $sort): int`

3. Replace current `fetchThreads()` board call path with:
- `fetchBoardThreads(string $view, string $sort): array`

Possible implementation pattern:
- fetch visible threads once
- filter in PHP through `matchesBoardView()`
- sort in PHP through `compareBoardThreads()`

Reason:
- lower risk
- keeps the first cut easy to extend
- avoids premature SQL branching

Later, if needed:
- push sorting/filtering down into SQL for scale

### Template changes

In [templates/pages/board.php](/home/wsl/v3/templates/pages/board.php):

Add:
- current view label
- current sort label
- `viewOptions`
- `sortOptions`

Render:
- one nav row for views
- one nav row for sorts

### Testing changes

Update [tests/LocalAppSmokeTest.php](/home/wsl/v3/tests/LocalAppSmokeTest.php) to cover:
- default board view
- at least one alternative view, e.g. `/?view=unanswered`
- at least one alternative sort, e.g. `/?sort=newest`

Add assertions for:
- active nav-link state
- expected thread inclusion/exclusion
- expected order changes where fixtures make that stable

## Suggested Slice Plan

### Slice 1: Board views and sorts foundation

Scope:
- add `view` param
- add `sort` param
- add view nav row
- add sort nav row
- implement extensible option/matcher/comparator helpers
- implement `all` and `liked`
- implement `newest`, `oldest`, and `top`

Reason:
- ships the narrow v1 feature set
- builds the extension points before more views/sorts are added

### Slice 2: Additional views

Scope:
- add deferred views such as `unanswered`, `active`, or `labeled`

### Slice 3: Additional sorts

Scope:
- add reply-count or activity-based sorts if they still feel necessary after using v1

## Recommendation

For the first pass, I recommend shipping these:

Views:
- `All`
- `Liked`

Sorts:
- `Newest`
- `Oldest`
- `Top`

And I recommend:
- keeping `Newest` as the default sort
- defining views/sorts through centralized helper methods so expansion stays local
- not adding viewer-specific filters yet

That gives the Board page a clear Activity-like control surface with the smallest useful initial set, while keeping future additions cheap.
