-- id: threads-by-reply-count
-- label: Threads by reply count
-- description: Show the most active indexed threads.
-- category: content
-- order: 30

SELECT subject, reply_count, last_activity_at, root_post_id
FROM threads
ORDER BY reply_count DESC
LIMIT 10
