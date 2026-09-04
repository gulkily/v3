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
