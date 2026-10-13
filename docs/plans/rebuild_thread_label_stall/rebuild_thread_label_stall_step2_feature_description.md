> **Feature plan:** [Step 1](./rebuild_thread_label_stall_step1_solution_assessment.md) · [Step 2](./rebuild_thread_label_stall_step2_feature_description.md) · [Step 3](./rebuild_thread_label_stall_step3_development_plan.md) · [Step 4](./rebuild_thread_label_stall_step4_implementation_summary.md)

Debug mode: enabled

## Problem
`./v3 rebuild` appears stalled when parsing reaches its total but silent indexing continues. Labels, reactions, and signed feature-flag changes perform repeated source-history lookups; production timings do not yet isolate their cost from other work.

## User stories
- As an operator, I want to see the current rebuild operation and its progress so that I can distinguish remaining work from parsing completion.
- As a maintainer, I want fewer repeated history walks and comparable timings so that rebuild performance improves without changing forum behavior.

## Core requirements
- Batch source-commit resolution for thread labels, post reactions, and signed feature-flag changes; preserve existing provenance and missing-history/Git-failure behavior.
- Name and time the identified work: history resolution, sorting/derivation, record and summary writes, approval/profile/author processing, activity/commit indexing, final commit, candidate validation, promotion, and snapshot bootstrap.
- Report bounded intermediate progress in long application loops using the actual work unit; parsing totals must not imply indexing completion. Identify blocking operations before they start and report their outcome/duration afterward; no heartbeat or percentage guarantee inside blocking Git, storage, or lock calls.
- Preserve labels, subjects, scores, votes, moderation, approvals, timestamps, activity, and rebuild recovery. Failed operations must not be reported as successful completion.
- Retain normal CLI usage and optional reporting for other callers; collect comparable before/after phase timings and verify derived-output equivalence before claiming speedups.

## Delivery scope and completion boundary
**Work type: application change**, an operator-facing rebuild improvement. Reuse the existing rebuild path and history resolver. Exclude schema changes and algorithmic redesign of approval replay, author lookup, row-write reduction, or legacy timestamp fallback semantics.

Start at normal `./v3 rebuild`; finish with a validated, promoted model and the existing snapshot readiness outcome. Preserve candidate cleanup, live-model protection before promotion, and post-promotion snapshot retry guidance. Release requires successful end-to-end and failure-path verification plus recorded performance evidence; deployment is outside this scope.

## Risks
- **Provenance drift:** batching could change activity attribution. Earliest validation: representative histories, including merges, absent/untracked paths, and Git failures; mitigation: compare source commits and derived output against current behavior before accepting the change.
- **Misleading progress or excessive output:** counters can obscure remaining work or add overhead. Earliest validation: empty, small, and large rebuild transcripts; mitigation: explicit units, bounded updates, accurate outcomes, and blocking-operation boundaries.
- **Unproven speedup:** other work may dominate production latency. Earliest validation: baseline phase timings and history-query counts; mitigation: require constant-bounded history invocations per targeted family and report measured results without a fixed production-time promise.

## Shared component inventory
Extend the canonical builder's history/progress/timing facilities and the candidate builder/promoter reporting path. The existing rebuild CLI renders this operator information; reuse its output/error flow through snapshot bootstrap. Forum pages, APIs, and offline content continue consuming equivalent read-model data; no new UI, API payload, or renderer is needed.

## Simple user flow
1. Operator runs `./v3 rebuild` as usual.
2. Output identifies active work, loop progress, and elapsed phase times through validation and promotion.
3. Operator receives completion and snapshot status, or the existing actionable failure/retry outcome.

## Success criteria
All three targeted families avoid history invocations proportional to record count; equivalent inputs produce equivalent domain data and provenance. Large-run transcripts expose every named phase with bounded loop updates. Normal completion and recovery checks pass; before/after timing results document the observed improvement or remaining bottlenecks.
