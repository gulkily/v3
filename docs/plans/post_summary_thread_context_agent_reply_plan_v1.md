# Post Summary and Thread Context for Agent Replies Plan V1

## Goal

Add a neutral one-sentence `post_summary` to stored post analysis, then use each thread comment's `post_summary` and full body text as context when deciding/generated agent replies.

## Current State

- Post analysis returns exactly four top-level keys: `engagement`, `moderation`, `quality`, and `respondability`.
- The only existing summary is `moderation.summary`, which is moderation-specific and should not be reused as a neutral content summary.
- `/api/generate_agent_reply` currently does not make a second model call. It posts `analysis.engagement.suggested_response` from the existing post analysis result.
- The current analysis context includes the target post body, thread root subject/body preview, and immediate parent body preview, but not all comments in the thread.
- Thread posts are already fetchable in sequence order through `fetchThreadPosts()`.

## Assumptions

- `post_summary` should be a top-level analysis field, not nested under moderation.
- `post_summary` should be one sentence, neutral, ASCII-only, and about the content of the post rather than moderation or reply-worthiness.
- The reply-generation context should include all comments in the target thread in chronological/thread sequence order.
- Each thread comment context entry should include at least `post_id`, `parent_id`, `author_label`, `created_at`, `post_summary`, and full `body`.
- For comments without a completed current-schema analysis, `post_summary` should be empty rather than blocking reply generation.
- Full comment text should still be capped by a conservative per-comment and total-context budget to avoid unbounded prompt growth.

## Implementation Plan

1. Update the post analysis schema and prompt.
   - Add top-level `post_summary` to `prompts/dedalus_post_analysis_system.txt`.
   - Update `DedalusPostAnalyzer::responseSchema()` to require a top-level `post_summary` string.
   - Update `DedalusPostAnalyzer::analyze()` to return `post_summary` alongside the existing analysis sections.
   - Update tests that construct expected analysis payloads.

2. Persist and hydrate `post_summary`.
   - Add a `post_summary` column to `post_analyses`.
   - Extend `SqlitePostAnalysisStore::ensureSchema()` with an `ALTER TABLE` migration for existing SQLite databases.
   - Save and hydrate `post_summary` in complete analysis rows.
   - Keep failed/config-missing analysis rows without a summary.

3. Bump the analysis schema version.
   - Change `analysis_schema_version` from `2` to `3` in `Application::postAnalysisContext()`.
   - This changes the content hash, preventing old cached analyses from being treated as complete for the new response shape.

4. Display the neutral summary in Post analysis.
   - Add `Post summary:` near the top of the `<details class="post-analysis">` panel.
   - Keep the existing moderation `Summary:` field but relabel it if needed to avoid ambiguity, for example `Moderation summary:`.

5. Build full-thread reply context.
   - Add an application helper that builds `thread_comments` for a target post.
   - Fetch all posts in the thread with `fetchThreadPosts($threadId)`.
   - For each comment, compute its current analysis content hash and look up the stored analysis.
   - Include the hydrated `post_summary` if present, plus full body text capped through a new limit helper.
   - Include metadata that lets the model distinguish the target post, parent post, root post, and existing reply-agent posts.

6. Use thread context for generated replies.
   - Because reply generation currently posts `analysis.engagement.suggested_response`, update the analysis context before the initial analysis call so suggested responses can use all thread comments.
   - Also add `thread_comments` to `$generationContext` stored in `post_generated_responses`, so the persisted generation record shows the context used.
   - If we later re-enable `DedalusAgentReplyGenerator::generate()`, pass the same `thread_comments` context into that model call too.

7. Gate context size.
   - Add per-comment body truncation for reply context, separate from post-analysis target body truncation.
   - Add a total thread comment budget so very large threads do not create oversized requests.
   - Prefer preserving the target post, root post, parent post, and latest comments if truncation is necessary.

8. Verification.
   - Update `DedalusPostAnalyzerTest` for the new top-level `post_summary` shape.
   - Add or update store tests to confirm `post_summary` persists and hydrates.
   - Add an application-level test that reply/analysis context for a thread includes multiple comments with summaries and body text.
   - Run `php tests/run.php`.

## Open Decisions

- Exact truncation limits for thread comments. Proposed starting point: 3000 chars per comment and 18000 chars total.
- Whether the UI label should be `Post summary` and `Moderation summary`, or leave the moderation field as `Summary`.
- Whether missing per-comment summaries should remain empty, or whether reply generation should opportunistically analyze missing comments first. The safer first implementation is empty summaries to avoid cascading provider calls.

