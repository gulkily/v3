# Tour snapshot

Purpose: Show visitors the site as a scroll of working pages without rendering any of them per visit.
Scenario: `resources/tour.txt` lists features and essays; a refresh renders each listed route logged-out and saves it under `public/assets/tour/` with the tour page; visitors load only those files. Refresh starts after deploys, rebuilds, retraction sweeps, cron, or from Tools → Operations.
Sources: [snapshots](../../src/ForumRewrite/Tour/TourSnapshots.php), [tour file](../../src/ForumRewrite/Tour/TourFile.php), [worker](../../src/ForumRewrite/Host/SharedRenderWorker.php), [reference](../reference/tour_snapshots.md).
Related: high-traffic cached pages use the same render, but are served at the page's own URL and only for `/`, `/threads/`, `/about/` and thread pages; a tour snapshot is never served at its page's URL.
