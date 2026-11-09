# Operator grant

Name: Signed operator grant
Purpose: Let an operator make another approved key an operator from the browser, with a signed record of who did it.
Scenario: An operator opens Tools → User Access → a key's page and presses "Make root". The confirmation names the key's username. The operator's browser signs the grant, and one commit saves the grant, its signature and an approval seed for the key. The seed names the grant in `Operator-Grant-ID`; the key's root approval page shows the grantor and links the grant.
Sources: `src/ForumRewrite/Write/LocalWriteService.php` (`prepareOperatorGrant`, `finalizePreparedOperatorGrant`), `src/ForumRewrite/Security/ApprovalPolicy.php` (`operatorGrantActors`), `templates/partials/operator_grant.php`, `public/assets/operator_grant.js`, `docs/specs/operator_grant_record_v1.md`.
Status: Implemented with write, route, page, browser and import tests, and a two-key local replay. Records are in `docs/plans/same_username_operator/same_username_operator_step4_implementation_summary.md`; production is not verified.

Name: Seed as the only authority
Purpose: Keep one source of operator power.
Scenario: Every operator check reads approval seeds, as before. The grant is evidence: a rebuild or reader that ignores grant records still decides operators correctly. Retiring a granted key ends its operator power, as for any seeded key. Instance import excludes grants with seeds, so an imported site starts with no operators.
Sources: `src/ForumRewrite/Security/ApprovalPolicy.php` (`isOperator`, `isActiveOperator`), `src/ForumRewrite/Import/ContentImportPlanner.php`, `docs/specs/user_approval_seed_record_v1.md`.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
