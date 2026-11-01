# Source history links

Purpose: Let readers open the exact committed bytes of a canonical record and the commit that introduced them.
Scenario: `/source/current/<path>` serves the working-tree record; `/source/blob/<commit>/<path>` serves the record at a commit; `/source/commits/<commit>` lists a commit's files. Activity pages and record pages link to these. A retracted post's path returns the deletion notice at every identity. When Git no longer knows a commit because a history purge rewrote it, the routes resolve the identity through the history rewrite records and name the resolved commit in the response.
Sources: [controller](../../src/ForumRewrite/Http/SourceFileController.php), [path and commit validators](../../src/ForumRewrite/Canonical/SourcePathValidator.php), [commit map resolver](../../src/ForumRewrite/Retraction/CommitMap.php), [record spec](../specs/history_rewrite_record_v1.md).
Known limitation: A commit that no record maps, such as one from a repository restored from backup, is simply not found.
