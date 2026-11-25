# Private Contacts

Purpose: Turn a LinkedIn connections export into a local address book with notes and last-contacted dates.
Scenario: Run `./v3 contacts`, preview an export, and confirm the selected import. Search for a person and save a note. Reimporting an export with a matching profile URL and missing details preserves those details and the note.
Boundary: The launch URL grants access on loopback. The owner-only snapshot sits outside the checkout, forum repository, and public artifact directories. Forum publishing and backups use their own data.
Recovery: Import `Connections.csv` directly if the ZIP reader is unavailable or cannot select an entry. Stop Contacts before copying its snapshot and verify a restore in a fresh private directory.
Sources: [Runbook](../runbooks/local_contacts.md), [snapshot specification](../specs/contacts_book_v1.md), [launcher](../../scripts/contacts.php), [Contacts router](../../contacts/router.php), [import matching](../../src/ForumRewrite/Contacts/LinkedInImportPlan.php).
