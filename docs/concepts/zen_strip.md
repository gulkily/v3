# Zen strip

Purpose: Open the home page with a Zen meme, so the first screen of zenmemes.com matches its name.
Scenario: a visitor opens the home page, `/`, and sees one Zen meme before anything else: the featured meme, the one with the highest thread score (the number Top sort uses; the newest wins a tie). It comes from the threads that an approved member tagged with every tag the site profile names (`zenStripTags`; on zenmemes.com, #zen and #meme), up to twelve. An image thread shows its first image whole, as wide as the content column: a compressed copy chosen for the screen (see [Image serving](image_serving.md)), never the stored original, fetched before the page's other images, over a tiny blurred stand-in of itself that is built into the page so the box is not empty while it loads. A text thread shows its title. It links to its thread. Under it, one row of tiny square thumbnails shows the other Zen memes. The row fits one line and clips any overflow. With one Zen meme there is no row.

Gallery: the thumbnail row links to `/gallery/?view=zen`, which pages through all eligible threads in the strip's order. Each card links to its thread and shows the first available image at full column width, using a responsive copy that loads as it approaches the viewport. Threads without an available image show a linked title. Dark themes dim images in both views. With Gallery off, the thumbnail row and old gallery links lead to the ordinary [tag listing](tag_browsing.md) for the profile's Zen tags.

The module shows only on `/` with no view or sort chosen. The threads page (`/threads/`), a chosen view or sort, tag pages and later pages never show it. On a local dev server `/` is the Local development page, so open `/?home=1` to see it. In dark colour schemes its images dim through the theme variable `--media-dim` (1 by default, 0.85 where a theme declares a dark scheme); a new dark theme sets it itself.
Sources: [selection](../../src/ForumRewrite/ReadModel/ZenStrip.php), [homepage controller](../../src/ForumRewrite/Http/BoardPageController.php), [gallery controller](../../src/ForumRewrite/Http/RecentPageController.php), [shared card](../../templates/partials/zen_meme_card.php), [profile tags](../../src/ForumRewrite/SiteProfileRegistry.php).

Each tag counts only from an approved member: the root's own board tag when its signed author is approved, or a label an approved member wrote that is still on the thread. This is the #prompt rule of the Prompts page, applied to each tag. A guest's or pending key's tags still show on the thread and put it on the ordinary tag page. The strip and Zen gallery share the approval rule and render the same HTML for every viewer. They need no JavaScript. Both require `FORUM_ZEN_STRIP_ENABLED` and a site profile with `zenStripTags`.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

*— Revised by Claude · 2026-11-20 UTC (grid) · Model: claude-opus-5-5*

*— Revised by Claude · 2026-11-21 UTC (featured meme, home only) · Model: claude-fable-5-2*

*— Revised by Claude · 2026-11-22 UTC (compressed copies, stand-in) · Model: claude-fable-5-2*

*— Revised by Jiro · 2026-11-23 UTC (Zen gallery)*
