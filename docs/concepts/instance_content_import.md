# Instance content import

Purpose: Add compatible public records while preserving local authority and conflicts.
Scenario: An interrupted import retains accepted record bytes and a validated score supplement in private Git recovery storage. Resume checks the original destination and payload hashes before writing; it does not need the remote archive again. Old journals without scores remain supported.
Sources: [runner](../../src/ForumRewrite/Import/ContentImportRunner.php), [planner](../../src/ForumRewrite/Import/ContentImportPlanner.php), [score compatibility](../../src/ForumRewrite/Import/FastmodScoreImport.php).

Approvals: by default approval and invitation posts and their receipts stay out with other instance authority. `--with-approvals` imports them (never seeds); they stay inert until a key that wrote them is approved locally, and `./v3 approval suggest-roots` then names the roots to seed. Source invitations with unused capacity become redeemable once their issuer is approved here.

Known instances: [`resources/known_instances.txt`](../../resources/known_instances.txt), read by [`KnownInstances`](../../src/ForumRewrite/Import/KnownInstances.php), names public instances for the command line, Tools → Import and the local start page; each site leaves out its own address.

Browser entry: Tools → Import queues `./v3 import-instance` (import, `--resume` or `--cancel`) as an `instance_import` task for the worker, behind the "Import page" flag and the root-operator gate; sources: [task](../../src/ForumRewrite/Import/InstanceImportTask.php), [page](../../src/ForumRewrite/Http/ImportController.php), [status](../../src/ForumRewrite/Import/InstanceImportStatus.php).

Signature checks: planning reuses successful checks saved beside the destination database, and imports each signer key into GPG once per process; see [signature check cache](signature_check_cache.md).

Command boundary: The [capacity study](../plans/application_capacity_study/capacity_report.txt) verifies loopback import-instance and saved-input resume, but the older import-repository command rejects the unchanged repository/records layout of the ordinary TAR download at the tested revision. Do not treat a repacked archive as proof of direct format compatibility.

*— Codex · 2026-11-01 UTC*

*— Claude · 2026-11-17 UTC (signature checks line) · Model: Claude Opus 5.5*
