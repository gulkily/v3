# Zen strip

Purpose: Open the home page with a Zen meme, so the first screen of zenmemes.com matches its name.
Scenario: a visitor opens the home page, `/`, and sees one Zen meme before anything else: the featured meme, the one with the highest thread score (the number Top sort uses; the newest wins a tie). It comes from the threads that an approved member tagged with every tag the site profile names (`zenStripTags`; on zenmemes.com, #zen and #meme), up to twelve. An image thread shows its first image whole, as wide as the content column (the 480px copy, or the original on wide or high-density screens); a text thread shows its title. It links to its thread. Under it, one row of tiny square thumbnails shows the other Zen memes. The whole row is a single link to the [tag page](tag_browsing.md) for those tags, /tags/zen+meme, and there is no separate All link; it is meant to open a gallery of full-size images instead ([task](https://zenmemes.com/threads/thread-20261120182535-0db81c46de8339600736c7ae1fec6e37)). The row is one line: it never scrolls sideways or wraps, and cuts off what doesn't fit. With one Zen meme there is no row.

The module shows only on `/` with no view or sort chosen. The threads page (`/threads/`), a chosen view or sort, tag pages and later pages never show it. On a local dev server `/` is the Local development page, so open `/?home=1` to see it. In dark colour schemes its images dim through the theme variable `--media-dim` (1 by default, 0.85 where a theme declares a dark scheme); a new dark theme sets it itself.
Sources: [query](../../src/ForumRewrite/ReadModel/ZenStrip.php), [controller](../../src/ForumRewrite/Http/BoardPageController.php), [cards](../../templates/partials/zen_strip.php), [styles](../../public/assets/site.css), [route](../../src/ForumRewrite/Application.php), [profile tags](../../src/ForumRewrite/SiteProfileRegistry.php).

Each tag counts only from an approved member: the root's own board tag when its signed author is approved, or a label an approved member wrote that is still on the thread. This is the #prompt rule of the Prompts page, applied to each tag. A guest's or pending key's tags still show on the thread and put it on the tag page, but not in the strip. Server HTML is the same for every viewer and needs no JavaScript. `FORUM_ZEN_STRIP_ENABLED` (on by default) turns the strip off; a site profile without `zenStripTags` shows none.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

*— Revised by Claude · 2026-11-20 UTC (grid) · Model: claude-opus-5-5*

*— Revised by Claude · 2026-11-21 UTC (featured meme, home only) · Model: claude-fable-5-2*
