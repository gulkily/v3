-- id: threads-by-reply-count
-- label: Threads by reply count
-- description: Show the most active indexed threads.
-- category: content
-- order: 30

SELECT root_post_id, subject, reply_count, last_activity_at
FROM threads
ORDER BY reply_count DESC
LIMIT 10
