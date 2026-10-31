# Collection selection

Purpose: Choose the records needed by a view or job while preserving eligibility, ordering and complete-output contracts.

Scenario: A live tag page selects at most 25 eligible thread IDs and loads only those bodies. Its ordering and navigation remain stable. QDB welcome streams eligibility/order metadata and hydrates at most eight distinct selected roots. QDB numbered pages still load every eligible body before selecting 25; their small visible result does not bound input work. Offline snapshots retain all pins plus recent threads, so a limit on recent threads is not a total-content limit.

Measurement: Distinguish source rows examined, rows/bodies materialized or transferred, and results used. A SQL LIMIT bounds returned rows, not necessarily the database scan. A streaming loop can bound retained memory while still transferring unnecessary body bytes. Complete exports, RSS and import validation may require full traversal; caps would change their contract.

Sources: [tag selection](../../src/ForumRewrite/ReadModel/TagPage.php), [shared thread reader](../../src/ForumRewrite/ReadModel/ThreadRepository.php), [QDB pagination](../../src/ForumRewrite/Qdb/QdbBoardPolicy.php), [offline selection](../../src/ForumRewrite/Offline/PublicOfflineSnapshotBuilder.php), [audit findings and proposed corrections](../plans/load_before_selection_audit/findings.txt).

Status: Describes current mixed behavior. QDB welcome selection is implemented; remaining audit recommendations are tracked in the [fix checklist](../plans/selection_fixes_checklist.md); audit measurements are local synthetic evidence, not production capacity acceptance.
