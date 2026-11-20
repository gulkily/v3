# Thread labels

Purpose: Let authors tag a thread with hashtags on purpose, without a passing mention moving their post.
Scenario: An author writes "this put a task in the strip (#zen and #meme)" and ends the post with `#issue #meta`. The thread gets issue and meta. #zen and #meme stay links to their [tag pages](tag_browsing.md) but don't label the thread, so it stays out of the [Zen strip](zen_strip.md). The composer shows "Tags: #issue #meta" under the text box before they post.
Sources: [the rule](../../src/ForumRewrite/Support/ThreadLabels.php) (`selfTags`), [write path](../../src/ForumRewrite/Write/LocalWriteService.php) (`extractThreadLabelsFromBody`), [composer line](../../public/assets/hashtag_tags.js), [shared examples](../../tests/fixtures/hashtag_self_tags_examples.txt), [plan](../plans/hashtag_self_tags/hashtag_self_tags_step2_feature_description.md).

A hashtag labels its thread when it sits on a line made only of hashtags, anywhere in the post, or in the run of hashtags that ends the post's last non-blank line. Every other hashtag is a reference. Hashtags in code, links, URLs and quoted (`>`) lines never label, and neither do all-digit hashtags or colour-like ones (three or six hex digits, so #fff but also #bad). A comma or full stop after a hashtag makes its line prose.

Labels are records written when a post is saved (`records/thread-labels/`), so a rule change affects new posts only. `FORUM_HASHTAG_SELF_TAGS_ENABLED`, on by default, selects this rule; off, every valid hashtag in a new post labels its thread, as before 2026-11-20. A mistaken label is fixed with a reply.

A tag-only reply (only hashtags) still collapses out of the conversation through `ThreadLabels::isTagOnly`, which keeps the older rule. A reply of only `#2024` therefore collapses although it adds no label, and its Activity entry names 2024. The PHP rule and `hashtag_tags.js` are kept in step by tests that both read the shared examples.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*
