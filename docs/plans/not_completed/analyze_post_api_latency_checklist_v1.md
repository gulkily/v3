# analyze_post API Latency Checklist

Goal: reduce `/api/analyze_post` wall time by making the Dedalus completion request smaller and easier to satisfy.

## Measure First

- [ ] Split `post_analysis` timing into `analysis_cache_lookup`, `dedalus_http`, `analysis_decode`, and `analysis_store`.
- [ ] Log prompt payload size, `thread_comments` count, and response usage fields when available.
- [ ] Capture before/after timings for cached, short-thread, and long-thread cases.

## Shrink Context

- [ ] Lower `THREAD_CONTEXT_TOTAL_BODY_LIMIT` from `18000` to a smaller trial value such as `6000`.
- [ ] Lower `THREAD_CONTEXT_COMMENT_BODY_LIMIT` from `3000` to a smaller trial value such as `1200`.
- [ ] Include full body only for the target post; send summaries or short previews for root, parent, and sibling comments.
- [ ] Cap the number of non-target thread comments included, prioritizing root, parent, recent comments, and agent-authored replies.
- [ ] Omit `thread_body_preview` and `parent_body_preview` when equivalent posts are already present in `thread_comments`.

## Reduce Output Budget

- [ ] Lower `max_completion_tokens` for `DedalusPostAnalyzer` from `4000` to a trial value such as `1200`.
- [ ] Add tests that the expected JSON response still fits within the reduced budget.
- [ ] Compare latency and failure rate for `800`, `1200`, and `1600` token caps.

## Simplify Schema

- [ ] Remove or defer fields not needed for immediate reply generation.
- [ ] Consider collapsing `quality` and `respondability` into fewer scalar fields.
- [ ] Keep only the moderation fields needed for gating: severity, labels, recommended action.
- [ ] Shorten string max lengths for summaries, reasons, and suggested replies.

## Simplify Task

- [ ] Split "analyze post" from "write suggested reply" if reply generation is the slow part.
- [ ] Trial a minimal gating-only analysis for posts unlikely to need a reply.
- [ ] Ask for a reply only after the model decides `should_generate_response=true`.
- [ ] Shorten the system prompt by removing style guidance that only matters when generating a public reply.

## Rollout

- [ ] Implement one change at a time behind constants or config so timing comparisons stay clean.
- [ ] Keep the current behavior as the baseline until the new path shows lower latency without worse skipped/reply quality.
- [ ] Prefer changes that reduce both input size and output complexity before changing models or providers.
