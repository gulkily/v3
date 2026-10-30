# Tag browsing

Purpose: Find threads carrying a board tag or thread label without loading the entire collection into a page.
Scenario: A reader opens /tags/news or another tag on any site and follows native 25-thread pages ordered by last activity and root ID. Membership combines tags and labels once per root and excludes retracted and hidden-bootstrap threads. The index retains five previews per tag.
Sources: [page adapter](../../src/ForumRewrite/ReadModel/TagPage.php), [controller](../../src/ForumRewrite/Http/TagsPageController.php), [navigation](../../docs/reference/pagination.md).

Offline tag pages show at most 25 saved threads. Their positions include the tag, snapshot generation time and saved membership/order fingerprint; online, foreign or stale positions offer saved First page or reconnect/retry. The offline cache revision changes with this reader contract. Saved snapshot contents and generation limits are unchanged.
