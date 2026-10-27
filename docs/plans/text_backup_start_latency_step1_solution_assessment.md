> **Feature plan:** [Step 1](./text_backup_start_latency_step1_solution_assessment.md) · [Step 2](./text_backup_start_latency_step2_feature_description.md) · [Step 3](./text_backup_start_latency_step3_development_plan.md) · [Step 4](./text_backup_start_latency_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/text_backup_start_latency; [ledger](./effort_estimation_trial.md#cycle-v3text_backup_start_latency); standing P2 enrollment
Extended estimates: enabled
Status: Proposed Step 1; implementation unapproved

**Cycle estimate: 0.65**

## Original Query

On production at zenmemes.com, the text backup still takes 5–7 seconds before it kicks off the download. How else can we optimize it? Please write Step 1.

## Understood Intent

Reduce click-to-download latency for Backup’s content repository archive, preserving a complete, current, restorable backup. Confirm the exact format during measurement; this assessment assumes the normal tar.gz/ZIP links, rather than Account Key text export.

Problem: The reported production delay remains after archive reuse and committed-tree staging optimizations; the deployed revision, cache outcome and dominant phase have not been measured.

Evidence: Current local code already avoids hashing source contents for freshness and stages committed Git content. Warm requests still acquire the writer lock, run two Git status checks, enumerate image metadata and hash the entire cached archive before returning it; cold requests additionally stage, scan and compress. These are candidates, not a production diagnosis ([export](../../src/ForumRewrite/Images/ImageBackup.php), [identity](../../src/ForumRewrite/Images/ExportIdentity.php), [cache](../../src/ForumRewrite/Images/ArchiveCache.php)). Earlier synthetic warm timing of 0.416 seconds predates current changes and does not establish zenmemes.com performance.

## Option A — Measure and streamline validated downloads

**Estimate: 0.65**

Measure the normal route on the deployed version, distinguish cold/miss from repeated warm downloads, then remove the dominant avoidable preparation work while preserving freshness and integrity. Scope includes safe cold-path reductions if misses dominate; retain completed-artifact delivery.

- Pros: Targets the actual delay; can reduce redundant scans, image coupling, lock duration or repeated archive verification without adding a worker service.
- Cons: Gains depend on the measured phase; replacing checks requires explicit invalidation and corruption coverage. Untracked/dirty changes, retractions and concurrent writers must remain safe. Driver: validation-contract and real-route verification; uncertainty: production bottleneck (whole-cycle range 0.45–0.85).

## Option B — Prepare archives ahead of downloads

**Estimate: 0.95**

Refresh validated archives after content changes or through existing scheduled work so clicks normally find a completed current artifact; preserve bounded on-demand recovery when preparation lags or fails.

- Pros: Moves cold staging/compression off the click path; useful if frequent writes make warm hits rare.
- Cons: Adds scheduling, coalescing, readiness and failed-worker recovery; request-time admission still needs to be fast. Driver: lifecycle integration; uncertainty: write frequency and host worker capacity (range 0.75–1.15; split if the larger scope proves necessary).

## Option C — Stream archive generation directly

**Estimate: 0.90**

Send archive bytes as generation proceeds so the browser can start receiving the download before assembly finishes.

- Pros: Can improve cold time to first byte without background scheduling.
- Cons: Errors after headers can leave an apparently downloaded incomplete backup; snapshot, generated metadata and proxy buffering complicate recovery. Driver: streaming completeness and browser/import verification; uncertainty: host buffering and format parity (range 0.70–1.10).

## Recommendation and approval

Choose A: one complete slice from the existing Backup click through a valid download and failure/retry, for tar.gz and ZIP through the shared path. First separate application/bootstrap, lock wait, freshness checks, cache verification, build and response/proxy delay; compare repeated unchanged downloads with the first download after a write on zenmemes.com. Set a measured latency target in Step 2; aim for a subsecond warm start without promising an unmeasured cold target. Verify extraction/import, current content after edits/retractions, corruption rejection and concurrent retry. Do not simply cache by HEAD, skip validation, serve stale backups or flush headers to disguise preparation. If cold builds dominate and safe reductions cannot meet the target, revise toward B before expanding scope. No new dependencies, schema/format changes, deployment or worker termination proposed.

## Study snapshots

Scope: v1/A; baseline c349d7fe12cfb651cd4002fc51ac299d5907dd58; distinct follow-up to v3/archive_export_contention, whose local completion and pending production acceptance remain unchanged. Full-cycle estimates include inspection, planning, implementation, verification, documentation and ledger upkeep; alternatives are non-additive R1 judgments, not hours.
Intake: 2026-10-26T17:49:57Z; 0.65, recorded after source and predecessor-plan exposure; pristine pre-inspection intake missing, not reconstructed. Codex single exposed rater; all three host-local R1 hashes verified. Step 1 handoff: same date/scope/reference, 0.65 reaffirmed. No production measurement, measured labor, independent rating or accepted outcome. Await Approved Step 1.

*— Codex · 2026-10-26 UTC*
