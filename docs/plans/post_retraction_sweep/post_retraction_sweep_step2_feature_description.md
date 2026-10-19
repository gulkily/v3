> **Feature plan:** [Step 1](./post_retraction_sweep_step1_solution_assessment.md) · [Step 2](./post_retraction_sweep_step2_feature_description.md) · [Step 3](./post_retraction_sweep_step3_development_plan.md) · [Step 4](./post_retraction_sweep_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Retraction hides content, but retained local payloads and interrupted artifact cleanup remain. Operators need automatic cleanup with an immediate manual path and verifiable recovery.

## User stories

- As an author or eligible operator, I want accepted retraction to trigger cleanup so that covered local copies are removed without keeping my browser open.
- As an operator, I want preview, immediate execution, status, and retries so that I can verify coverage and recover missed cleanup.

## Core requirements

- Preserve immediate suppression and existing retraction recovery. Acceptance promptly queues cleanup; cleanup failure never reverses signed acceptance. Show cleanup pending/failure separately from retraction acceptance, with concise recovery guidance.
- Use one repeatable cleanup policy for queued work, manual execution, and periodic discovery through the existing cron workflow. Discover historical/imported retractions and recover acceptance-to-enqueue gaps; bound each run and resume remaining work.
- Cover generated HTML, served offline snapshot/update copies, and recorded LLM request/response/error/context payloads with proven retracted dependencies. Retain only explicitly allowlisted non-content exchange status/provenance; preserve unaffected exchanges. Unknown dependencies remain withheld and unchanged, with counts reported for policy review.
- Keep suppression effective during cleanup. Coordinate concurrent publishers and exchange writers so late content cannot silently invalidate completion; late provider results must be scrubbed or remain visibly pending for reconciliation. Repeated runs must converge without repeatedly destroying clean artifacts.
- Provide a non-mutating preview and operator status showing covered stores, examined/cleaned/pending/uncertain counts, failures, and retries without copying removed prose into reports. Completion means logical removal from covered live stores; account for database free pages and journals, without promising forensic secure erasure.

## Delivery scope and completion boundary

Work type: application change, delivering an operator maintenance capability integrated with ordinary retraction. Inventory managed content stores before implementation and publish an explicit coverage list. Other analysis caches/logs discovered in that inventory are documented as exclusions or returned for scope approval.

Entry: existing signed retraction or operator preview/run command. Outcome: cron or direct execution removes covered copies and reports coverage. Recovery: failures remain visible; operators can retry the same operation without another signature or acceptance commit. Release requires verified normal acceptance, cron discovery, manual execution, and interruption/concurrency recovery.

Canonical files, Git history, backups, attachments, other users' posts/published replies, remote servers/providers, and downloaded/browser copies remain outside automatic deletion. Retain signed evidence and operator authority history. No new navigation destination is required.

## Risks

- **Incorrect deletion:** uncertain provenance could remove unrelated diagnostics. Validate classification and payload inventory before planning dependent stages; delete only proven dependencies and preserve uncertain rows.
- **Late writes or missed work:** stale publications or provider completion could restore content. Trace all covered writers and scheduler entry points during Step 3; require coordination, recurring discovery, and race/restart verification before release.
- **Residual content or overstated completion:** payloads may survive in storage pages or unlisted stores. Inventory stores and database retention behavior before dependent stages; report precise coverage and exclusions, and block completion on covered-store failures.

## Shared component inventory

Extend the existing prepare/finalize retraction API, More actions control, partial-result recovery, and shared retraction notice only as needed for cleanup status. Reuse canonical retraction validation, artifact invalidation, read-model refresh, exchange dependency classification/visibility, queue worker/status/cron, and CLI conventions. Existing exchange links/list/detail and snapshot/download surfaces keep their suppression policy. Add a sweep CLI capability because operators currently have no preview or direct cleanup entry; use the shared policy across all triggers. Record any copy cleanup in the UI copy audit checklist.

## Simple user flow

1. Retract normally; suppression takes effect and cleanup is queued.
2. The worker cleans covered stores; recurring discovery catches missing or late work.
3. Inspect status or preview; run immediately when needed and retry reported failures.

## Success criteria

A fixture containing target, context-only, unaffected, and uncertain exchanges loses all covered target payloads while preserving unaffected/uncertain rows. Acceptance without an available worker remains suppressed and pending. Cron discovers a missed enqueue; manual execution reaches the same outcome; repeated clean runs do no destructive work. Forced interruption and late-write tests recover to a verified clean state with truthful status. Coverage and exclusions are documented.
