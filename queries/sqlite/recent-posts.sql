-- id: recent-posts
-- label: Recent posts
-- description: Show the ten newest indexed posts.
-- category: content
-- order: 20

SELECT subject, author_label, created_at, post_id
FROM posts
ORDER BY created_at DESC
LIMIT 10
