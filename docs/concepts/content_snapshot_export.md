# Content snapshot export

Purpose: Move committed public content and reusable score results through ordinary downloads.
Scenario: A TAR or ZIP download adds an allowlisted Fastmod score supplement automatically. Changing or pruning scores changes the content archive cache identity even without a Git commit; image archives do not depend on scores. SQLite statement snapshots include committed WAL data. Missing score stores export an empty supplement without creating a database.
Sources: [archive builder](../../src/ForumRewrite/Images/ImageBackup.php), [score snapshot](../../src/ForumRewrite/Scoring/FastmodScoreSnapshot.php), [format](../specs/fastmod_score_transfer_v1.md).
