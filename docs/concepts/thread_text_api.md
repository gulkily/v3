# Thread text API

Purpose: Let an agent read a conversation and identify its speakers without fetching every post separately.

Scenario: A client requests `/api/get_thread?thread_id=<id>&include_authors=1`.
It receives thread headers and blank-line-separated post blocks with `Post-ID`, `Author`, and applicable `Author-Identity-ID` headers.
Each body retains its single-line `[post-id]` prefix. Without the exact opt-in value `1`, the existing compact response stays unchanged.

Attribution: Reuse `get_post`'s existing rule. An identity ID appears only when a post names an identity and has a detached-signature source file.
This is source presence, not a cryptographic validity result. Display names are not unique, and unsigned identity claims omit the identity header.
Source paths resolve through the same activity lookup and canonical-path fallbacks used by `get_post`.

Boundary: Read-only output; visibility, ordering, retraction and resolution behavior remain unchanged.
No canonical records, schema, membership rules or signature-verification policy change.

Sources: [controller](../../src/ForumRewrite/Http/ApiTextController.php), [application route and source lookup](../../src/ForumRewrite/Application.php), [route catalog](../../src/ForumRewrite/Http/ApiRouteCatalog.php), [API reference](../reference/agent_api.md#optional-thread-authors), [tests](../../tests/ThreadAuthorDetailsTest.php).
