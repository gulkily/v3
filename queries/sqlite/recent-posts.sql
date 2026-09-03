-- id: recent-posts
-- label: Recent posts
-- description: Show the ten newest indexed posts.
-- category: content
-- order: 20

SELECT post_id, created_at, subject, author_label
FROM posts
ORDER BY created_at DESC
LIMIT 10
