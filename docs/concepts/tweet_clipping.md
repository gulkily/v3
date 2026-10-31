# Tweet clipping

Purpose: Bring a selected tweet's text, attribution and photo references into an editable post draft.

Scenario: The tweet bookmarklet finds the selected/permalink/visible tweet, keeps its text and author link, and includes its own still-photo URLs. The first photo URL is also carried as client-only attachment intent; additional photos remain links. Quote media, avatars and video thumbnails are excluded. The composer downloads the first photo without credentials, reuses the existing image preview/preparation, and waits for ordinary signed publication. Failed capture preserves source links and offers retry/removal/manual attachment. Live X page execution remains unverified in the implementation environment.

Sources: [bookmarklets](../../public/assets/tools_bookmarklets.js), [bookmarklet page](../../templates/pages/bookmarklets.php), [feature plan](../plans/tweet_bookmarklet_images/tweet_bookmarklet_images_step3_development_plan.md).
