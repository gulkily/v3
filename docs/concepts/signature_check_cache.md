# Signature check cache

Purpose: Avoid repeating OpenPGP checks that already succeeded.
Scenario: An operator imports the same instance twice. The first import checks each signed label and reaction with GPG and saves each success in `signature_checks.sqlite3` beside the read model. The second import, a rebuild or a page request finds those checks saved and starts no GPG process for them.

Keys hash the record bytes, signature, public key and expected fingerprint, so any changed byte checks again. Failed checks are never saved, because a GPG that could not run looks like a bad signature. The file is derived and safe to delete; a missing, locked or unreadable file only means GPG runs again. Import opens it beside the destination database before planning, including `--dry-run` and an explicit `--database-path`.

Uncached checks within one process share one GPG home per signer key: the first check imports the key, later checks run only `gpg --verify`. Homes sit under `forum-rewrite-gpg-keys-<pid>-<random>` in the temp directory and are removed when the process ends; a later process removes folders left by one that was killed.

Sources: [cache](../../src/ForumRewrite/Security/SignatureCheckCache.php), [reaction and label signatures](../../src/ForumRewrite/Security/ReactionSignature.php), [verifier](../../src/ForumRewrite/Security/OpenPgpSignatureVerifier.php), [import runner](../../src/ForumRewrite/Import/ContentImportRunner.php), [cache tests](../../tests/SignatureCheckCacheTest.php), [key reuse tests](../../tests/OpenPgpKeyReuseTest.php).
Plan: [import planning speed](../plans/import_planning_speed/import_planning_speed_step3_development_plan.md).

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
