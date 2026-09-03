-- id: activity-all
-- label: Activity: All
-- description: Reproduce the activity page's newest-first activity records.
-- category: activity
-- order: 80

SELECT activity.created_at, activity.kind, activity.record_family, activity.action_key,
       activity.post_id, activity.thread_id, activity.label, activity.board_tags_json,
       activity.author_identity_id, activity.source_path, activity.source_commit_sha,
       activity.id, activity.author_label, activity.author_profile_slug,
       activity.author_username_token, activity.author_is_approved
FROM activity
LEFT JOIN posts ON posts.post_id = activity.post_id
ORDER BY activity.created_at DESC, activity.post_id DESC, activity.id DESC
LIMIT 100
