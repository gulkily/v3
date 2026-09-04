# Site Text Search Step 1 Solution Assessment

## Problem Statement

Users need a site search path that lets them enter text and find matching visible forum content without leaving the application.

## Option A: Add simple SQL-backed search over the existing read model

Pros:
- Fits the current PHP application and SQLite read-model architecture.
- Keeps the first user-facing version small by querying existing `posts` and `threads` content.
- Avoids database migrations and new services.
- Easy to expose as a normal page with graceful empty-query and no-result states.

Cons:
- Relevance ranking will be basic.
- Substring matching can get slower as content grows.
- Search behavior may differ from the existing related-content ranking path.

## Option B: Add SQLite full-text search for public site search

Pros:
- Better suited to user-entered text search than ad hoc `LIKE` matching.
- Can provide more useful ranking, tokenization, and snippet behavior.
- Keeps search local to SQLite without adding an external service.

Cons:
- Requires read-model/index changes and rebuild considerations.
- Adds migration and compatibility risk around SQLite FTS availability.
- More upfront work before proving the desired page and workflow.

## Option C: Introduce a reusable search service boundary first, backed by the existing read model

Pros:
- Lets the public search page and future callers share one search contract.
- Can start with simple SQL and later move to FTS behind the same boundary.
- Aligns with the existing internal related-content search pattern.
- Keeps UI, route, and search behavior testable without committing to a permanent ranking engine.

Cons:
- Slightly more design work than directly querying inside a page handler.
- Initial relevance still depends on the first backing strategy.
- The boundary must stay narrow to avoid premature abstraction.

## Recommendation

Recommend Option C.

Brief justification:
- The main uncertainty is product behavior and ranking quality, while the app already has SQLite read-model content and precedent for a search service.
- A small shared boundary supports the first public search page now and leaves room for SQLite FTS later without reshaping the route or template.
