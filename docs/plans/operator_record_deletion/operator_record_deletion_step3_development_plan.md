> **Feature plan:** [Step 1](./operator_record_deletion_step1_solution_assessment.md) · [Step 2](./operator_record_deletion_step2_feature_description.md) · [Step 3](./operator_record_deletion_step3_development_plan.md) · [Step 4](./operator_record_deletion_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract

- Entry/outcome: author/operator selects **… → Retract post**, confirms/signs; suppression preserves attribution and replies.
- Recovery: visible partial status/Retry; no duplicate evidence or mutation on denial.
- Deployment: verify old-cache startup rebuild, real browser signing, keyboard/touch and audit evidence locally; no external deployment.
- Release: legacy parity, operator success, unauthorized denial, valid signatures, persistence after privilege removal/rebuild/reimport. Eight stages, ≤1 hour each; rescope if exceeded.

## Key Risks

- **High risk: authority evidence.** Forgery/resurrection; prove signatures and locally trusted acceptance history in Stage 1. Imported assertions cannot confer authority; block dependents until proven.
- **High risk: attribution/cache drift.** Exposure/misattribution; inventory reads early, share validation/notices, and verify cache migration.
- **High risk: hidden recovery/rollback.** Validate menu failures early; keep feedback/Retry visible. Never roll back to code that ignores accepted operator evidence.

## Stage 1

- Goal: prove durable acceptance and backward compatibility.
- Dependencies: approved Step 2.
- Expected changes: define actor/classification and trusted historical authority evidence; preserve author format.
- Verification approach: signed fixtures: privilege removal, fabricated history, legacy parity.
- Risks or open questions: prove provenance/missing-history behavior; unverified operator evidence fails closed. Block Stage 2 until resolved.
- Canonical components/API contracts touched: retraction record/parser, canonical repository, shared authority policy; document verification contract.

## Stage 2

- Goal: accept correctly signed operator requests.
- Dependencies: Stage 1 proof.
- Expected changes: extend prepare/finalize actor attribution; preserve author requests, protected posts, retries.
- Verification approach: signed API success/denial, tampering, privilege loss before finalize.
- Risks or open questions: stale authorization; recheck within the write lock before commit.
- Canonical components/API contracts touched: LocalWriteService, existing retraction endpoints, ApprovalPolicy.

## Stage 3

- Goal: honor accepted actions consistently during reads/rebuilds.
- Dependencies: Stage 2.
- Expected changes: share signature/acceptance validation across suppression, source access and refresh.
- Verification approach: rebuild and source-route tests for accepted, forged, and legacy records.
- Risks or open questions: cost/drift; share/cache verified decisions.
- Canonical components/API contracts touched: RetractedPosts, canonical repository, read-model builders, source routes.

## Stage 4

- Goal: prevent imported actions from bypassing authority.
- Dependencies: Stage 3.
- Expected changes: validate imported signatures/authority; preserve accepted local actions.
- Verification approach: forged/duplicate imports and restoration after privilege removal.
- Risks or open questions: remote trust claims; reject unsupported operator evidence rather than importing privileges.
- Canonical components/API contracts touched: ContentImportPlanner, ContentImportRunner, retraction validation.

## Stage 5

- Goal: show correct actor attribution everywhere.
- Dependencies: Stage 3.
- Expected changes: derive actor/classification; extend notices/queries. Version/document schema changes.
- Verification approach: author/operator notices, replies, old-cache startup, artifact refresh.
- Risks or open questions: missed queries; inventory every retracted_at consumer and preserve legacy defaults.
- Canonical components/API contracts touched: read-model metadata/schema, Application, thread/authored repositories, shared notices and paned views.

## Stage 6

- Goal: provide consistent, accessible action placement.
- Dependencies: Stages 2 and 5.
- Expected changes: shared **… → Retract post**, accessible name, eligible-role visibility everywhere currently supported.
- Verification approach: root/reply/post pages; keyboard, focus, Escape, touch.
- Risks or open questions: surface drift; reuse one control and add no QDB/Forte action entry points.
- Canonical components/API contracts touched: shared retraction partial, root/post cards, shared menu styles/behavior.

## Stage 7

- Goal: complete browser signing and recovery.
- Dependencies: Stage 6.
- Expected changes: sign as actual actor; update confirmation/status language and keep feedback/Retry visible.
- Verification approach: real-browser signing, cancellation, rejection, partial refresh/retry.
- Risks or open questions: signer confusion or lost feedback; verify target and actor through the shared flow.
- Canonical components/API contracts touched: browser_signing, post_retraction, RetractedPostNotice.

## Stage 8

- Goal: verify and document the complete slice.
- Dependencies: Stages 4 and 7.
- Expected changes: integrated tests, audit/retention and deployment/rollback documentation.
- Verification approach: focused/full suite, rebuild/UI, source/Commits, privilege removal/reimport.
- Risks or open questions: retention claims/baseline failures; document limits and regressions separately.
- Canonical components/API contracts touched: retraction/API/import/browser tests, author retraction reference and read-model specification.
