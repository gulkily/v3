# SQLite Viewer Query Catalog Candidates

This is a follow-up list of useful presets to add to the SQLite viewer. The existing `Board: Liked + Newest` preset remains the default and is the parity reference for the main board.

## Interface view reproductions

- **Board: All + Newest** — reproduce the default all-threads view with the root-post and profile joins, hidden identity/bootstrap exclusion, pinned-first ordering, newest root-post ordering, and the same selected fields as the board.
- **Board: All + Oldest** — use the all-thread filters and joins, then order by pinned status followed by oldest root-post timestamp and root-post ID.
- **Board: All + Top** — use the all-thread filters and joins, then order by pinned status, descending thread score, and the newest-board tie-break.
- **Board: Liked + Oldest** — reproduce the liked-thread label and non-negative root-post-score constraints with oldest ordering.
- **Board: Liked + Top** — reproduce the liked-thread constraints with descending thread score and the newest-board tie-break.
- **Tags index** — group threads by the distinct union of board tags and thread labels, including each tag’s count and the five newest threads shown for that tag.
- **Tag page** — list all threads associated with one selected board or thread label, using the same hidden-record and ordering rules as the interface.
- **Thread detail** — return one thread, its root post, replies in sequence order, author/profile fields, labels, scores, and visibility fields used by the thread page.
- **Approved users directory** — list approved profiles with username, profile slug, post count, and thread count in directory order.
- **Username/profile lookup** — reproduce username-route resolution and show approved matches plus any unapproved matches exposed by the profile page.

## Activity views and statistics

- **Activity: All** — reproduce the activity page’s selected fields, post join, source metadata, and `created_at`, post ID, and activity ID ordering.
- **Activity: Content** — apply the content view’s exclusion of identity-tagged records and hidden posts.
- **Activity: Identity** — show identity-tagged activity, including the same post/source fields.
- **Activity: Bootstrap** — show identity-plus-internal activity.
- **Activity: Approval** — show identity-plus-approval activity.
- **Activity counts by kind** — count activity rows grouped by `kind`, with newest event time and total count.
- **Activity counts by record family** — count activity grouped by `record_family`, including the proportion or count linked to posts versus threads or configuration records.
- **Activity by day** — summarize activity volume by calendar day, optionally split by content, identity, approval, and other classifications.
- **Activity source coverage** — count rows with source paths, source commits, and signature paths/statuses to assess auditability.
- **Recent activity summary** — return total activity rows, newest timestamp, oldest timestamp, and counts for the visible/content subset.

## Content and engagement statistics

- **Content totals** — count posts, threads, replies, approved profiles, and username routes.
- **Posts by board tag** — expand `board_tags_json` and count posts per board tag, excluding hidden identity/bootstrap content when reproducing public-facing counts.
- **Threads by label** — expand `thread_labels_json` and count threads per label, including `like` and `pinned`.
- **Most active threads** — rank threads by reply count, with last activity and root-post fields.
- **Highest-scoring threads** — rank threads by `score_total`, with the same tie-break as the Top board view.
- **Posting volume over time** — summarize posts by day or month using `created_at`.
- **Participation by author** — aggregate post and thread counts by author label/profile, with an explicit approved-only variant.
- **Hidden/bootstrap inventory** — count identity-tagged posts and threads and show their labels so technical users can reconcile hidden records.

## Read-model and snapshot diagnostics

- **Table row counts** — reproduce the System State row-count summary for the viewer’s current database.
- **Read-model metadata** — show schema version, repository head, rebuild time, rebuild reason, and other `metadata` values.
- **Snapshot freshness** — return the database rebuild timestamp and repository snapshot identifier used by the Backup page.
- **Analysis coverage** — count post-analysis, Unicode-risk, generated-response, and related diagnostic rows by status where those tables exist.
- **Referential spot checks** — identify threads without root posts, activity rows without resolvable posts/threads, and username routes without profiles.

## Catalog conventions

- Give each preset a short label, a plain-language description, and a stable category.
- Prefer presets that reproduce an existing page exactly before adding exploratory statistics.
- Keep result sets bounded in the viewer; aggregation presets should return summaries rather than raw unbounded rows.
- Preserve the existing client-side, read-only execution contract.
- Full-text search, FTS indexes, persistent browser caching, and server-side query execution are not part of this catalog yet.
