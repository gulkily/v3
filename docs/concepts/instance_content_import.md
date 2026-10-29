# Instance content import

Purpose: Add compatible public records while preserving local authority and conflicts.
Scenario: An interrupted import retains accepted record bytes and a validated score supplement in private Git recovery storage. Resume checks the original destination and payload hashes before writing; it does not need the remote archive again. Old journals without scores remain supported.
Sources: [runner](../../src/ForumRewrite/Import/ContentImportRunner.php), [planner](../../src/ForumRewrite/Import/ContentImportPlanner.php), [score compatibility](../../src/ForumRewrite/Import/FastmodScoreImport.php).
