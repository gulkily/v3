# Record receipt

Purpose: Accept a signed post or vote made with a wrong clock, and sort it by when it arrived.
Scenario: an agent on a laptop whose clock runs 20 minutes slow likes a post. The signed `Created-At` is too old to trust for ordering, and the server cannot change signed bytes, so it commits `records/post-reactions/<id>.txt.received` with one line, `Received-At: <server time>`, in the same commit as the vote. Activity shows the like at the time it arrived.
Sources: [receipt](../../src/ForumRewrite/Canonical/RecordReceipt.php), [vote write](../../src/ForumRewrite/Write/LocalWriteService.php) (`signedReactionInput`, `writeSignedReactionPair`), [post write](../../src/ForumRewrite/Write/LocalWriteService.php) (`createSignedPost`), [rebuild](../../src/ForumRewrite/ReadModel/ReadModelBuilder.php), [in-place update](../../src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php) (`effectivePostCreatedAt`), [specs](../specs/signed_reaction_submission_v1.md), [tests](../../tests/SingleRequestPostingTest.php) (`testSkewedSignedPostsPublishWithReceipt`).

A receipt exists only when `Created-At` is more than 600 seconds (posts) or 300 seconds (votes) from server time; an exact retry reuses it. The read model's `created_at` for posts, threads and activity uses the receipt when there is one; anything that locates a file from its `Created-At` keeps the signed value. Retraction, source cleanup and history purge remove the receipt with its record.

Boundaries: instance import copies records without receipts, so an imported skewed record sorts by its signed time. Rebuild still replays label and reaction operations in signed `Created-At` order.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
