> **Feature plan:** [Step 1](./account_retraction_attribution_step1_solution_assessment.md) · [Step 2](./account_retraction_attribution_step2_feature_description.md) · [Step 3](./account_retraction_attribution_step3_development_plan.md) · [Step 4](./account_retraction_attribution_step4_implementation_summary.md)

Debug mode: disabled

Implementation in progress.

## Stage 1 - Durable acceptance evidence
- Changes: finalization captures a versioned local commit trailer binding attribution and canonical username to the retraction hash, actor, and target. Fresh approved profiles and a clean canonical tree are required for author wording; uncertainty retains operator wording. Signed records and API fields are unchanged.
- Verification: `php tests/run.php WriteApiSmokeTest::testOperatorRetractionCapturesAccountEvidenceAtAcceptance WriteApiSmokeTest::testOperatorRetractionSignsAsActorAndRechecksPrivilegeAtFinalize` — 2 passed. Covers normalized same-account keys, different accounts, unapproved target, approval revoked after prepare, dirty evidence, unchanged signed bytes, retries, and existing operator authority.
- Notes: account state is checked at finalization under the existing write lock, before writing the tombstone. Historical records have no new evidence and remain conservative.

## Stage 2 - Shared accepted attribution
- Changes: RetractionAuthority resolves attribution from the same accepted Git event used for operator authority; PostRetractionRecord carries it separately from its unchanged authorization classification. Missing, duplicate, malformed, mismatched, or later-added evidence retains operator wording.
- Verification: four targeted WriteApiSmokeTest methods passed (acceptance capture, bound-event validation, historical authority, and operator finalize/recovery). Coverage includes unaccepted/import-style records, altered bytes, cache changes with HEAD, and approval removed after acceptance.
- Notes: existing local Git trust boundary is retained. No historical reconstruction from present-day profiles; an accepted author decision remains stable after approval changes.

## Stage 3 - Consistent post and source notices
- Changes: schema 19 adds separate nullable display attribution to posts/threads; every existing classification-selecting post query carries it. Shared notices use accepted attribution, and current/historical source responses resolve the same evidence. Existing effectivePostIds values remain authorization classifications. Updated schema documentation and prior-version startup regression.
- Verification: 11 schema/metadata/retraction tests passed, followed by 3 surface tests covering same/different/unapproved/revoked/dirty evidence through thread, permalink, paned view, and current/historical source routes. Startup from version 18 rebuilds before the new queries; version 17 recovery also passes. Accepted attribution survives revocation and full rebuild while derived authorization remains operator. Existing suppression/reply/action tests pass.
- Notes: the added test query cursor was released before rebuilding to avoid a fixture-held SQLite lock; the corrected run passed. No runtime lock changes were needed. Release retains local acceptance Git history; deployment to a remote environment is outside this task.
