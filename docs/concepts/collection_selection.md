# Collection selection

Purpose: Choose the records needed by a view or job while preserving eligibility, ordering and complete-output contracts.

Scenario: A live tag page selects at most 25 eligible thread IDs and loads only those bodies. Its ordering and navigation remain stable. QDB welcome streams eligibility/order metadata and hydrates at most eight distinct selected roots. QDB Latest, Top, Leetness and Search count/select eligible IDs in a read snapshot and hydrate at most 25 roots. Search still scans transient bodies using the existing literal-match rule; bounded retention does not imply bounded scanning. Offline snapshot candidate selection now streams rows, retaining all pins plus recent threads, so a limit on recent threads is not a total-content limit.

Measurement: Distinguish source rows examined, rows/bodies materialized or transferred, and results used. A SQL LIMIT bounds returned rows, not necessarily the database scan. A streaming loop can bound retained memory while still transferring unnecessary body bytes. Complete exports, RSS and import validation may require full traversal; caps would change their contract.

Sources: [tag selection](../../src/ForumRewrite/ReadModel/TagPage.php), [shared thread reader](../../src/ForumRewrite/ReadModel/ThreadRepository.php), [QDB numbered selection](../../src/ForumRewrite/ReadModel/QdbPage.php), [QDB pagination](../../src/ForumRewrite/Qdb/QdbBoardPolicy.php), [offline selection](../../src/ForumRewrite/Offline/PublicOfflineSnapshotBuilder.php), [audit findings and proposed corrections](../plans/load_before_selection_audit/findings.txt).

Status: Describes current mixed behavior. QDB welcome, numbered-page selection and streaming offline candidate selection are implemented; remaining audit recommendations are tracked in the [fix checklist](../plans/selection_fixes_checklist.md); audit measurements are local synthetic evidence, not production capacity acceptance.

Local capacity evidence: The [capacity study](../plans/application_capacity_study/capacity_report.txt) measures normal QDB latest/top/search at 10 MiB PHP for 1000 ordinary records and 72 MiB for 10000, with 25 returned IDs in both cases. Exact internal loaded counts remain uninstrumented; a small result is not a memory bound.

*— Codex · 2026-11-01 UTC*

QDB follow-up evidence: [HTTP acceptance](../plans/qdb_page_selection/capacity_report.txt) records 72 ordinary requests at 1000/10000/40000 records with exact IDs/bodies/counts and 2–4 MiB peak PHP allocation. The 40000 prepared model has canonical-body provenance but does not establish full rebuild capacity. Maximum-valid numbered views/search were about 82.1 MiB under 128 MiB; PHP 8.3/FastCGI remains a separate host check. Branch-local schema 41 startup recovery (integrated as schema 43 alongside main’s message/Recent indexes) and shared-consumer checks are recorded in the [implementation summary](../plans/qdb_page_selection/qdb_page_selection_step4_implementation_summary.md).

*— Codex · 2026-11-02 UTC*

Board thread search: on non-QDB profiles, `/search?q=` selects listed roots whose effective title or opening body contains the query, with QDB's case-insensitive literal rule (`LiteralTextMatch`, now shared by both searches). Tag pages and board search share `ThreadKeysetPage`: board eligibility (not retracted, not identity bootstrap), activity/ID descending boundaries, direction probes and at most 25 hydrated roots, paged with the shared Previous/Next navigation. Like QDB search, it still scans every listed root's title and body. Replies are not searched yet. Sources: [board search selection](../../src/ForumRewrite/ReadModel/ThreadSearchPage.php), [shared keyset selection](../../src/ForumRewrite/ReadModel/ThreadKeysetPage.php), [one-shot record](../plans/board_thread_search/board_thread_search_one_shot.md).

*— Claude (claude-opus-5-5) · 2026-11-03 UTC*
