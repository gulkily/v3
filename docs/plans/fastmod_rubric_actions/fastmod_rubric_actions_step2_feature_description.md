> **Feature plan:** [Step 1](./fastmod_rubric_actions_step1_solution_assessment.md) · [Step 2](./fastmod_rubric_actions_step2_feature_description.md) · [Step 3](./fastmod_rubric_actions_step3_development_plan.md) · [Step 4](./fastmod_rubric_actions_step4_implementation_summary.md)

Debug mode: enabled

Effort study: E07 — [trial ledger](../effort_estimation_trial.md#e07--fastmod-rubric-actions)

## Problem

Fastmod identifies application-support content but does not make it discoverable through tags. Turn current meta evidence into a correctable classification without changing moderation or ranking.

## User stories

- As a reader, I want to find support posts through `meta` so that useful requests and reports are easy to discover.
- As a root-approved operator, I want to correct automatic classifications and recover failed changes so that mistakes do not persist silently.

## Core requirements

- Classify the scored item: a root score tags that root; a reply score tags only that reply, without tagging its ancestors or siblings. Matching replies must be discoverable through the meta filter with a link to the actual item. Show a compact machine-origin indicator with accessible evidence details; preserve independent human tags. Generated meta classification must have zero effect on votes, ranking, flags, author standing or visibility.
- Use only successful, current content/model/rubric evidence containing `meta`. Apply an explicit operator-controlled threshold with inclusive comparison; propose 0.90 as a candidate to validate, not proof of accuracy. New automation defaults off, independently of advisory scoring. Enabling or changing policy does not silently backfill old results or make model calls; only subsequent ordinary local-content evaluations qualify.
- Root-approved operators may suppress or restore automatic meta tagging per item through authenticated actions. Suppression survives retries, rescoring, restarts and policy changes until explicitly restored; restoration re-evaluates current evidence, rather than force-tagging an ineligible item. Human tag permissions remain unchanged. Re-evaluation below threshold or invalidated evidence withdraws only machine-owned classification; missing/failed work never becomes a zero score or evidence for a new tag.
- Record attributable classification, correction and rollback decisions with content, score, model, rubric and policy identity. Duplicate/concurrent processing has one consistent outcome. Failed application is visible and explicitly retryable without another provider call when evidence remains current. Preserve original signed bytes and never attribute automatic tags to a human identity.
- Disabling automation stops automatic additions and withdrawals while preserving classifications and corrections. A separate explicit operator rollback removes machine-owned meta tags, preserves human tags and suppression/audit history, and is retryable after interruption. Re-enabling does not silently reapply rolled-back historical results; document restart/rebuild and policy-change behavior.

## Delivery scope and completion boundary

**Work type: application change.** Cover newly evaluated local roots/replies, meta discovery, operator policy/correction/retry/rollback and accompanying reference/runbook updates. Include this cycle's plans/debug log and `docs/plans/effort_estimation_trial.md` bookkeeping in the approved documentation scope. Defer subcategories, imported/historical bulk tagging, holds, ranking changes and a general rule-builder.

Complete ordinary publication → existing async scoring → eligible meta classification → normal-view discovery → operator correction/retry/rollback. Release only when this journey and evidence freshness survive concurrent work, restart/rebuild and rollback, and representative human-reviewed support/non-support examples justify the threshold. If validation fails, retain default-off behavior and report activation blocked; deterministic provider tests establish mechanics, not accuracy.

## Risks

- **Tag semantics:** existing post tags are reactions and thread labels have different targets; direct reuse may affect scoring or misattribute authorship. Before Step 3, trace consumers and choose a machine-origin classification contract with explicit no-score behavior.
- **False labels:** the meta rubric covers support broadly, including hostile support. Before Step 3, define a representative human-labeled set, precision/recall reporting and an operator-accepted false-positive limit; resolve the release gate before enabling automation.
- **Staleness/recovery:** policy changes, rescoring and correction can race, resurrecting removed tags. Before Step 3, define evidence invalidation, durable suppression and idempotent retry/rollback semantics; verify those boundaries in implementation.

## Shared component inventory

Reuse/extend Fastmod's rubric panel, worker/score store and private exchange evidence; existing Tools policy/signing/authorization for operator controls; root/post cards and post detail for classification/provenance; tag index/detail and standard/Forte/QDB board, search, thread and profile results for consistent display/discovery. Trace ThreadRepository, ViewerTagLookup, TagGrouping and TagScore plus tag APIs, read-model builders and static/offline exports for shared classification consumption. Extend shared contracts rather than clone markup; introduce a machine-origin classification path if reaction contracts cannot preserve target and no-score semantics. Private evidence/corrections remain outside public exports; published tag projections must survive rebuild without changing signed records.

## Simple user flow and success criteria

1. Operator validates the candidate threshold and enables tagging through Tools → Fastmod.
2. A local post publishes normally; its next ordinary successful meta evaluation adds a qualifying tag. A reader follows the meta filter to that exact root or reply.
3. Operator suppresses a mistaken classification, restores eligibility deliberately, or retries a failed application; visible results confirm the outcome. Operator can disable automation and explicitly roll back generated tags.

Verify below/equal/above threshold; meta-only and overlapping moderation results; root/reply targeting; no score/visibility changes; human-tag preservation; forged/repeated/concurrent corrections; stale/failed evidence; restart/rebuild; interrupted retry and rollback. Check normal browser flows on phone/desktop and affected themes; report gaps separately from passes.

## Study snapshot and approval state

E07, Step 2 handoff, UTC 2026-10-20; Codex, single exposed rater; R1 hashes reverified; scope v1 clarified above; starting revision `4c521b743575979b8fefc66b38271551a57a08ef`. Projected total **0.95 Railspike units**, revised from intake/Step 1 **0.85** (preserved). New understanding: existing tagging writes identity-attributed reactions/labels, making no-score classification, reply discovery and rebuild/rollback integration less directly reusable than assumed. Same meta-only outcome and required recovery; no hours/stage conversion. Includes planning, implementation, verification, documentation and ordinary correction; projection remains conditional on resolving the risks above.

Step 1 approved by the user. Threshold/authority/target/correction defaults above are proposals for Step 2 approval; numeric validation acceptance remains a gate to resolve before Step 3. No implementation authorized.


## Approved amendment — 2026-10-20

After approving Step 2, the user explicitly declined the proposed human-labeled accuracy gate: “I don't think we need to do this, the models are smart enough to understand the concept.” This supersedes the human-labeled validation/precision-recall release conditions above. Scope v2 uses initial adjustable threshold 0.90, deterministic behavior verification and human correction/recovery; no labeled benchmark or model-accuracy gate is required. No moderation or ranking scope added.
