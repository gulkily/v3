> **Feature plan:** [Step 1](./feature_flag_change_attribution_step1_solution_assessment.md) · [Step 2](./feature_flag_change_attribution_step2_feature_description.md) · [Step 3](./feature_flag_change_attribution_step3_development_plan.md) · [Step 4](./feature_flag_change_attribution_step4_implementation_summary.md)

# Step 3: Development Plan — Signed Feature-Flag Changes

## Completion Contract

- **Normal entry:** A root-approved operator changes or resets one mutable flag from `/tools/feature-flags/`.
- **End-to-end outcome:** The browser signs the server-prepared change; the server verifies the same authorized identity, then atomically commits the existing snapshot, immutable action record, and detached signature; activity exposes the signed action and signer.
- **Required recovery:** Invalid, expired, replayed, unavailable, or identity-mismatched signatures make no configuration, repository, or activity change and leave the operator able to retry.
- **Deployment/external verification:** A deployed browser with a root-approved local key can complete the signed flow; repository source and activity links expose the action record and detached signature.
- **Release condition:** New site-level flag changes are verifiably attributable without changing evaluation, override, lock, dependency, reset, or invalidation semantics.

## Key Risks

- **High risk: identity binding.** Impact: a valid signature could be credited to someone other than the authorized operator. Early validation: trace the resolved root-approved profile's canonical identity ID and public key through existing prepared signed writes. Mitigation: prepare and finalize both bind that exact identity and reject every mismatch.
- **High risk: split evidence.** Impact: configuration could change without a matching auditable signature. Early validation: inspect the existing multi-file commit and failure behavior. Mitigation: write and commit snapshot, action record, and signature as one canonical change; test every failure before commit.
- **High risk: historical rebuild compatibility.** Impact: existing unsigned flag history could disappear or duplicate during read-model rebuild. Early validation: rebuild a fixture with pre-feature commits and a new signed change. Mitigation: preserve legacy activity as explicitly unattributed while using action records as the source for new activity.

## Stage 1
- Goal: Define and validate the immutable canonical feature-flag change record.
- Dependencies: none.
- Expected changes: add the V1 record specification, value object/parser, canonical path resolver, repository loader, and source-path validation for one action containing its ID, timestamp, flag, value, and operator identity; define the adjacent detached-signature path and record-path consistency rules.
- Verification approach: parser/path tests cover valid records and malformed IDs, timestamps, values, identities, duplicates, and mismatched paths; source-route validation accepts only the new record and its signature.
- Risks or open questions:
  - Impact: an ambiguous record cannot provide trustworthy audit evidence.
  - Early warning / validation: review the contract against existing post-reaction and signed-post record conventions.
  - Mitigation: finalize the minimal V1 contract before any writer or UI changes.
- Canonical components/API contracts touched: new feature-flag action-record spec and canonical classes; `CanonicalPathResolver`, `CanonicalRecordRepository`, `SourcePathValidator`.

## Stage 2
- Goal: Make signed preparation and finalization the only write path for an actual feature-flag change.
- Dependencies: Stage 1.
- Expected changes: add `prepareFeatureFlagChange(array $input, string $operatorIdentityId): array` and `finalizePreparedFeatureFlagChange(array $input, string $operatorIdentityId): array` to `LocalWriteService`; reuse the existing prepared-record storage and detached OpenPGP verifier; validate the resolved operator identity, exact prepared bytes, expiry, single use, flag mutability, override state, and requested value; commit the snapshot, immutable action record, and `.asc` signature together; retain no-op behavior and existing cache/read-model refresh semantics.
- Verification approach: service and write-API tests prove valid signature success and that absent, invalid, altered, expired, replayed, mismatched-identity, locked, and overridden requests create no commit, action record, snapshot update, or activity.
- Risks or open questions:
  - Impact: a partial failure could leave a snapshot without proof.
  - Early warning / validation: force each write and commit failure in a temporary repository.
  - Mitigation: use one canonical commit boundary and remove prepared state only after success.
- Canonical components/API contracts touched: `LocalWriteService`; existing prepared-write storage and `OpenPgpSignatureVerifier`; feature-flag evaluator and invalidator reused unchanged.

## Stage 3
- Goal: Expose authenticated prepare/finalize endpoints while preventing the legacy unsigned submission path from changing flags.
- Dependencies: Stage 2.
- Expected changes: add prepare and finalize feature-flag routes and controller methods; resolve and pass the root-approved viewer's canonical identity on both calls; return no-store structured results suitable for browser signing; change `/api/set_feature_flag` and the form-submit path to reject unsigned mutation with clear guidance.
- Verification approach: route tests cover methods, non-root rejection, identity changes between prepare and finalize, successful response fields, and unsigned legacy API/form rejection.
- Risks or open questions:
  - Impact: a session hint could be confused with signature authority.
  - Early warning / validation: test two valid identities across one prepared request.
  - Mitigation: require the same root-approved identity at prepare, signature verification, and finalize.
- Canonical components/API contracts touched: `Application` routes; `ToolsPageController`; `/api/prepare_feature_flag_change`; `/api/finalize_feature_flag_change`; legacy `/api/set_feature_flag` and `/tools/feature-flags/` submit contracts.

## Stage 4
- Goal: Let a root-approved operator complete the signed change from the existing Feature Flags page.
- Dependencies: Stage 3.
- Expected changes: extend the existing page assets and feature-flags script to prepare, sign with the selected browser key, finalize, and refresh the current control state; preserve existing lock, dependency, reset, pending, and error feedback behavior; make a missing or unusable browser key a recoverable signed-action error rather than an unsigned fallback.
- Verification approach: browser-signing normalization tests assert prepare/sign/finalize payload binding and focused page tests confirm enabled controls, reset, success, and signing-error feedback.
- Risks or open questions:
  - Impact: an operator might believe a change succeeded before final verification.
  - Early warning / validation: exercise invalid-signature and key-unavailable paths with the current status UI.
  - Mitigation: update UI state only from a successful finalize result and retain retryable errors.
- Canonical components/API contracts touched: `templates/pages/feature_flags.php`; `feature_flags.js`; `browser_signing.js`; existing browser key and identity-hint behavior.

## Stage 5
- Goal: Make new signed changes durable, attributed activity and source evidence while retaining legacy history.
- Dependencies: Stages 1–3.
- Expected changes: derive new `site_feature_flag` activity from immutable action records rather than a git subject; populate existing activity author fields from the verified operator; use the action record as the source path so existing source, commit-manifest, and detached-signature presentation applies; preserve an explicitly unattributed fallback for historical snapshot-only changes without duplicating signed events; update incremental activity insertion to match rebuild output.
- Verification approach: warm-write and full-rebuild tests show one attributed signed event with source/signature links; a legacy fixture retains one unattributed event; no duplicate events appear after rebuild.
- Risks or open questions:
  - Impact: auditors could see missing, duplicate, or misleading actor attribution.
  - Early warning / validation: compare warm and rebuilt activity rows for one old and one new commit.
  - Mitigation: make the canonical action record the single source for new event identity and action key.
- Canonical components/API contracts touched: `ReadModelBuilder`, feature-flag activity synchronization, `ActivityService`, existing activity/source templates and commit manifest.

## Stage 6
- Goal: Verify the released vertical slice and document operator-facing behavior.
- Dependencies: Stages 1–5.
- Expected changes: extend the site feature-flags record/reference documentation with the signed-action audit behavior and update relevant API/operation guidance; add end-to-end regression coverage spanning browser preparation, verification, git commit contents, activity/source presentation, and unchanged evaluator behavior.
- Verification approach: run focused canonical, signing, feature-flag, write-API, activity, and browser-script tests, then the full suite; perform a browser smoke test with a root-approved local identity and inspect the committed record/signature through source and activity links.
- Risks or open questions:
  - Impact: a green unit suite could miss a deployed key-loading or UI integration failure.
  - Early warning / validation: run the signed flow in a browser against a disposable repository before release.
  - Mitigation: treat the browser smoke and repository/source inspection as release gates.
- Canonical components/API contracts touched: affected specs/reference documentation; existing test runner and browser-signing test harness.
