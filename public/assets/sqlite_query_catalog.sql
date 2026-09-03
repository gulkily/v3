-- SQLite Viewer Query Catalog
-- Generated from repository query sources.

-- -----------------------------------------------------------------------------
-- Board: Liked + Newest
-- Reproduce the default board view with its joins, filters, fields, and ordering.
-- -----------------------------------------------------------------------------

-- id: board-liked-newest
-- label: Board: Liked + Newest
-- description: Reproduce the default board view with its joins, filters, fields, and ordering.
-- category: board
-- order: 10

SELECT threads.root_post_id,
       threads.root_post_created_at,
       threads.last_activity_at,
       threads.subject,
       threads.body_preview,
       threads.reply_count,
       threads.score_total,
       threads.board_tags_json,
       threads.thread_labels_json,
       posts.author_label,
       posts.author_profile_slug,
       posts.post_score_total AS root_post_score_total,
       profiles.username_token AS author_username_token,
       COALESCE(profiles.is_approved, 0) AS author_is_approved
FROM threads
JOIN posts ON posts.post_id = threads.root_post_id
LEFT JOIN profiles ON profiles.identity_id = posts.author_identity_id
WHERE NOT EXISTS (
  SELECT 1
  FROM json_each(threads.board_tags_json)
  WHERE json_each.value = 'identity'
)
AND EXISTS (
  SELECT 1
  FROM json_each(threads.thread_labels_json)
  WHERE json_each.value = 'like'
)
AND posts.post_score_total >= 0
ORDER BY CASE WHEN EXISTS (
  SELECT 1
  FROM json_each(threads.thread_labels_json)
  WHERE json_each.value = 'pinned'
) THEN 0 ELSE 1 END,
         threads.root_post_created_at DESC,
         threads.root_post_id DESC

-- -----------------------------------------------------------------------------
-- Recent posts
-- Show the ten newest indexed posts.
-- -----------------------------------------------------------------------------

-- id: recent-posts
-- label: Recent posts
-- description: Show the ten newest indexed posts.
-- category: content
-- order: 20

SELECT post_id, created_at, subject, author_label
FROM posts
ORDER BY created_at DESC
LIMIT 10

-- -----------------------------------------------------------------------------
-- Threads by reply count
-- Show the most active indexed threads.
-- -----------------------------------------------------------------------------

-- id: threads-by-reply-count
-- label: Threads by reply count
-- description: Show the most active indexed threads.
-- category: content
-- order: 30

SELECT root_post_id, subject, reply_count, last_activity_at
FROM threads
ORDER BY reply_count DESC
LIMIT 10

-- -----------------------------------------------------------------------------
-- Approved profiles
-- Show approved profiles in the read model.
-- -----------------------------------------------------------------------------

-- id: approved-profiles
-- label: Approved profiles
-- description: Show approved profiles in the read model.
-- category: people
-- order: 40

SELECT profile_slug, username, post_count, thread_count
FROM profiles
WHERE is_approved = 1
ORDER BY username
LIMIT 20

-- -----------------------------------------------------------------------------
-- Recent activity
-- Show the ten newest activity records.
-- -----------------------------------------------------------------------------

-- id: recent-activity
-- label: Recent activity
-- description: Show the ten newest activity records.
-- category: activity
-- order: 50

SELECT created_at, kind, label, author_label
FROM activity
ORDER BY created_at DESC, id DESC
LIMIT 10

