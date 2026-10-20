> **Feature plan:** [Step 1](./fastmod_rubric_actions_step1_solution_assessment.md) · [Step 2](./fastmod_rubric_actions_step2_feature_description.md) · [Step 3](./fastmod_rubric_actions_step3_development_plan.md) · [Step 4](./fastmod_rubric_actions_step4_implementation_summary.md)

Debug mode: enabled

Effort study: E07 — [trial ledger](../effort_estimation_trial.md#e07--fastmod-rubric-actions)

## Current revision — scope v3, Step 2 approved

This root-only replacement follows the [Stage 1 findings](./fastmod_rubric_actions_step4_implementation_summary.md). Previous Step 2/3 approvals and the original implementation plan do not authorize this revision. Historical scope and estimates remain below; the original roots/replies outcome is not declared complete.

## Problem and user stories

Fastmod scores application-support content but does not add a discoverable `meta` tag. As a reader, I want qualifying top-level posts to appear under the existing meta tag; as an instance operator, I want to remove mistakes and stop automation without changing human tags.

## Core requirements

- On an eligible ordinary Fastmod evaluation of a newly published local top-level post, add machine-owned `meta` when the successful score is **≥0.90**. Use a fixed meta mapping, with default-off configuration and a configurable initial threshold. Replies, imports and historical/backfill work cannot create tags. Enabling/configuration changes do not score or tag old items.
- Persist scores, machine tags, minimal evidence and suppression privately in SQLite; no per-tag canonical text/signature files or Git commits. Live reads combine human/machine tags through shared indexed joins or bulk lookup. Preserve original signed bytes and keep votes, ranking, moderation and visibility unchanged.
- Use existing tag presentation and root-level discovery across applicable live/static/offline views, without new tag styling, origin badges or evidence UI. Generated public artifacts include effective root tags only; scores and private audit/evidence are excluded from public backups and exports. Retain machine/human ownership internally so identical labels deduplicate without erasing human contributions.
- Provide CLI status/recovery and **remove-and-suppress** for an incorrect machine tag, plus machine-only bulk rollback. Suppression persists across retries/restarts; no restore workflow. Disabling stops new additions without removing existing tags. Rollback removes current machine tags and suppresses their reapplication; human tags survive. Repeated/interrupted operations are recoverable; tag application failure does not discard successful scoring or silently require another provider call.
- Classifications are stable until explicitly removed: no automatic rescoring, withdrawal or reclassification after model/rubric/threshold changes. Eligibility is checked when a new tag is created using matching content and captured scoring configuration; failures/missing meta are never interpreted as zero. Duplicate processing cannot recreate removed tags. Normal retraction/access rules still govern the post and its presentation. Reuse existing worker retry/status; add no dashboard, generic rules engine, browser controls or labeled-model accuracy gate.

## Delivery scope and completion boundary

**Work type: application change.** Ordinary publication → existing asynchronous score → root meta tag → existing tag discovery; CLI correction, disable, rollback and restart/rebuild recovery complete the slice. Only roots newly published after activation qualify; later ordinary retries may complete their interrupted initial evaluation, but historical scoring/rescoring is not a tagging trigger. Accompanying documentation covers configuration, commands/private backup and this cycle's plans/log/effort ledger; schema documentation only if applicable.

Release after meaningful deterministic end-to-end tests, root-target/threshold/failure checks, human-tag preservation, interrupted recovery, live/static/offline consistency and affected phone/desktop/theme verification. Retain generation-safe publication/invalidation so correction during an artifact build cannot be overwritten by that build. No external deployment or live-model accuracy gate. Deferred: replies, browser controls, automatic reclassification, subcategories, restore, evidence viewer, bulk historical tagging, ranking and moderation actions.

## Risks

- **Attribution and discovery:** human tags are canonical reactions/labels. Stage 1 already established separate private machine ownership and shared live read composition; reuse root tags in existing grouping/rendering rather than inventing item-level offline data.
- **Correction/publication races:** stale worker retries or artifact builds can restore removed tags. Retain suppression, idempotent commands and generation-aware artifact invalidation/publication checks; verify interruption and concurrent build/correction before release.
- **Private-state durability:** Git alone cannot restore machine tags/suppression. Keep their minimal evidence independently of transient score pruning; document private database backup and make unavailable state visible without corrupting ordinary human-only reads. False labels are handled by remove-and-suppress; no new accuracy study is required.

## Shared components and flow

Reuse Fastmod scorer/store/sweep and CLI worker status, root/thread cards and detail, ThreadRepository/TagGrouping, tag index/detail and standard/Forte/QDB/profile/search consumers; reuse PublicOfflineSnapshotBuilder's existing root-label format and offline reader presentation. Extend shared live tag selection and artifact generation/invalidation, preserving private/public separation. Reuse CLI operator trust; no new browser identity/signing flow or navigation destination.

1. Operator enables configuration; publishes a new local top-level post normally.
2. Existing worker scores it; an eligible meta score adds the existing-style tag, available through normal tag discovery.
3. Operator inspects status, removes-and-suppresses a mistake, or disables/rolls back; subsequent reads/artifacts show the effective result.

## Study snapshot — revised Step 2 handoff

E07, UTC 2026-10-20; Codex single exposed rater; R1 hashes reverified. Scope **v3** is an explicitly reduced replacement, not delivery of original v2; branch `feat/fastmod-rubric-actions`, current investigation baseline `79bc519f`, original starting revision `4c521b743575979b8fefc66b38271551a57a08ef`. Total projected cycle **0.85 Railspike units**, including preceding assessment/investigation, replanning, implementation, verification, documentation and ordinary correction. Larger than composer decluttering (0.60), below provisional startup recovery (1.0): existing root tag formats/presentation are reusable, with no new reply discovery, offline item schema/client, signed browser controls or automatic reclassification. Private state, cross-database reads and reliable artifact/CLI recovery remain substantive. This is a scope reduction from the 1.20 v2 discovery projection, not an efficiency measurement or hour conversion; all previous snapshots remain historical. Detailed stage feasibility must still be checked in revised Step 3.

## Request disposition and approval

User explicitly accepted root-only tagging, no browser controls/reclassification, fixed mapping, no evidence viewer/restore/dashboard, and existing tag styling. Incorporated in v3; the user subsequently responded `Approved Step 2`. Revised Step 3 may now be drafted; application work still requires revised Step 3 approval.

<details>
<summary>Historical Step 2 v1/v2 — original approved scope, superseded for future work</summary>


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

</details>
