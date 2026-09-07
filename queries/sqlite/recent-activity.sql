-- id: recent-activity
-- label: Recent activity
-- description: Show the ten newest activity records.
-- category: activity
-- order: 50

SELECT label, author_label, kind, created_at
FROM activity
ORDER BY created_at DESC, id DESC
LIMIT 10
