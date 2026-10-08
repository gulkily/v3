> **Feature plan:** [Step 1](./forte_board_memory_safe_thread_loading_step1_solution_assessment.md) · [Step 2](./forte_board_memory_safe_thread_loading_step2_feature_description.md) · [Step 3](./forte_board_memory_safe_thread_loading_step3_development_plan.md) · [Step 4](./forte_board_memory_safe_thread_loading_step4_implementation_summary.md)

# Forte Board Memory-Safe Thread Loading Step 3 Development Plan

## Completion Contract

- Normal entry: open `/forte`, select a thread, navigate with keyboard/history, or follow a selected-thread/reply permalink.
- End-to-end outcome: only the selected pane is initially rendered; selection loads a complete canonical pane, and bounded idle preloading makes likely next selections immediate.
- Required recovery: loading/invalid/stale responses preserve the last valid pane and provide retryable feedback.
- Deployment/external verification: after release, request a representative `/forte` board and confirm no PHP memory-exhaustion entry is logged.
- Release condition: focused server/client regressions prove initial-content exclusion, direct links, reaction/compose behavior, bounded cache/preload, and failure recovery.

## Key Risks

- **High risk:** Dynamic panes could lose reactions. Impact: controls appear but do nothing; early validation: inject a reply pane; mitigation: make the existing reaction binder explicitly callable for new pane roots.
- **High risk:** An endpoint could widen visibility. Impact: hidden-content disclosure; early validation: test unknown and excluded-thread requests; mitigation: retain the board's existing direct-permalink eligibility semantics.
- **High risk:** Preloading could shift resource exhaustion to clients or backend. Impact: degraded browsing/load; early validation: assert queue, concurrency, entry, and byte limits; mitigation: fixed 24-pane/4 MiB LRU budget and idle, one-at-a-time fetching.
- Stale responses could replace the current thread. Impact: incorrect content; early validation: simulate rapid selection; mitigation: render only when the response still matches current selection.

## Stage 1 — Establish a canonical one-thread pane

- Goal: Extract the current article markup into a partial that renders exactly one thread and its reply tree.
- Dependencies: Approved Steps 1–2.
- Expected changes: `paned_board_content_pane.php` remains the pane shell; a one-thread article partial becomes its canonical child; initial output stays equivalent.
- Verification approach: Render a selected thread and compare article attributes, reply nesting, reactions, compose target, and permalink markup before/after extraction.
- Risks or open questions:
  - Impact: markup drift breaks client selectors.
  - Early warning / validation: existing Forte smoke assertions plus a rendered-HTML comparison.
  - Mitigation: move markup without changing data attributes or reaction contracts.
- Canonical components/API contracts touched: `paned_board_content_pane.php`, `paned_thread_reply_tree.php`, new canonical one-thread article partial.

## Stage 2 — Deliver one scoped pane through a detail endpoint

- Goal: Provide rendered HTML for one allowed Forte thread without loading all reply posts.
- Dependencies: Stage 1.
- Expected changes: add `ThreadRepository::replyPostsByThreadId(PDO $pdo, string $threadId): array`; add a Forte board detail action and `/api/forte_thread_detail` returning `{status, html}`; reuse the canonical article and existing viewer reaction state.
- Verification approach: Exercise valid, unknown, invalid, direct-link-only, and highlighted-reply requests; confirm a returned pane contains only that thread's replies and correct viewer state.
- Risks or open questions:
  - Impact: detail visibility differs from current permalink behavior.
  - Early warning / validation: compare both paths for an excluded-but-addressable thread.
  - Mitigation: centralize the board's existing single-thread resolution semantics in the detail action.
- Canonical components/API contracts touched: `ForteBoardController`, `ThreadRepository`, `RouteServices::renderFragment()`, JSON detail-response convention.

## Stage 3 — Bound the initial Forte document

- Goal: Stop `/forte` from constructing hidden panes for every listed thread.
- Dependencies: Stages 1–2.
- Expected changes: initial board rendering supplies no pane when unselected and at most the requested selected-thread pane when selected; remove all-thread reply-tree preparation while preserving direct permalink resolution.
- Verification approach: Render a multi-thread fixture; assert unselected body/reply markers are absent and selected/permalink output remains present under a 128 MiB PHP limit.
- Risks or open questions:
  - Impact: an unselected board could show stale or missing placeholder/compose state.
  - Early warning / validation: smoke-test no-selection, selection, filter, and direct-link states.
  - Mitigation: retain the existing pane shell and compose panel outside the replaceable article.
- Canonical components/API contracts touched: `ForteBoardController::board()`, `paned_board_content_pane.php`, Forte URL parameters.

## Stage 4 — Make selection load and bind a dynamic pane

- Goal: Restore complete in-place selection after initial-page slimming.
- Dependencies: Stages 2–3.
- Expected changes: extend `paned_board_reader.js` to request/replace one article with loading and retry states, current-selection response gating, initial-pane reuse, and preserved URL/history/keyboard behavior; expose a scoped binding entry point in `thread_reactions.js` for injected roots.
- Verification approach: Use the Node client harness for hit/miss, fast selection, error, and history cases; manually verify a loaded reply Like/Flag and compose target.
- Risks or open questions:
  - Impact: injected controls or stale responses behave incorrectly.
  - Early warning / validation: exercise two out-of-order mocked responses and an injected reaction control.
  - Mitigation: bind only the inserted pane and verify its identity before replacement.
- Canonical components/API contracts touched: `paned_board_reader.js`, `thread_reactions.js`, `/api/forte_thread_detail`, existing compose/reply data attributes.

## Stage 5 — Add bounded progressive preloading

- Goal: Make likely next selections fast without reintroducing unbounded work.
- Dependencies: Stage 4.
- Expected changes: add a 24-pane/4 MiB LRU cache and idle, one-at-a-time nearby-visible-thread preload queue; cache hits avoid a request, and eviction never removes the current pane.
- Verification approach: Mock queue ordering, cache hits, byte/count eviction, and rapid selection; verify a small fixture can fully warm while a larger fixture remains within both limits.
- Risks or open questions:
  - Impact: background work competes with interactive loading.
  - Early warning / validation: inspect request order while changing selection/filter.
  - Mitigation: prioritize interactive misses, cancel/deprioritize obsolete candidates, and fetch preloads only when idle.
- Canonical components/API contracts touched: `paned_board_reader.js`, `/api/forte_thread_detail`, selected-thread URL/list-row contract.

## Stage 6 — Prove the memory and interaction boundary

- Goal: Lock in the complete vertical slice and operational outcome.
- Dependencies: Stages 1–5.
- Expected changes: add focused PHP and Node regression coverage; update the test runner only if a new test file is introduced; no schema or deployment-configuration change.
- Verification approach: run focused tests and full suite; render a board whose non-selected content would have exceeded the prior response shape at 128 MiB; manually verify production-style request/log outcome after deployment.
- Risks or open questions:
  - Impact: a structural test misses real memory growth.
  - Early warning / validation: combine exclusion assertions with constrained-memory rendering.
  - Mitigation: keep the fixture deterministic and assert both successful response and absent unselected markers.
- Canonical components/API contracts touched: Forte smoke tests, Node asset test harness, `tests/run.php` if needed.
