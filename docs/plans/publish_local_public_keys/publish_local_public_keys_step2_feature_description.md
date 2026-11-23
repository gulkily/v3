> **Feature plan:** [Step 1](./publish_local_public_keys_step1_solution_assessment.md) · [Step 2](./publish_local_public_keys_step2_feature_description.md) · [Step 3](./publish_local_public_keys_step3_development_plan.md) · [Step 4](./publish_local_public_keys_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/publish_local_public_keys; [cycle ledger](../effort_ledger/publish_local_public_keys.md), standing P2 enrollment.
Extended estimates: enabled

**Cycle estimate: 0.50**

## Problem

Local public keys may be unknown to production. Operators need to publish selected missing identities without moving private keys or granting membership.

## User stories

- As an operator, I want to preview missing identities so that I publish only the keys I choose.
- As an operator, I want confirmed results and safe retries so that interrupted publication never replaces an existing identity.

## Core requirements

- Read canonical local public identities only. Exclude locally retired keys; reject malformed keys and private-key material before network work.
- Default to a read-only preview for an explicit destination. Show fingerprints, registration names, existing/missing identities, refusals and unknown results. Errors never imply absence.
- Publish only the reviewed selection, bound to the same keys and destination. Recheck before writing; changed input requires another preview. Refuse name mismatches and retired destinations; skip existing identities.
- Confirm each accepted identity by destination fingerprint and name. Preserve existing identities, key material, approvals and privileges; registration proves neither key possession nor membership.
- Retain per-key outcomes after partial failure. Read back after uncertain responses before retrying; report unresolved selected keys with an actionable next step and unsuccessful exit status.

## Delivery scope

Application change: a local CLI command, project-owned helpers, focused tests, CLI guidance, touched concept entries, feedback references and this cycle's plans/ledger. Reuse normal registration, including its hidden bootstrap anchor. No new server API, web page, dependency or schema. Exclude user-post transfer, approvals, private-message data, key updates and automatic background publishing. PF-20261121-F03 remains broader; PF-20261121-F11 remains deferred. No additional requests are authorized.

## Completion boundary and known environment

The requester runs the local WSL checkout's ordinary CLI against an explicitly chosen production instance. Preview, select, publish and rerun must show which identities arrived and which need attention. First verify equivalent isolated instances, including failures. Live production access needs separate authorization; fixture success cannot establish production acceptance. Handoff records any pending production check. No message-recovery claim follows from public-key publication.

## Risks

- Names or retirement differences could mislead operators or revive an unwanted key. Check names and retirement evidence before planning writes; refuse differences without importing authority.
- A timeout or concurrent registration could look like a missing identity. Validate destination responses and lost-response recovery early; recheck exact identity before retrying.
- Wrong destinations or source files could expose unintended data. Validate preview binding and public-only input early; refuse changed selections and preserve destination access policy.

## Shared component inventory

Reuse OpenPgpKeyInspector.php, IdentityBootstrapRecordParser.php and existing local name/retirement evidence. Reuse getProfile in ApiTextController.php and linkIdentityApi in ComposeAndAccountKeyController.php; retain LocalWriteService.php registration. The new command supplies inventory/selection orchestration absent from those endpoints. Reuse CLI conventions from scripts/import_instance.php; [source paths and reuse decisions](publish_local_public_keys_assessment_evidence.txt).

## Simple user flow

1. Name the destination and preview local identities.
2. Select eligible fingerprints and explicitly publish them.
3. Review confirmed/refused/unknown results; rerun unresolved selections after the stated recovery action.

## Success criteria and concepts touched

Ordinary CLI tests establish zero preview writes, exact selected publication, duplicate-free retries, explicit mixed results and unchanged authority. Cover shared registration consumers and restricted-site refusal. Add the public-key publication concept; use [approval acceptance](../../concepts/approval_acceptance.md) and [key retirement](../../concepts/key_retirement.md) unchanged. Import behavior stays unchanged.

## Study snapshot

2026-11-22T21:13:08+00:00: Step 1 Option A approved by “approved step 1”. Step 2 handoff: 0.50 reaffirmed; scope v1/A, baseline 22fc9f10a4d4f8cba9ecaed501aba2ea90d129e4, R1/P2. Codex, single exposed rater; prior snapshots retained in Step 1. All three frozen R1 hashes reverified. No implementation, measured effort or acceptance claim.

*— Codex · 2026-11-22 UTC*
