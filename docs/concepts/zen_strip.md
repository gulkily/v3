# Zen strip

Purpose: Open the board with Zen memes, so the first screen of zenmemes.com matches its name.
Scenario: a visitor opens the board's first page and sees a grid of Zen memes before anything else: the newest twelve threads that an approved member tagged with every tag the site profile names (`zenStripTags`; on zenmemes.com, #zen and #meme). An image thread shows its first image whole, at its own shape, large enough to read without opening it (the 480px copy, or the original on wide or high-density screens); a text thread shows its title. The grid has one column on a phone and two in the content column, never scrolls sideways, and grows as tall as its cards need. All, below it, opens the [tag page](tag_browsing.md) for those tags, /tags/zen+meme; it is meant to open a gallery of full-size images instead ([task](https://zenmemes.com/threads/thread-20261120173657-2cb6e813e62a6457e4cde6eccd4c5828)).
Sources: [query](../../src/ForumRewrite/ReadModel/ZenStrip.php), [controller](../../src/ForumRewrite/Http/BoardPageController.php), [cards](../../templates/partials/zen_strip.php), [styles](../../public/assets/site.css), [profile tags](../../src/ForumRewrite/SiteProfileRegistry.php).

Each tag counts only from an approved member: the root's own board tag when its signed author is approved, or a label an approved member wrote that is still on the thread. This is the #prompt rule of the Prompts page, applied to each tag. A guest's or pending key's tags still show on the thread and put it on the tag page, but not in the strip. Server HTML is the same for every viewer and needs no JavaScript. `FORUM_ZEN_STRIP_ENABLED` (on by default) turns the strip off; a site profile without `zenStripTags` shows none.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

*— Revised by Claude · 2026-11-20 UTC (grid) · Model: claude-opus-5-5*
