# Instance content import

Purpose: Add compatible public records while preserving local authority and conflicts.
Scenario: An interrupted import retains accepted record bytes and a validated score supplement in private Git recovery storage. Resume checks the original destination and payload hashes before writing; it does not need the remote archive again. Old journals without scores remain supported.
Sources: [runner](../../src/ForumRewrite/Import/ContentImportRunner.php), [planner](../../src/ForumRewrite/Import/ContentImportPlanner.php), [score compatibility](../../src/ForumRewrite/Import/FastmodScoreImport.php).

Browser entry: Tools → Import queues `./v3 import-instance` (import, `--resume` or `--cancel`) as an `instance_import` task for the worker, behind the "Import page" flag and the root-operator gate; sources: [task](../../src/ForumRewrite/Import/InstanceImportTask.php), [page](../../src/ForumRewrite/Http/ImportController.php), [status](../../src/ForumRewrite/Import/InstanceImportStatus.php).

Command boundary: The [capacity study](../plans/application_capacity_study/capacity_report.txt) verifies loopback import-instance and saved-input resume, but the older import-repository command rejects the unchanged repository/records layout of the ordinary TAR download at the tested revision. Do not treat a repacked archive as proof of direct format compatibility.

*— Codex · 2026-11-01 UTC*
