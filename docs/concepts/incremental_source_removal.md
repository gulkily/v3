# Incremental source removal

Purpose: Remove one post from the site in well under a second without rebuilding the read model.
Scenario: An operator runs `./v3 delete-record <post-id>` on a large site. The removal commit touches only the post, its owned labels and the receipt; the post's read-model row becomes the same placeholder a rebuild writes; its thread's label state is re-derived from the thread's remaining label files; only pages that can show the post are cleared. When the read model is not current, or the targeted update fails, the command falls back to a full rebuild.
Sources: [targeted update](../../src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php) (`applyPostRemoval`), [label file index](../../src/ForumRewrite/ReadModel/ThreadLabelFileIndex.php), [removal flow](../../src/ForumRewrite/Write/SourceLabelRemoval.php), [page clearing](../../src/ForumRewrite/Write/StaticArtifactInvalidator.php) (`invalidateRemovedPost`), [equivalence tests](../../tests/DeleteRecordCommandTest.php).
Equivalence: tests compare every read-model table and in-thread order with a fresh rebuild, except per-build values and the commits table, which no incremental write maintains.
Known limitations: identity and hidden posts always rebuild; a thread with tens of thousands of labels is slow because all of them are re-derived; the commits view catches up at the next rebuild.

*— Claude · 2026-11-04 UTC*
