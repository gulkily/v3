# Outbox Item Target Link — Step 3 Development Plan

## Stage 1
- Goal: Let people open the original forum target of eligible Outbox entries without changing the item’s delivery state or outcome meaning.
- Dependencies: Approved Step 2 description; existing Outbox item target records; normal thread and post routes; current accepted-outcome link rendering.
- Expected changes: Add a safe target-link presentation helper to the canonical Outbox renderer; render thread links for thread-target items and post links for post-target items; omit the link for board-only or incomplete legacy targets; add focused rendering tests for each route and fallback.
- Verification approach: Run JavaScript syntax checking and focused Outbox presentation/state tests; manually inspect an expanded thread-target and reply-target item while online and an eligible thread-target item while offline.
- Risks or open questions: A visible link does not guarantee that its normal route is cached offline; retain the existing offline navigation boundary and do not represent target availability or delivery success through the link.
- Canonical components/API contracts touched: `outbox.js` compact item renderer; Outbox `target.kind`/`target.id` records; normal `/threads/{id}` and `/posts/{id}` routes; existing accepted-item published-content link.
