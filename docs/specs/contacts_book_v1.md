# Contacts book v1

`contacts.txt` is the private snapshot for one local Contacts book. It stores
imported connections and their user-edited notes and last-contacted dates. The
launcher checks the file before opening the book. See the [backup and restore
runbook](../runbooks/local_contacts.md#back-up-and-restore).

## Text format

The file is UTF-8 with LF line endings and a final LF. It starts with
`Contacts-Version: 1`. An empty book has just this version line. Each record
starts after a `%%` separator on its own line. The first record header is
`Contact: <ID>`, where the ID is unique and contains 32 lowercase hexadecimal
characters.

| Header | Value |
| --- | --- |
| `First-Name`, `Last-Name` | Imported name. At least one has a value. |
| `Profile-URL` | Recognized LinkedIn profile URL. |
| `Email`, `Company`, `Position` | Imported contact and work details. |
| `Connected-On` | Date or timestamp as supplied by the export. |
| `Last-Contacted` | User-entered calendar date in `YYYY-MM-DD` form. |

Headers use `Key: value`. A header key appears at most once per record.
Fields with empty values are omitted on write. A blank line after the headers
starts the note body, which can be empty. The serializer prefixes a `%` to
each note line beginning with `%`. The reader removes one leading `%` from
note lines beginning with `%%`. This preserves literal separator lines in
notes.

## Write and restore

The snapshot is a regular file owned by the data directory's owner, with mode
`0600` and one hard link. Contacts locks the book, checks the revision for
the proposed write, and replaces the snapshot atomically from the same
directory. A preview or edit based on an older revision pauses for a fresh
preview or reopened book.

For a stopped-book backup, copy `contacts.txt`. The lock and temporary import
previews are recreated as needed during normal use.

[Snapshot reader and writer](../../src/ForumRewrite/Contacts/ContactBook.php)
