-- id: content-totals
-- label: Content totals
-- description: Summarize row counts for the primary content and profile tables.
-- category: statistics
-- order: 110

SELECT 'posts' AS table_name, COUNT(*) AS row_count FROM posts
UNION ALL
SELECT 'threads', COUNT(*) FROM threads
UNION ALL
SELECT 'profiles', COUNT(*) FROM profiles
UNION ALL
SELECT 'username_routes', COUNT(*) FROM username_routes
ORDER BY row_count DESC, table_name
LIMIT 20
