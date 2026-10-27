> **Feature plan:** [Step 1](./docs_publication_policy_step1_solution_assessment.md) · [Step 2](./docs_publication_policy_step2_feature_description.md) · [Step 3](./docs_publication_policy_step3_development_plan.md) · [Step 4](./docs_publication_policy_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/docs_publication_policy`, standing enrollment P2; [host ledger](../effort_estimation_trial.md#enrollment-index-after-p2).

## Original Query

This is a bad thing to happen after `./v3 import-instance`:

```text
Rendering shared pages (685/1787): /docs/plans/high_traffic_mode/high_traffic_mode_step4_implementation_summary.md.
Rendering shared pages (686/1787): /docs/plans/home_compact_thread_compose_plan_v1.md.
Rendering shared pages (687/1787): /docs/plans/http_openpgp_failure_fix_plan_v1.md.
Rendering shared pages (688/1787): /docs/plans/identity_bootstrap_signature_verification/identity_bootstrap_signature_verification_import_gap_remediation_plan_v1.md.
Rendering shared pages (689/1787): /docs/plans/identity_bootstrap_signature_verification/identity_bootstrap_signature_verification_investigation.md.
Rendering shared pages (690/1787): /docs/plans/identity_bootstrap_signature_verification/identity_bootstrap_signature_verification_step1_solution_assessment.md.
Rendering shared pages (691/1787): /docs/plans/identity_bootstrap_signature_verification/identity_bootstrap_signature_verification_step2_feature_description.md.
Rendering shared pages (692/1787): /docs/plans/identity_bootstrap_signature_verification/identity_bootstrap_signature_verification_step3_development_plan.md.
Rendering shared pages (693/1787): /docs/plans/identity_bootstrap_signature_verification/identity_bootstrap_signature_verification_step4_implementation_summary.md.
Rendering shared pages (694/1787): /docs/plans/image_content/image_content_step1_solution_assessment.md.
Rendering shared pages (695/1787): /docs/plans/image_content/image_content_step2_feature_description.md.
Rendering shared pages (696/1787): /docs/plans/image_content/image_content_step3_development_plan.md.
Rendering shared pages (697/1787): /docs/plans/image_content/image_content_step4_implementation_summary.md.
Rendering shared pages (698/1787): /docs/plans/image_upload_enhancements/image_upload_enhancements_debug_log.md.
```

Do we also need to build all those pages? What dictates that we do?

Please write Step 1.

## Understood Intent

Decide which documentation should be prebuilt, and when, so an instance import does not routinely render the entire local documentation library.

## Problem and Existing Contract

Imports trigger complete static publication, which rebuilds every eligible local document regardless of whether documentation changed.

The [original Docs plan](../platform_docs/platform_docs_step3_development_plan.md#stage-3--public-discovery-and-static-release-support) required static document publication; [Docs discovery requirements](../docs_discovery/docs_discovery_step2_feature_description.md#core-requirements) expanded this to the full eligible library, including plans/history, with dynamic/static parity. These dictate availability, not rerendering after every content import. The [builder](../../../src/ForumRewrite/Host/StaticArtifactBuilder.php) currently couples the two. The reported 1,787 is a shared-route total, including browse pages, not solely document pages.

## Option A: Render Documentation on Demand

Serve documentation when requested on PHP hosts; reserve full prerendering for explicitly selected static exports.

- **Pros:** Removes bulk documentation rendering from normal imports, including the first import; retains the full library on dynamic hosts.
- **Cons:** Changes the existing static-host contract unless exports remain automatic there; requires delivery-mode and stale-static routing rules. Request-time inventory/search cost remains.

## Option B: Reuse Unchanged Published Documentation

Keep the complete static library, but rebuild it only when documentation or its rendering dependencies change; reuse a verified compatible library during content publication.

- **Pros:** Removes repeated rendering during ordinary imports while preserving existing URLs, search, downloads and static-host access.
- **Cons:** First publication or changed inputs still requires the full build; compatibility must include presentation, profile and other relevant inputs, not just document text. Copying and validation still have a cost.

## Option C: Prebuild Only Curated Guidance

Publish a small selected set; offer the remaining library through dynamic routes or source links.

- **Pros:** Reduces initial and repeated static-build work and storage.
- **Cons:** Removes complete static-library access, breaking the existing history/search/link contract; requires an explicit product decision about excluded material.

## Recommendation and Viable Slice

Choose **B** for the first slice: distinguish documentation publication from imported-content publication, reuse only a complete compatible library, and rebuild on a cold start, changed inputs or failed validation. Preserve complete-release activation, private-instance gates and interrupted-import recovery. Verify an unchanged repeat import avoids document rendering; changed/deleted docs and relevant presentation inputs invalidate reuse; a missing/damaged library rebuilds safely. Keep full dynamic/static browsing, search and downloads working. No database schema change is expected.

A is a valid alternative if the desired product decision is to stop providing automatic complete static documentation. C is not recommended because it reduces an established feature. This assessment authorizes no implementation; Step 1 approval is pending.

## Study Snapshots

- Cycle `v3/docs_publication_policy`; scope v1; start `be2be9d4ad4c172db33147239ecca6d2ca860d4a`; R1/P2; Codex, single exposed rater; UTC 2026-10-21T19:40:41Z. Intake before detailed inspection was **not collected**: import/publisher/builder/catalog/controller and prior-plan inspection already occurred. No retrospective intake score is invented.
- Step 1 handoff: **0.65 projected total** for B, including investigation, planning, implementation, compatibility/recovery checks and dynamic/static verification. Larger than a small rendering change because reuse must preserve publication correctness; below the provisional startup-recovery 1.0 anchor. Frozen R1 hashes verified against the report and diff review in `/home/wsl/railspike-dev/docs/research/effort_estimation/` and exact feedback bytes at `/tmp/reply-r1-feedback.md` (recoverable from Git revision `4f6cfe2b436fae8d939b1349fe724a3879ee0a0e`). Prior calibration/feedback scores and source inspection were visible; no independent-rating or measured-effort claim. Approval and maintainer acceptance remain pending; no parent/replacement cycle.

## Approval Record

2026-10-21T20:24:13Z — User replied “Approved Step 1”; Option B advances to Step 2. The original handoff and study snapshot above remain historical.
