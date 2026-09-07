-- id: board-all-newest
-- label: Board: All + Newest
-- description: Reproduce the all-threads board view with pinned-first newest ordering.
-- category: board
-- order: 60

SELECT threads.subject,
       threads.body_preview,
       posts.author_label,
       threads.root_post_created_at,
       threads.last_activity_at,
       threads.reply_count,
       threads.score_total,
       posts.post_score_total AS root_post_score_total,
       threads.root_post_id,
       posts.author_profile_slug,
       threads.board_tags_json,
       threads.thread_labels_json,
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
ORDER BY CASE WHEN EXISTS (
  SELECT 1
  FROM json_each(threads.thread_labels_json)
  WHERE json_each.value = 'pinned'
) THEN 0 ELSE 1 END,
         threads.root_post_created_at DESC,
         threads.root_post_id DESC
LIMIT 100
