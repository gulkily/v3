# Fastmod score supplement v1

Path: fastmod-scores.txt at the content archive repository root
Format: Key: value plain text, UTF-8, LF lines
Header: Format: fastmod-scores-v1
Limits: 16 MiB, 50000 rows, 64 named scores per row, 256 bytes per field line

After the header, each row is separated by one blank line. Fields are Post,
Content-Hash, Rubric-Revision, Created-At, Updated-At and Score-<rubric-id>.
Hashes/revisions are lowercase SHA-256; post IDs are 1–128 ASCII letters,
numbers, underscores or hyphens. Rubric IDs start with a lowercase letter and
contain up to 64 lowercase letters, numbers, underscores or hyphens.
Probabilities are finite numbers from 0 through 1. Timestamps use
YYYY-MM-DDTHH:MM:SS+HH:MM with valid dates; update cannot precede creation.
No workflow state, failure, prompt, secret, policy or suppression fields travel.

Malformed or unknown-version supplements are rejected as a whole, independently
of valid content. Duplicate row keys are retained by decoding for import reporting;
destination rows win, and the first eligible source row wins among duplicates.
Existing archives without this file stay supported. Canonical signed bytes are
unchanged; this generated advisory supplement is not an authority record.

Exports include only completed nonlegacy named score sets for committed posts
whose current content hash matches. Source rows are sorted by post/hash/revision;
named probabilities are sorted by rubric ID. Empty exports contain only the
header. Source SQLite reads use a consistent statement snapshot including WAL;
its encoded digest participates in content archive freshness.

Imports require exactly the destination selected rubric IDs and composition
revision. Rows are insert-only, with source marked import and original ages
preserved. Unknown fields and duplicate fields reject the whole supplement;
duplicate row keys count separately. No canonical record or signature changes.
