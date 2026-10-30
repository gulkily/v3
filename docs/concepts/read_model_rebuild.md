# Derived read-model rebuild

Purpose: Reconstruct queryable site state from canonical records and their accepted history.
Scenario: An operator runs rebuild or build-static; canonical content, attribution, reactions and activity evidence are derived into SQLite. Candidate-based CLI paths validate before promotion; some write-maintenance paths rebuild directly. A completed derivation is distinct from static release activation.
Sources: [builder](../../src/ForumRewrite/ReadModel/ReadModelBuilder.php), [candidate validation](../../src/ForumRewrite/ReadModel/ReadModelCandidateBuilder.php), [promotion](../../src/ForumRewrite/ReadModel/ReadModelCandidatePromoter.php), [CLI](../../scripts/rebuild_read_model.php).
Known limitation: Several passes retain corpus-sized collections; the [QDB audit](../plans/qdb_memory_audit/qdb_memory_audit_findings.txt) documents evidence, not a delivered memory-capacity guarantee.

Commit indexing uses streamed raw tree changes for file counts, preserving Git's existing rename/merge defaults without computing line statistics. Activity evidence refresh uses a temporary file-backed manifest lookup shared across bounded batches, fetching only each action's source/signature/key paths. A failed manifest load rolls back its scratch transaction; missing history keeps existing fallback evidence. Scratch storage is removed after each refresh, including failures, and never becomes part of a read-model export or a later refresh. Full commit-detail views retain their existing manifest contract.
