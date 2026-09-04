-- id: recent-activity
-- label: Recent activity
-- description: Show the ten newest activity records.
-- category: activity
-- order: 50

SELECT created_at, kind, label, author_label
FROM activity
ORDER BY created_at DESC, id DESC
LIMIT 10
