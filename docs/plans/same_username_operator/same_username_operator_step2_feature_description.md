> **Feature plan:** [Step 1](./same_username_operator_step1_solution_assessment.md) · [Step 2](./same_username_operator_step2_feature_description.md) · [Step 3](./same_username_operator_step3_development_plan.md) · [Step 4](./same_username_operator_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/same_username_operator`; [host ledger](../effort_estimation_trial.md#cycle-v3same_username_operator). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.45**

Status: approved on 2026-11-09 ("Approved step 2"), including the import revision. Rewritten on 2026-11-09 for [Step 1 Revision 1](./same_username_operator_step1_solution_assessment.md#revision-1--a-make-root-button-current-direction), Option E. The first version described Option B, an automatic grant on same-name approval; the maintainer replaced it before approving Step 2. The cycle keeps its ID, `v3/same_username_operator`, for the study.

**Concepts touched:** [approval acceptance](../../concepts/approval_acceptance.md), [key retirement](../../concepts/key_retirement.md). Proposed addition: **operator grant**, a new entry in `docs/concepts/`. An operator signs a request that makes another approved key an operator, and the server writes an approval seed for it.

## Problem

Operator power comes only from approval seeds, which only `./v3 approval seed` writes on the server. The maintainer's second `ilyag` key cannot use the Feature flags page, and every new device key will need SSH to fix the same way.

## User stories

- As an operator, I want a "Make root" button on a key's access page, so that I can make my other keys operators from the browser.
- As an operator, I want the confirmation to name the key's username, so that I do not grant root to a look-alike key.
- As anyone reading a key's root approval page, I want to see who granted root and when, so that every operator traces back to a signed request or a server command.

## Core requirements

- Only an active operator sees the button and can use it. It appears only on approved, unretired keys that are not operators yet.
- The operator signs the request in the browser, as for a flag change. The server checks the signature and operator status again when it saves.
- One commit saves the signed request, its signature, and the new approval seed. The seed reason names the operator and the request.
- Every operator check keeps reading seeds alone. Rebuilds from records keep the new key an operator.
- Removal stays as today: retiring the key ends its operator power.
- Instance import keeps granting no root. It already excludes `records/approval-seeds/` and `records/feature-flag-changes/` as instance authority; the new grant records join that list. An imported instance still starts with no operators until its owner runs `./v3 approve`, and after that the button works there too.

## Delivery scope

- Work type: application change.
- Files expected: `LocalWriteService.php` (prepare and finalize, as for flag changes), a new signed record type with its parser and `CanonicalPathResolver` path, the route and browser script, `profile_access.php`, `root_approval.php`, specs `user_approval_seed_record_v1.md` and a new grant record spec, `ArchiveRecordCatalog.php` and `ContentImportPlanner.php`, the concept file, `docs/reference/v3_cli.md`, tests, and this cycle's plans and ledger entry.
- No feature flag, unless the maintainer asks for one at review.

## Completion boundary

- Normal entry: an operator, signed in, opens Tools → User Access → the key's page and presses "Make root".
- End-to-end outcome: the key's root approval page shows the grant. Signed in with that key, the Feature flags switches work. A full rebuild from records keeps it an operator.
- Recovery: if signing or saving fails, nothing is written and the page shows the error; pressing the button again retries. A stale page, where the key already became an operator, shows that instead of a second grant.
- Release condition: merged and deployed.

## Known user or environment

The maintainer, on zenmemes.com, signed in with his seeded `ilyag` key. He opens Tools → User Access → `openpgp-1fc2edb074108568ad28fec8748e323e033d32b4` and presses "Make root". He then checks that the Feature flags switches work when signed in with that key, on his phone. If the button is missing, his browser is not signed in as the seeded key; Account Key shows which key it is. Evidence: the new seed file and grant record in the source history, and the working switches. `./v3 approve` remains the fallback.

## Risks

- **A new signed record type.** Rebuilds and history views may need to know it. Earliest check: Step 3 lists where feature-flag change records are read, and mirrors those places. Mitigation: the seed alone carries authority; the grant record is evidence, so a reader that ignores it stays correct.
- **Seed meaning changes.** `user_approval_seed_record_v1.md` says seeds "exist only to bootstrap initial trust". Mitigation: update the spec to allow seeds written by an operator grant, with the reason naming the request.
- **Import of the new record type.** `ArchiveRecordCatalog.php` lists the record paths an archive may hold, and `ContentImportPlanner.php` excludes authority records. A grant record the importer does not know could block an import or be copied in. Earliest check: Step 3 adds the path to both and tests an archive that holds a grant. Mitigation: even if a grant record were copied, the seed is excluded, so it grants nothing.
- **Granting root to the wrong key.** Mitigation: the confirmation names the username, and the root approval page shows the grantor. Retirement removes it.

## Shared component inventory

- Access box `templates/partials/profile_access.php`, on Tools → User Access key pages: extend with the button next to Approve.
- `LocalWriteService::prepareFeatureFlagChange` and `finalizePreparedFeatureFlagChange`: copy the pattern. Prepared record, browser signature, operator re-check, and one commit for record, signature and state.
- `LocalWriteService::seedApprovedIdentity` and the seed record builder: reuse to build the seed. The new path commits the seed together with the grant, not in its own commit.
- `RouteServices::profileIsActiveOperator` (PR #88): reuse to decide who sees the button.
- `ApprovalPolicy::isOperator` and `isActiveOperator`: unchanged.
- `RootApprovalPageController` and `root_approval.php`: extend to show the grantor and link the signed request.
- Browser signing in `browser_signing.js`: reuse, as the Feature flags page does.
- Instance import, `ArchiveRecordCatalog.php` and `ContentImportPlanner.php`: extend the authority exclusion with the grant record path.

New: one signed record type, because no existing record says "this operator made that key an operator".

## User flow

1. An operator opens Tools → User Access and picks a key.
2. The access box shows "Make root" beside the key's status.
3. The operator presses it. The confirmation reads "Make ilyag (1fc2edb0…) a root operator?"
4. The browser signs the request with the operator's key.
5. The page reloads. The key is now an operator, with a link to its root approval page.
6. Signed in with that key, the Feature flags switches work.

## Success criteria

- An operator can make an approved key an operator from its access page, with no server command.
- A non-operator never sees the button, and a forged or unsigned request is rejected.
- After a full rebuild from records, the key is still an operator.
- The root approval page shows the grantor and links the signed request.
- A retired key loses operator power as today.
- Importing an archive from an instance with grants brings in no operators, and the import still completes.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-09T05:05:56Z, `railspike-effort` / `v3/same_username_operator`, Step 2 handoff. Scope v1/B unchanged: both approval paths, the review sentence and context field, one flag, one concept file, and the CLI doc. Projected total **0.40** reaffirmed. The approval-context replay check and the two-commit question were known risks at Step 1 and do not change the estimate yet. Same baseline `bc4bfd6b`, R1/P2, single exposed Claude rater. No implementation or accepted outcome.

2026-11-09T05:13:13Z, Step 2 rewrite checkpoint for Step 1 Revision 1. Scope v2: Option E, a "Make root" button, signed, one commit for request and seed, no flag. Projected total 0.40 → **0.45**, reason changed scope: a new signed record type, its spec and the root approval page, minus the approval-context change. Same baseline `bc4bfd6b`, R1/P2, single exposed Claude rater. No implementation or accepted outcome.

*— Revised by Claude · 2026-11-09 UTC · Model: claude-opus-5-5* — added instance import to requirements, risks, inventory and success criteria after the maintainer asked how imports would behave. No estimate change: two path lists and one test.
