# Recent conversation

Purpose: Let readers catch up on individual public threads and replies without technical event details.
Scenario: Open Recent in forum navigation, scan up to 50 newest contributions, open a thread at the relevant post, then return or use native Previous/Next links. Equal timestamps use post IDs for stable positions. Invalid positions offer First page; reconnect and reload after network failure.
Visibility: Hidden/retracted posts, identity bootstrap and scripture expansion records are excluded; unsigned and unapproved public contributions remain eligible. Missing or excluded thread roots use neutral context. Recent itself has no personal read tracking or live polling; members catch up on what is new through Unread's [caught-up marks](caught_up_mark.md).
Sources: [reader](../../src/ForumRewrite/ReadModel/RecentPostRepository.php), [controller](../../src/ForumRewrite/Http/RecentPageController.php), [page](../../templates/pages/recent.php), [acceptance](../../tests/browser/recent.mjs).
Publication: Shared first page is generated; position URLs use the live reader. Recent is outside the offline-reader and high-traffic cache allowlists. See [publication](static_publication.md) and [release handoff](../plans/recent_activity/recent_activity_step4_implementation_summary.md#release-and-recovery).
