> **Feature plan:** [Step 1](./read_model_schema_version_integrity_step1_solution_assessment.md) · [Step 2](./read_model_schema_version_integrity_step2_feature_description.md) · [Step 3](./read_model_schema_version_integrity_step3_development_plan.md) · [Step 4](./read_model_schema_version_integrity_step4_implementation_summary.md)

## Stage 1 - Canonical DDL fingerprint

- Changes:
  - Added `ReadModelSchema` as the single source of the read-model DDL statements and its SHA-256 fingerprint.
  - Changed `ReadModelBuilder` to create the schema by executing that canonical statement list.
  - Added focused coverage that executes the canonical DDL and checks the resulting tables/indexes and deterministic fingerprint shape.
- Verification:
  - `php -l src/ForumRewrite/ReadModel/ReadModelSchema.php`
  - `php -l src/ForumRewrite/ReadModel/ReadModelBuilder.php`
  - `php -l tests/ReadModelSchemaTest.php`
  - `php tests/run.php ReadModelSchemaTest ReadModelBuilderTimingTest` — 3 passed.
  - `git diff --check`
- Notes:
  - The readable schema generation remains unchanged at this stage; later stages add the fingerprint to the metadata identity contract.

## Stage 2 - Paired metadata identity

- Changes:
  - Added shared expected-identity and identity-matching helpers that require both the readable schema version and DDL fingerprint.
  - Wrote the paired identity in full rebuild and incremental-update metadata.
  - Applied the shared freshness check to candidate validation, request-time rebuild decisions, and local-write safety checks.
  - Added metadata-contract coverage, including incremental metadata writing and absent/mismatched identity cases.
- Verification:
  - `php -l` passed for changed read-model, application, write-service, and test files.
  - `php tests/run.php ReadModelMetadataTest ReadModelCandidateBuilderTest ReadModelSchemaTest` — 7 passed.
  - `git diff --check`
- Notes:
  - A missing fingerprint is intentionally an identity mismatch, so databases built before this change take the existing recovery path.

## Stage 3 - Operator schema identity diagnostics

- Changes:
  - Extended the shared operator-status payload with stored and expected schema version and fingerprint values, and made status freshness use the paired identity contract.
  - Added both identifiers to the read-model status API, codebase-state page, and CLI status command.
  - Updated the read-model schema reference and diagnostics coverage.
- Verification:
  - `php -l` passed for changed production, template, CLI, and test files.
  - `php tests/run.php OperatorStatusCollectorTest StatusCommandTest` — 10 passed.
  - `php tests/run.php LocalAppSmokeTest::testApplicationRendersTextApisAndRss` — 1 passed.
  - `php tests/run.php LocalAppSmokeTest::testApplicationRendersCoreRoutes` — 1 passed.
  - `git diff --check`
- Notes:
  - The API and CLI make the expected fingerprint explicit, allowing an operator to distinguish missing or mismatched legacy metadata from a current read model.
