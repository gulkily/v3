-- id: activity-counts-by-kind
-- label: Activity counts by kind
-- description: Summarize activity volume by kind with the newest event time.
-- category: statistics
-- order: 100

SELECT kind, COUNT(*) AS activity_count, MAX(created_at) AS newest_activity_at
FROM activity
GROUP BY kind
ORDER BY activity_count DESC, kind
LIMIT 50
