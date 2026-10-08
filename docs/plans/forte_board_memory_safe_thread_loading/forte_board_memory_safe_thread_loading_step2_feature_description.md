> **Feature plan:** [Step 1](./forte_board_memory_safe_thread_loading_step1_solution_assessment.md) · [Step 2](./forte_board_memory_safe_thread_loading_step2_feature_description.md) · [Step 3](./forte_board_memory_safe_thread_loading_step3_development_plan.md) · [Step 4](./forte_board_memory_safe_thread_loading_step4_implementation_summary.md)

# Forte Board Memory-Safe Thread Loading Step 2 Feature Description

## Problem

The Forte board includes every thread body and reply tree in its initial, hidden content pane. That unbounded render exhausts the production 128 MiB PHP memory limit and makes the initial document grow with the entire board.

## User Stories

- As a Forte reader, I want the board to open reliably regardless of how many threads exist so that I can browse without server errors.
- As a reader moving between threads, I want nearby content to appear quickly so that the three-pane reader remains responsive.
- As a user opening a Forte permalink, I want its thread and highlighted reply to load correctly so that shared links remain dependable.

## Core Requirements

- The initial Forte document contains the list/folder UI and at most the selected thread's complete pane; it never includes unselected thread bodies or replies.
- Selecting a thread, including keyboard navigation and browser history restoration, displays its complete rendered pane and retains the current URL, compose, reaction, and reply-highlight behaviors.
- The client progressively preloads likely next threads in small background batches, prioritizing the selected thread and visible neighbors.
- Cached panes are bounded by an explicit count and/or byte budget with eviction; small boards may become fully cached incrementally, while large boards remain bounded.
- Loading, invalid, unavailable, and stale detail responses provide recoverable feedback and never replace a newer selection.

## Delivery Scope

- Work type: application change.
- In scope: Forte board server-rendered detail delivery, initial board rendering, client selection/preload/cache behavior, and focused regression coverage.
- Out of scope: database schema changes, changing board/thread visibility policy, offline persistence, and a permanent PHP memory-limit increase.

## Completion Boundary

- Normal entry: visit `/forte`, select a thread, or open a selected-thread/reply permalink.
- End-to-end outcome: the requested pane is shown, then appropriate neighbors are optionally cached without eagerly rendering the whole board.
- Recovery: a failed or stale background/detail request leaves the current pane intact and presents a retryable error state.
- Release condition: regression tests prove unselected content is absent from the initial page and verify selection, permalink, cache-bound, and failure behavior.

## Risks

- Injected panes may not preserve reactions or compose behavior; validate an injected reply pane first and reuse the existing reaction/compose integration.
- Stale or out-of-order responses could display the wrong thread; validate rapid selection early and gate rendering by current selection.
- An unbounded preload/cache could move the resource problem to browser memory or backend load; validate configured bounds and batch prioritization before broadening prefetch.
- Direct links to excluded-but-addressable threads could regress; validate their existing permalink path before replacing eager rendering.

## Shared Component Inventory

- `ForteBoardController` and `paned_board_content_pane.php` — canonical Forte board-pane source; extend it so the same pane can represent one requested thread rather than fork its markup.
- `paned_thread_reply_tree.php` — canonical reply rendering; reuse inside the selected pane.
- `paned_board_reader.js` — canonical board selection, URL/history, keyboard, and compose behavior; extend it for detail loading and bounded preloading.
- `paned_users_reader.js` and `/api/forte_user_detail` — reusable client loading/error/cache interaction pattern; adapt its behavior for threads.
- `ForteActivityController::activityDetail()` — reusable `status` plus rendered-HTML detail-response convention.
- `/api/get_forte_content_summary` — remains summary-only and cannot supply a full pane; do not repurpose it as the thread-detail surface.
- `thread_reactions.js` and existing Forte permalink producers — retain their existing contracts against the rendered selected pane.

## Simple User Flow

1. User opens `/forte`; the thread list and requested pane, if any, render without hidden full-board content.
2. The reader loads the selected pane immediately on a cache miss and shows it from cache on a hit.
3. Idle background work preloads nearby visible threads within the cache budget.
4. User selects, arrows through, or returns via browser history to another thread; the matching pane appears without stale content replacing it.
5. If loading fails, the reader shows a recoverable error and keeps the last valid pane available.

## Success Criteria

- `/forte` no longer fails because it renders all board bodies and reply trees in one request.
- The initial page excludes every unselected thread's complete content pane.
- Direct selected-thread and reply permalinks, reactions, compose targeting, filtering, sorting, keyboard navigation, and history restoration continue to work.
- Prefetching is incremental and demonstrably bounded, while cached nearby threads can be displayed without another network request.
