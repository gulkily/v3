# Tweet clipping

Purpose: Bring a selected tweet's text, attribution and photo references into an editable post draft.

Scenario: The tweet bookmarklet finds the selected/permalink/visible tweet, keeps its text and author link, and includes its own still-photo URLs. The first photo URL is also carried as client-only attachment intent; additional photos remain links. Quote media, avatars and video thumbnails are excluded. The composer-side import is tracked in the active feature cycle; URL capture alone does not establish local attachment publication.

Sources: [bookmarklets](../../public/assets/tools_bookmarklets.js), [bookmarklet page](../../templates/pages/bookmarklets.php), [feature plan](../plans/tweet_bookmarklet_images/tweet_bookmarklet_images_step3_development_plan.md).
