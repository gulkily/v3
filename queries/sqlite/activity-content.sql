-- id: activity-content
-- label: Activity: Content
-- description: Reproduce the activity page's visible content-only view.
-- category: activity
-- order: 90

SELECT activity.created_at, activity.kind, activity.record_family, activity.action_key,
       activity.post_id, activity.thread_id, activity.label, activity.board_tags_json,
       activity.author_identity_id, activity.source_path, activity.source_commit_sha,
       activity.id, activity.author_label, activity.author_profile_slug,
       activity.author_username_token, activity.author_is_approved
FROM activity
LEFT JOIN posts ON posts.post_id = activity.post_id
WHERE activity.board_tags_json NOT LIKE '%"identity"%'
  AND (activity.post_id IS NULL OR COALESCE(posts.is_hidden, 0) = 0)
ORDER BY activity.created_at DESC, activity.post_id DESC, activity.id DESC
LIMIT 100
