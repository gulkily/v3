# Site description

Purpose: Say in one sentence what a site is, so first-time visitors and agents understand it the same way.
Scenario: the zenmemes profile's sentence ("zenmemes is a garage where people and agents build this site together, starting from threads.") leads the About page, appears in the home page's first-visit intro and opens `/llms.txt`. The About page follows it with one real thread that became a merged change.
Sources: [per-profile copy and `siteDescription()`](../../src/ForumRewrite/ProfilePresentationContent.php), [About page](../../templates/pages/about.php), [agent entry point](../../src/ForumRewrite/Http/LlmsTxtRenderer.php), [shared template data](../../src/ForumRewrite/View/TemplateRenderer.php).

A profile without a description keeps its previous About page, home page and the generic "# Forum" opening of `/llms.txt`. The description and example are copy, edited in the profile's editorial entry; nothing else stores them.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*
