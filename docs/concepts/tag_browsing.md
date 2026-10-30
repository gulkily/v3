# Tag browsing

Purpose: Find threads carrying a board tag or thread label without loading the entire collection into a page.
Scenario: A reader opens /tags/news or another tag on any site and follows native 25-thread pages ordered by last activity and root ID. Membership combines tags and labels once per root and excludes retracted and hidden-bootstrap threads. The index retains five previews per tag.
Sources: [page adapter](../../src/ForumRewrite/ReadModel/TagPage.php), [controller](../../src/ForumRewrite/Http/TagsPageController.php), [navigation](../../docs/reference/pagination.md).
