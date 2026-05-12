# Cross Thread Reference Search Step 3 Development Plan

## Stage 1
- Goal: Add an internal related-content search boundary backed by existing forum read-model content.
- Dependencies: Approved Step 2 requirements and current SQLite read model availability.
- Expected changes: Add a small search service class that accepts a target post context and returns bounded related post/thread matches; planned signature: `findRelatedContent(array $targetPost, int $limit = 5): array`.
- Verification approach: Unit-test matched, unmatched, self-exclusion, same-thread exclusion/preference behavior, and stable URL/excerpt fields using an in-memory SQLite fixture.
- Risks or open questions:
  - Initial ranking may be keyword-oriented and imperfect.
  - Search should stay useful without introducing a schema migration in this feature.
- Canonical components/API contracts touched: SQLite read model `posts`/`threads` data; new internal search service contract only.

## Stage 2
- Goal: Feed related-content matches into the existing post-analysis context.
- Dependencies: Stage 1 search service contract.
- Expected changes: Extend the analyzer context builder to include a bounded `related_content` list when matches exist; keep empty/no-match behavior compatible with existing analysis calls.
- Verification approach: Extend application/write API tests to assert analyzer context includes related matches, excludes the target post, and preserves existing `thread_comments` behavior.
- Risks or open questions:
  - Context size limits must keep provider requests bounded.
  - Related matches must not duplicate same-thread comments in a confusing way.
- Canonical components/API contracts touched: `/api/analyze_post` behavior, post-analysis context object, existing post-load analysis flow.

## Stage 3
- Goal: Let the analyzer and generated replies use related-content context safely.
- Dependencies: Stage 2 context field.
- Expected changes: Update the analysis prompt/schema expectations so related matches can influence `post_summary`, `engagement.suggested_response`, and respondability without requiring a match; planned response handling remains backward compatible.
- Verification approach: Update analyzer tests with fixture completions that use related content and fixture completions that ignore absent related content.
- Risks or open questions:
  - Prompt wording must avoid overstating weak matches as definitive answers.
  - Public replies need stable links without exposing private analysis metadata.
- Canonical components/API contracts touched: Dedalus post-analysis prompt, analyzer response contract, agent reply generation input through stored analysis.

## Stage 4
- Goal: Add inspectability and regression coverage for the completed related-content analysis path.
- Dependencies: Stages 1-3.
- Expected changes: Surface related-content summaries in analysis details where analysis details are already shown; keep normal thread/post rendering unchanged when details are hidden.
- Verification approach: Run the focused PHP test suite for analyzer, write API, and local app smoke coverage; manually exercise a post that asks an already-answered question and confirm related links appear only where expected.
- Risks or open questions:
  - Public display may need restrained copy to avoid making uncertain matches look authoritative.
  - Manual verification depends on having enough fixture content to produce a meaningful match.
- Canonical components/API contracts touched: Existing post-analysis details UI, post cards/permalinks, existing test fixtures.
