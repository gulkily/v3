> **Feature plan:** [Step 1](./import_archive_hardening_step1_solution_assessment.md) · [Step 2](./import_archive_hardening_step2_feature_description.md) · [Step 3](./import_archive_hardening_step3_development_plan.md) · [Step 4](./import_archive_hardening_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/import_archive_hardening; [ledger](../effort_estimation_trial.md#cycle-v3import_archive_hardening).

Extended estimates: enabled

**Cycle estimate: 1.00**

## Entry

Step 3 approved. Planning commit: `17ae0250`; branch `fix/import-archive-hardening`. Scope v1 clarified / R1 / P2 / original baseline `00c84badd8832ed9e81fa0980c670283ae9fa318`. Step 4 entry projection 1.00 reaffirmed on 2026-10-27, Codex single exposed rater. Original S1–S8 estimates: 0.15, 0.15, 0.25, 0.20, 0.25, 0.30, 0.25, 0.20. Non-additive stage work; shared acceptance owned by S8. No measured labor or maintainer acceptance claimed. Dependency inventory: zero additions/updates. Existing review and ledger records remain pending stage documentation, excluded from planning commit.

## Stage 1 - Budget and compatibility contracts

**Original estimate: 0.15**

**Retrospective stage work: 0.15**

- Changes: shared monotonic deadline, operational-limit exception, record-size constants and bounded Linux process-group runner. Existing installed setsid/POSIX facilities used without dependencies.
- Verification: `php tests/run.php BoundedProcessTest` 2/2; stdout/stderr/exit status, expired budget, noisy child and forked stalled descendant. Descendant was absent or terminated, not running; limit returns under two seconds for 150ms budget. Fixture inventory: 5 armor files, maximum 803 bytes; 20 text files, maximum 2,890 bytes. Prior real ZenMemes acceptance provides representative archive evidence; full limit acceptance retained for S8.
- Inventory: direct verifier users are ReactionSignature, AuthApiController, LocalWriteService, Railspike WorkflowService and audit_post_signatures; generic parser feeds all canonical families and ReactionSignatureIndex. Import indirect reads include repository, timestamps, accepted retractions/legacy removals and identity catalog. Source history uses isolated Git; publication invokes candidate building and offline generation. These call paths are covered by S3/S5/S6.
- Notes: runner ownership is a process group; invocation supervision must additionally handle nested owned groups (S6). No global installation or promise of an OS sandbox. Original scope/estimate unchanged; no measured labor.

*— Codex · 2026-10-27 UTC*

## Stage 2 - Bounded-memory text validation

**Original estimate: 0.15**

**Retrospective stage work: 0.10**

- Changes: a Unicode category check replaces the per-character PHP array while retaining permitted tab/LF and rejection of other category-C characters.
- Verification: `CanonicalTextBoundsTest CanonicalRecordParsersTest` 49/49, including a full 16 MiB record in a 128 MiB child, multibyte prose, controls/private-use/invalid bytes and all canonical families. S1 commit: `00f2dfbe`.
- Notes: no signed-byte changes; this bounds parser overhead, not whole-import memory. Original scope unchanged; no new dependencies.

*— Codex · 2026-10-27 UTC*

## Stage 3 - Shared admission and filename policy

**Original estimate: 0.25**

**Retrospective stage work: 0.25**

- Changes: bounded actual reads plus stat admission for canonical records/companions, reaction keys and metadata; local preflight prevents swallowed destination-limit errors. UTF-8 archive names required before extraction, including excluded categories. Storage/resource exceptions propagate instead of being categorized as malformed content.
- Verification: planner/archive/canonical groups 62/62; new admission and normal HTTP/CLI tests 12/12. Exact/over limits, both companion suffixes, oversized indirect source/fallback keys, independent valid content, bad filenames and untouched extraction destination covered. S2 commit: `b679f23c`.
- Notes: accepted retraction/removal evidence retains existing 1 MiB restrictions; timestamps retain 4 KiB. No signature repair or byte rewriting. Scope/estimate unchanged.

*— Codex · 2026-10-27 UTC*

## Stage 4 - Linear dependency rejection

**Original estimate: 0.20**

**Retrospective stage work: 0.20**

- Changes: reverse dependency edges and a work queue replace repeated complete-plan scans. Signature/record coupling and existing state decisions remain; a diagnostic visit counter makes complexity testable without machine-speed assumptions.
- Verification: planner/recovery 15/15, then strengthened planner 13/13. A 4,000-record reverse chain plus diamond and valid cycle uses fewer than ten visits per entry; deep root cause, valid cycles, conflicts, missing dependencies and signatures retain expected behavior. S3 commit: `99030016`.
- Notes: initial path order and dependency order remain deterministic; rejection wakes only affected entries. Scope/estimate unchanged.

*— Codex · 2026-10-27 UTC*
