-- id: approved-profiles
-- label: Approved profiles
-- description: Show approved profiles in the read model.
-- category: people
-- order: 40

SELECT username, profile_slug, post_count, thread_count
FROM profiles
WHERE is_approved = 1
ORDER BY username
LIMIT 20
