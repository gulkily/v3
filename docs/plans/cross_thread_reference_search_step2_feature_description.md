# Cross Thread Reference Search Step 2 Feature Description

## Problem

Posts sometimes refer to other discussions or ask questions already answered elsewhere, but the analyzer currently sees only the current post and bounded same-thread context. The feature should surface relevant existing threads/comments so analysis and generated replies can point users toward prior discussion instead of duplicating or missing context.

## User Stories

- As a poster, I want related prior discussions to be found automatically so that I can get useful context without manually searching the board.
- As a reader, I want replies to reference existing answers when relevant so that repeated questions connect back to the best available discussion.
- As a moderator/operator, I want related-content matching to be inspectable enough to diagnose poor matches so that automated analysis stays trustworthy.
- As a developer, I want the search capability shaped as a reusable boundary so that it can later back an MCP server without redesigning the feature.

## Core Requirements

- Detect likely cross-thread references and repeated-question cases during post analysis without slowing or blocking post creation.
- Retrieve a bounded set of relevant existing threads/comments from current forum content, excluding the target post itself.
- Include enough related-content context for the analyzer and reply generator to summarize or cite relevant matches safely.
- Make no public canonical record writes for inferred matches in the first version.
- Preserve graceful behavior when search has no good matches or the analyzer provider is unavailable.

## Shared Component Inventory

- Post-analysis trigger and API flow (`/api/analyze_post` plus post-load analysis script): extend this canonical flow rather than adding a new user action.
- Analyzer context builder for posts, parent previews, and same-thread comments: extend this context shape with related-content results.
- SQLite read model pages and data for posts/threads: reuse as the source of searchable forum content.
- Existing post cards and thread/post permalinks: reuse URLs when presenting or generating references to matched content.
- Agent reply generation flow: extend its input through stored analysis results rather than creating a separate reply path.
- MCP integration: no existing canonical MCP surface in this repo, so defer public MCP exposure until the internal search contract is proven.

## Simple User Flow

1. User creates a thread or reply normally.
2. The existing post-load analysis request runs for the new post.
3. The application searches existing content for related threads/comments.
4. The analyzer receives the target post plus a bounded related-content list.
5. If matches are useful, analysis and any generated reply can mention the relevant prior discussion with links.
6. If no useful match exists, analysis proceeds as it does today.

## Success Criteria

- New post creation remains fast because related-content search runs only in the existing analysis path.
- Analyzer context can include relevant matches with stable post/thread URLs and concise excerpts.
- Same-thread context continues to work, and cross-thread matches exclude the target post.
- Existing analyzer and write API tests can verify matched, unmatched, and provider-failure behavior.
- The resulting search boundary is clear enough to expose through MCP in a later feature without changing the analyzer-facing contract.
