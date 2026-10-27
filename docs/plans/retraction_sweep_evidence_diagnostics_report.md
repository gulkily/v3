# Retraction sweep evidence diagnostics report

Date: 2026-10-24. Status: findings and suggested follow-up only; no implementation or production repair performed. This is a source review report, not a new Railspike implementation cycle or an approval of changes.

## Production observation

```text
./v3 retraction-sweep preview
Retraction sweep failed: Retraction evidence unavailable; repair canonical state before cleanup.
```

The message establishes that `ExchangeRetractionState::read()` returned null. It does not identify the failing record or prove corrupt canonical data. `preview()` throws before artifact scanning or exchange-store inspection; this invocation performed no cleanup. Production filesystem, configuration, permissions, Git history and deployed revision have not been inspected directly.

The absent-target condition is a plausible explanation given the separately documented production incident involving retained retractions for deleted replies. It is not a confirmed diagnosis of this command. The high-traffic publication assessment is related context, not evidence that fixing publication also fixes this sweep.

Sources: [CLI](../../scripts/retraction_sweep.php), [sweep](../../src/ForumRewrite/Retraction/RetractionSweep.php), [evidence reader](../../src/ForumRewrite/Llm/ExchangeRetractionState.php), [removal evidence](../../src/ForumRewrite/Canonical/LabelRemoval.php), [record loader](../../src/ForumRewrite/Canonical/CanonicalRecordRepository.php), [signature and operator authority](../../src/ForumRewrite/Security/RetractionAuthority.php), [operational reference](../reference/retraction_sweep.md), [related publication assessment](high_traffic_absent_retractions_step1_solution_assessment.md).

## Failure inventory and suggested responses

Proposed codes below are suggestions, not currently supported CLI output. Report repository-relative paths and identifiers in operator diagnostics, without post bodies, credentials or raw subprocess output containing secrets.

| Condition in current evidence scan | Suggested diagnostic | Suggested recovery or engineering response |
| --- | --- | --- |
| Selected root has no readable `records/` directory | `repository_records_missing` or `repository_records_unreadable`, selected root and selection source | Show precedence: explicit `--repository-root`, then `FORUM_REPOSITORY_ROOT`, then application `state/local_repository`. Confirm intended instance and mount before changing records; report missing versus inaccessible separately. |
| Git metadata exists, but `git ls-tree ... HEAD` fails | `canonical_git_failed`, operation, exit status and sanitized cause | Distinguish unavailable Git, ownership/safe-directory rejection, unreadable metadata, invalid HEAD, damaged history and other command failures. Correct the specific environment/history problem; do not broadly disable Git ownership protections. |
| Git tracks a retraction `.txt` file that is missing or unreadable | `tracked_retraction_missing` or `tracked_retraction_unreadable`, path | Inspect checkout/sparse-checkout, filesystem and access rights. Restore only reviewed trusted evidence where appropriate; do not discard unrelated changes. |
| Removal evidence tracked in HEAD is absent | `tracked_removal_missing`, path | Inspect checkout completeness and trusted history. Keep cleanup blocked until required evidence is recovered. |
| Removal JSON is unreadable, unsafe, too large, invalid JSON or structurally invalid | Separate `removal_unreadable`, `unsafe_evidence_path`, `removal_too_large`, `removal_parse_invalid`, `removal_structure_invalid` | Identify the file and violated constraint; preserve original bytes and recover trusted evidence rather than manufacturing acceptance. Cover invalid source/thread IDs, hashes, operation and label bindings. |
| Removal record lacks exact-byte trusted local acceptance, or Git lookup fails | `removal_acceptance_missing` versus `canonical_git_failed`, path | Determine whether trusted acceptance history is missing, shallow or unavailable. Imported files alone do not establish authority. Restore trusted history or use the existing reviewed acceptance process with sufficient source evidence. |
| Retraction directory is a symlink, wrong file type, unreadable, or cannot be listed | `unsafe_evidence_path`, `evidence_not_directory`, `evidence_directory_unreadable`, `evidence_listing_failed` | Show the specific directory and reason. Verify intended layout and permissions. Preserve symlink rejection; do not automatically follow arbitrary links. |
| Individual retraction is a symlink, unreadable, cannot be hashed, or changes/disappears during scanning | `unsafe_evidence_path`, `retraction_unreadable`, `evidence_hash_failed`, `evidence_changed_during_scan` | Reject unsafe paths; recover permissions or a stable checkout. Use bounded retries only for demonstrated transient changes, followed by an explicit retryable failure. Bind validation and hashing to consistent bytes. |
| Retraction headers/body, identifiers, timestamp, operation, classification or filename are invalid | `retraction_parse_invalid` or `retraction_path_mismatch`, path and safe parser reason | Report the exact failed rule. Preserve invalid evidence for investigation; recover an authentic valid record through the existing process. |
| Signature is invalid, or an operator retraction lacks a verified signature | `retraction_signature_invalid` or `operator_signature_unverified` | Preserve current acceptance rules. Distinguish bad signature from missing key, unavailable verifier or other verification failure where the verifier can establish that distinction. Do not assume every unverified author record currently fails: loader policy treats author/operator cases differently. |
| Operator retraction lacks content-bound local acceptance | `operator_acceptance_missing`, record path and required evidence category | Separate missing acceptance from failed Git lookup in the authority helper. Restore trusted history/authority evidence or perform an explicitly reviewed existing acceptance procedure; never synthesize an acceptance commit just to pass validation. |
| Referenced post exists but cannot be read or parsed, or its path does not match its ID | `target_post_unreadable`, `target_post_parse_invalid`, `target_post_path_mismatch` | Identify retraction path, post ID and attempted target paths; retain nested safe parser cause. Include legacy timestamp recovery failures as a distinct reason when known. |
| Referenced post is absent from both resolver candidates and no accepted `delete` removal supplies authority | `retraction_target_absent`, post ID, retraction path, attempted paths and removal-evidence status | Describe an absent target, not generic corruption. Inspect whether deletion was intentional and whether trusted removal evidence exists. Do not restore deleted prose or delete retained retractions merely to satisfy the sweep. Separately assess safe absent-target cleanup semantics before relaxing the current guard. |
| Unexpected exception in removal or retraction processing | `evidence_scan_internal_error`, phase and correlation ID | Preserve the exception chain privately; expose a concise operator message. Keep unknown failures blocking and never convert them into an empty retraction list. |

Important distinctions: an absent `records/post-retractions/` directory is currently allowed if no tracked evidence is missing and removal scanning succeeds. A parsed retraction that does not apply to an existing post is not itself this failure: `RetractedPosts::applies()` returns false for author mismatch, protected authority posts and other inapplicable cases. Report those as separate scan outcomes where useful, without granting suppression authority. The strict sweep reader and absent-tolerant snapshot reader have different contracts; do not substitute one for the other globally.

## Suggested error-reporting contract

1. Replace the information-losing null boundary internally with a typed result carrying success/blocked state, effective IDs, scan completeness and structured issues. Preserve compatibility for callers that require null on any failure until deliberately migrated. Never use partial IDs to authorize destructive cleanup.
2. Each issue should contain a stable code, phase, safe relative path, applicable post/record ID, cause category, retryability and concrete next action. Record selected repository and configuration source once. Retain a sanitized exception chain in private diagnostics, with a correlation ID for unexpected failures.
3. Human output should lead with the actual blocker and explicitly state that preview cleanup did not occur. For example: `Retraction preview blocked [retraction_target_absent]: <post-id>; evidence: records/post-retractions/<record>.txt. Neither expected post path exists and no accepted deletion evidence was found. No cleanup performed.` Show safe inspected paths and the relevant recovery action next.
4. Make `--json` failures structured on a documented stream, with nonzero exit status and stable fields such as `status`, `code`, `issues`, `scan_complete`, `repository_root`, `configuration_source`, `retryable` and `cleanup_performed`. Currently exceptions always produce plain text on stderr even with `--json`.
5. A read-only evidence diagnostic command/mode should collect a bounded number of independent issues, report truncation and scan completeness, and permit continuation past a bad record for diagnosis only. Bound expensive Git/history work as well as issue count; the existing sweep row limit does not cap canonical validation.
6. Carry the same issue codes through manual run, persisted sweep status, task-queue failures and private logs. Currently run saves only `failure: cleanup_failed`, the worker reduces errors to a generic cleanup message, and preview does not save its failure. Preserve preview's read-only behavior rather than writing a status file as an incidental diagnostic side effect.
7. Keep public/browser output concise and omit private paths and evidence details; place detailed reporting in operator CLI/private diagnostics. Do not expose removed prose, signatures, key material or entire records in errors.

## Suggestions for absent-target cleanup

Treat this as a separate behavior decision from better messages. An intentionally absent post can still have surviving derived HTML or exchange payloads. Simply ignoring its retraction can therefore leave content behind. Conversely, treating every parsed record as effective removal can grant unsupported authority.

Compare retaining strict cleanup with actionable evidence repair against a narrowly authorized absent-target cleanup design. The latter must establish which evidence is sufficient, prove dependency/exclusion behavior for generated artifacts and exchanges, retain malformed/untrusted evidence failures, and fence concurrent deletion, reappearance and evidence changes. Existing snapshot handling is useful precedent, not automatic authorization or proof of sweep correctness. Keep published replies, canonical history and other excluded stores outside cleanup scope.

## Suggested verification and delivery sequence

- First add source-level reason preservation and read-only diagnostics, then propagate those reasons through CLI, JSON, status and worker reporting. Preserve current cleanup authority while making production diagnosis actionable.
- Cover every inventory row with existing-style fixtures, including missing/unreadable directories and files, broken Git/HEAD, missing tracked files, unsafe paths, malformed records/JSON, signature and acceptance failures, invalid posts/legacy timestamps, and absent targets with and without accepted deletion evidence. Verify permission cases under a user for whom access restrictions actually apply.
- Verify success controls: no retractions, accepted deletion of an absent target, valid author/operator records, nonapplicable retractions and normal bounded cleanup. Check that a late scan failure never permits partial destructive cleanup.
- Assert human messages, structured JSON errors and nonzero exit codes; verify private persisted/worker causes and redaction. Confirm preview/diagnostics create no databases, progress files or canonical changes. Add concurrency/retry checks for file replacement and target reappearance before considering changed absent-target semantics.
- In production, first collect the deployed revision, selected repository/configuration source and read-only evidence diagnosis under the actual CLI and worker users. Select recovery only after the blocker is known; rerun preview before any cleanup. A clean local source review does not establish production recovery.

Dependency delta for this report: zero. No runtime code, production state, task queue, UI, existing effort-study records or `todo.txt` changed. Implementation proposals require the normal project planning and approval process.
