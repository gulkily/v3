-- id: approved-profiles
-- label: Approved profiles
-- description: Show approved profiles in the read model.
-- category: people
-- order: 40

SELECT profile_slug, username, post_count, thread_count
FROM profiles
WHERE is_approved = 1
ORDER BY username
LIMIT 20
