# Fastmod scoring

Purpose: Keep reusable advisory probabilities separate from canonical content and provider workflow state.
Scenario: A compatible imported score fills a missing result without replacing a local result, resetting its age, or enqueueing a provider call. Named scores must match the selected rubric revision and accepted content hash.
Sources: [store](../../src/ForumRewrite/Scoring/SqliteFastScoreStore.php), [content hash](../../src/ForumRewrite/Scoring/FastScoreContextFactory.php), [transfer format](../specs/fastmod_score_transfer_v1.md).
