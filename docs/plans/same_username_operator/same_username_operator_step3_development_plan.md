> **Feature plan:** [Step 1](./same_username_operator_step1_solution_assessment.md) · [Step 2](./same_username_operator_step2_feature_description.md) · [Step 3](./same_username_operator_step3_development_plan.md) · [Step 4](./same_username_operator_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/same_username_operator`; [host ledger](../effort_estimation_trial.md#cycle-v3same_username_operator). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.55** (revised at Step 3; Step 2 value 0.45)

Status: Step 2 approved on 2026-11-09 ("Approved step 2"), including the instance import revision. No scope added since.

**Concepts touched:** [approval acceptance](../../concepts/approval_acceptance.md), [key retirement](../../concepts/key_retirement.md), [instance content import](../../concepts/instance_content_import.md). New entry: **operator grant**, in Stage 6.

## Completion Contract

- Normal entry: an operator, signed in, opens Tools → User Access → a key's page and presses "Make root".
- End-to-end outcome: one commit saves the signed grant, its signature and an approval seed. Signed in with the granted key, the Feature flags switches work. A full rebuild keeps the key an operator. Its root approval page names the grantor and links the grant.
- Required recovery: a failed signature, a lost race or a stale page writes nothing and shows the error; pressing the button again retries. A key that already became an operator shows that instead.
- Deployment check: after deploy, the maintainer grants `openpgp-1fc2edb0…` on zenmemes.com and checks the switches from that key. Pending until he deploys.
- Release condition: merged and deployed. No flag.

## Key Risks

- **High risk:** granting root by mistake or by forgery. Impact: full site control. Early validation: Stage 2 tests reject a non-operator, a retired operator, a bad signature and an unapproved target. Mitigation: the server re-checks operator status inside the write lock, and the confirmation names the username.
- **High risk:** a rebuild that drops or invents operators. Impact: lost access, or a key gaining root. Early validation: Stage 2 rebuilds from records and checks the result. Mitigation: authority stays in the seed alone; readers that ignore grant records stay correct.
- Import of the new path. Impact: a blocked import, or grant records copied in. Early validation: Stage 5 imports an archive holding a grant. Mitigation: add the path to the archive catalog and to the authority exclusion.

## Stage 1

**Estimate: 0.08**

- Goal: define the operator grant record.
- Dependencies: none.
- Expected changes: new spec `docs/specs/operator_grant_record_v1.md` (`Key: value` headers: record ID, created time, operator identity, target identity, target username). New `OperatorGrantRecord` and parser. `CanonicalPathResolver::operatorGrant(recordId)` for `records/operator-grants/`. `SourcePathValidator` and `CanonicalRecordRepository::loadOperatorGrant`. `user_approval_seed_record_v1.md` allows seeds written by a grant.
- Verification approach: parser cases in `CanonicalRecordParsersTest` (valid, missing header, mismatched path).
- Estimate details (extended mode only): driver: copying the flag change record set; uncertainty: low.
- Risks or open questions: the target username header is evidence only. It records what the confirmation showed.
- Canonical components/API contracts touched: `FeatureFlagChangeRecord` and its parser as the model; `CanonicalPathResolver`, `SourcePathValidator`, `CanonicalRecordRepository`.

## Stage 2

**Estimate: 0.12**

- Goal: prepare and finalize a grant in `LocalWriteService`.
- Dependencies: Stage 1.
- Expected changes: `prepareOperatorGrant(input, operatorIdentityId)` and `finalizePreparedOperatorGrant(input, operatorIdentityId)`, copied from the flag change pair. Finalize re-checks inside the write lock: operator active, target approved, unretired, not yet seeded. One commit saves the record, its `.asc` and the seed. The seed reason reads "Granted by <operator> in <record ID>".
- Verification approach: new `OperatorGrantWriteTest`: success writes three files in one commit; a non-operator, a retired operator, a bad signature, an unapproved target and an existing seed are each rejected with nothing written. Then a full rebuild keeps the target an operator.
- Estimate details (extended mode only): driver: the authorization checks and rebuild test; uncertainty: the shared commit helper for three paths.
- Risks or open questions:
  - Impact: a forged or stale grant.
  - Early warning / validation: the rejection cases above.
  - Mitigation: identical checks in prepare and finalize; the seed's existence stops a second grant.
- Canonical components/API contracts touched: `prepareFeatureFlagChange` and `finalizePreparedFeatureFlagChange` as the model; `seedApprovedIdentity`'s seed builder; `ApprovalPolicy::isActiveOperator`; `KeyRetirement`; `commitCanonicalWrite`.

## Stage 3

**Estimate: 0.12**

- Goal: the "Make root" button, end to end.
- Dependencies: Stage 2.
- Expected changes: routes `/api/prepare_operator_grant` and `/api/finalize_operator_grant` in `Application.php`, added to the same route lists as key retirement. A controller method beside the flag change API. In `profile_access.php`, a form for approved, unretired, unseeded keys. It is visible before paint only when the browser's key is an operator, as `key_retirement.php` does. A small script that confirms with the username and signs through `browser_signing.js`.
- Verification approach: a controller test for both routes. A headless browser run on `./v3 start`: the operator sees the button, a member does not; the grant succeeds and the page shows the key as an operator.
- Estimate details (extended mode only): driver: the browser flow; uncertainty: reusing `key_retirement.js` or the flag page script.
- Risks or open questions: the button must not flash for non-operators. The before-paint pattern from key retirement covers it.
- Canonical components/API contracts touched: `profile_access.php`, `key_retirement.php` and `key_retirement.js` patterns, `browser_signing.js`, `ToolsPageController` flag change API as the model, `RouteServices::profileIsActiveOperator`.

## Stage 4

**Estimate: 0.06**

- Goal: show who granted root.
- Dependencies: Stage 2.
- Expected changes: `RootApprovalPageController` reads the grant named in the seed reason, and `root_approval.php` shows the grantor and links the grant source. `ActivityService` labels the path "operator grant record" and resolves its author, as it does for flag changes.
- Verification approach: `RootApprovalPageTest` gains a granted seed case; `ActivityItemLinkTest` gains the path.
- Estimate details (extended mode only): driver: two small readers; uncertainty: low.
- Risks or open questions: older seeds have no grant. The page keeps today's view for them.
- Canonical components/API contracts touched: `RootApprovalPageController`, `root_approval.php`, `ActivityService`.

## Stage 5

**Estimate: 0.05**

- Goal: imports bring in no operators.
- Dependencies: Stage 1.
- Expected changes: `ArchiveRecordCatalog.php` accepts the grant path. `ContentImportPlanner.php` adds `operator-grants` to its authority exclusion.
- Verification approach: an import test with an archive holding a seed and a grant: both excluded, the import completes, and no imported key is an operator.
- Estimate details (extended mode only): driver: finding the import test fixture; uncertainty: low.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: `ArchiveRecordCatalog`, `ContentImportPlanner`.

## Stage 6

**Estimate: 0.07**

- Goal: documentation, and a replay of the maintainer's case.
- Dependencies: Stages 1–5.
- Expected changes: `docs/concepts/operator_grant.md`. `docs/reference/v3_cli.md` names the button beside `./v3 approve`. Smoke set plus changed tests.
- Verification approach: the known-user replay on `./v3 start`: two keys named `ilyag`, one seeded; the second sees read-only flags; the first presses "Make root"; the second's switches work; a rebuild keeps it. The production check stays pending until deploy. Final diff review.
- Estimate details (extended mode only): driver: the two-key browser replay; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: concept inventory, CLI reference.

## Study checkpoint

2026-11-09T05:18:38Z, `railspike-effort` / `v3/same_username_operator`, Step 3 handoff. Scope v2 unchanged since Step 2 approval. Projected total 0.45 → **0.55**, reason new understanding: the record type also needs `SourcePathValidator`, the repository loader, `ActivityService` and the route lists, and the button must decide visibility before paint. Stage work 0.08, 0.12, 0.12, 0.06, 0.05 and 0.07, judged per stage and not added. Same baseline, R1/P2, single exposed Claude rater. No implementation or accepted outcome.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
