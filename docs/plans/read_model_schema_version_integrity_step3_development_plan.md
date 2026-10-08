> **Feature plan:** [Step 1](./read_model_schema_version_integrity_step1_solution_assessment.md) · [Step 2](./read_model_schema_version_integrity_step2_feature_description.md) · [Step 3](./read_model_schema_version_integrity_step3_development_plan.md) · [Step 4](./read_model_schema_version_integrity_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a deployment runs against an existing read-model database whose DDL differs from the application's canonical DDL.
- End-to-end outcome: the next request detects the schema-identity mismatch, performs the existing serialized rebuild, and subsequent reads use the current model.
- Required recovery: absent or mismatched fingerprint metadata follows the existing stale-model/rebuild failure and operator-status paths without exposing raw schema errors.
- Deployment/external verification: validate fresh builds, legacy metadata, and changed DDL identity through automated tests; verify operator diagnostics report stored and expected identity.
- Release condition: every metadata producer and freshness consumer uses the same readable generation plus derived fingerprint contract, with the existing repository freshness checks preserved.

## Key Risks

- **High risk: incomplete metadata propagation.** Impact: a candidate, incremental update, or write path could mark a stale database ready. Early validation: inventory each producer and consumer in focused tests. Mitigation: use one shared identity API and test every path.
- **High risk: unstable fingerprint input.** Impact: non-semantic edits could force expensive rebuilds. Early validation: change formatting without changing the canonical DDL definition. Mitigation: fingerprint only the canonical DDL representation that also creates the schema.
- **High risk: legacy-database recovery.** Impact: deployed databases without a fingerprint could fail before recovery. Early validation: exercise metadata lacking the field. Mitigation: treat it as stale and reuse the exclusive-lock rebuild path.
- **Non-DDL rebuild requirements.** Impact: changed derivation behavior may still need a deliberate rebuild. Early validation: identify whether this change has any non-DDL dependency. Mitigation: retain the readable generation as the explicit operator-controlled rebuild signal.

## Stage 1

- Goal: Establish one canonical read-model DDL definition and a stable derived fingerprint.
- Dependencies: Approved Steps 1–2; current read-model schema creation behavior.
- Expected changes: Move the read-model creation definitions behind a shared schema contract; make schema construction execute that contract; add a fingerprint accessor derived only from its canonical representation.
- Verification approach: Run focused schema-build tests and confirm the expected tables/indexes remain available; unit-test deterministic fingerprints and a changed canonical definition producing a different value.
- Risks or open questions:
  - Impact: refactoring DDL definitions can accidentally alter the database shape.
  - Early warning / validation: compare a fresh database's schema and existing build test results.
  - Mitigation: preserve each existing definition verbatim while changing only its ownership and invocation.
- Canonical components/API contracts touched: Read-model schema creation contract; `ReadModelMetadata` identity accessor.

## Stage 2

- Goal: Make paired schema identity authoritative across all read-model metadata producers and freshness decisions.
- Dependencies: Stage 1 fingerprint contract.
- Expected changes: Store the fingerprint with the existing readable schema version during full and incremental builds; extend candidate validation, request-time readiness, and local write safety checks to require both values.
- Verification approach: Focused tests for full build, incremental update, candidate validation, and stale detection with matching, mismatched, and absent fingerprints.
- Risks or open questions:
  - Impact: a missed consumer yields contradictory readiness decisions.
  - Early warning / validation: search the metadata identity call sites and exercise each identified path.
  - Mitigation: centralize expected identity retrieval and complete the producer/consumer inventory before merge.
- Canonical components/API contracts touched: Read-model metadata rows; application freshness contract; candidate and local-write validation contracts.

## Stage 3

- Goal: Surface paired identity in existing operator diagnostics without changing public-facing behavior.
- Dependencies: Stage 2 paired metadata contract.
- Expected changes: Extend the existing operator status collection and codebase/read-model status output with stored and expected schema fingerprints while retaining the readable version display.
- Verification approach: Update status/controller smoke coverage to assert the readable generation and both diagnostic fingerprint values; run existing operator-status tests.
- Risks or open questions:
  - Impact: operators cannot distinguish a legacy stale database from a current one.
  - Early warning / validation: inspect status output for matching and missing metadata cases.
  - Mitigation: use explicit stored-versus-expected labels and preserve existing status fields.
- Canonical components/API contracts touched: `OperatorStatusCollector`; `CodebaseStateController` status output.

## Stage 4

- Goal: Prove automatic recovery for the forgotten-version-increment scenario and legacy metadata.
- Dependencies: Stages 1–3.
- Expected changes: Add integration coverage that presents a database with a mismatched or absent fingerprint but unchanged readable version, then verifies one serialized rebuild yields a ready model; update affected test expectations.
- Verification approach: Run focused read-model and application smoke tests, followed by the relevant full test suite and static/style checks used by the repository.
- Risks or open questions:
  - Impact: tests could validate metadata in isolation but not the request-time recovery path.
  - Early warning / validation: assert the rebuild reason and the post-rebuild metadata through the normal entry path.
  - Mitigation: make the recovery scenario an end-to-end test using the existing rebuild mechanism.
- Canonical components/API contracts touched: `Application::ensureReadModel()` recovery flow; read-model test fixtures and smoke-test contract.

Waiting for "Approved Step 3" before creating the feature branch or beginning implementation.
