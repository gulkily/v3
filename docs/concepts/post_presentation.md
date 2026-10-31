# Post presentation and visibility

Purpose: Present public contributions with existing author attribution, readable content and links to their source conversation.
Scenario: A Recent preview reuses shared author/time metadata and plain-text title/excerpt handling, then links to the thread, anchored to the reply; a post whose thread is unavailable links to its own page. Visible replies can remain readable after their thread root becomes unavailable; the preview does not expose excluded root text.
Sources: [Recent entry](../../templates/partials/recent_entry.php), [renderer metadata helpers](../../src/ForumRewrite/View/TemplateRenderer.php), [title handling](../../src/ForumRewrite/Support/ThreadTitle.php), [thread reader](../../src/ForumRewrite/ReadModel/ThreadRepository.php), [Recent eligibility](../../src/ForumRewrite/ReadModel/RecentPostRepository.php).
Boundary: Recent does not change attribution, approval, post deletion or retraction policy. Profile-history counting rules are not a public-contribution membership filter.
