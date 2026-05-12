# Cross Thread Reference Search Step 1 Solution Assessment

## Problem Statement

When a user post references another discussion or asks a question already answered elsewhere, the application should find relevant existing threads/comments and make that context available to analysis and reply generation.

## Option A: Extend post analysis with in-process SQLite search

Pros:
- Fits the current PHP analysis path and read-model architecture.
- Keeps the first version small: use existing `posts` and `threads` data before introducing a new service boundary.
- Gives the analyzer deterministic related-content context without requiring tool orchestration.
- Easier to test with the existing smoke and analyzer tests.

Cons:
- Search quality is limited unless the read model gains better indexing/ranking.
- Analyzer remains responsible for both detection and use of related context.
- Harder to reuse from external agents or operator tooling.

## Option B: Add a local MCP server for forum content search, then let the analyzer/agent call it

Pros:
- Creates a reusable search interface for agents, moderation tools, and future workflows.
- Separates retrieval concerns from post analysis and reply generation.
- Can grow into richer search/ranking without expanding application request handlers.

Cons:
- Larger operational surface: server process, configuration, auth/scope, and failure behavior.
- Current PHP analyzer path does not appear to have tool-calling infrastructure, so integration would require new orchestration.
- More moving pieces before proving the product behavior.

## Option C: Hybrid phased approach: in-process search first, MCP wrapper second

Pros:
- Proves relevance, ranking, prompt shape, and UI behavior inside the existing app first.
- Leaves a clean path to expose the same search capability through MCP once the contract stabilizes.
- Avoids committing to MCP process boundaries before the retrieval requirements are understood.
- Keeps Step 2 and Step 3 focused while acknowledging the likely future integration point.

Cons:
- Requires designing the search API boundary carefully enough that it can later back MCP.
- May duplicate a small amount of adapter work when MCP is added.
- Does not immediately satisfy external agent/tool access.

## Recommendation

Recommend Option C.

Brief justification:
- The core uncertainty is retrieval quality and analyzer behavior, not MCP mechanics.
- The existing app already has a SQLite read model and post-analysis context path that can carry related threads/comments.
- A small internal search service can become the future MCP server’s backing contract after the feature proves useful.
