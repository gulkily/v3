# Railspike effort estimation calibration study

Study date: 2026-10-18. Author: Railspike development agent. Audience: Railspike maintainer.

Ten historical cycles in `/home/wsl/v3` support a useful initial scale of relative cycle capacity. Adopt the following working definition:

> **1.0 is the largest well-scoped, releasable slice we would comfortably approve for one Railspike cycle, including required verification and documentation.**

Use startup dependency recovery as a provisional full-cycle reference and the smaller examples below as fractional references. Treat private-message history synchronization and image support as examples to challenge or split before approving at their historical scope. Composer decluttering remains a verification caution, but implementation review places it below the full-cycle ceiling. Completing a recorded cycle does not establish that its scope comfortably fit.

The numerical assignments below are proposed retrospective judgments by one analyst. The records establish scope, recorded outcomes, and concrete verification burdens; they do not measure effort or establish a statistically calibrated ceiling. A prospective trial is needed before treating the numbers as reliable predictions.

## Scope and method

The study follows the [estimation handoff](../../../inbox/railspike_effort_estimation_handoff.txt) and the user's refinement from maximum work one could cram into a cycle to work one could comfortably approve. It uses a purposeful sample: two small changes, three modest features, three substantial features, and two cautionary cases. This selection tests the proposed scale; it cannot describe the distribution of all Railspike work.

For each case, compare Step 2 requirements, Step 3 stages and recovery obligations, Step 4 outcomes and exceptions, and the implementation and review commits. The initial pass examined commit manifests; the subsequent [implementation diff review](./diff_review.md) inspects production patches across all 50 selected commits, representative test bodies, and changes between each cycle's starting and ending revisions. All test patch inventories were screened; the larger private-history and image test bodies were selectively inspected. This is a static effort review, not a comprehensive correctness or security audit.

The table links the current summaries, whose navigation leads to the plans. The [source manifest](./source_manifest.json) preserves hashes of all 30 plan/summary files, the source checkout revision, and 50 full commit IDs with changed-file lists. The [diff evidence](./diff_review.json) records exact comparison boundaries, per-stage and combined change counts by category, and initial versus revised scores. Source manifests and static test inspection do not establish execution of historical tests.

The analysis is retrospective and was not blinded to outcomes. Current plans can include later annotations; private-history plans explicitly include a review change removing global status UI. That change is treated as review work, not an original requirement. The study did not rerun historical application tests, collect active-work duration, or obtain independent ratings. Historical pass/failure statements below are reports from the source records.

## Proposed numerical references

Scores refer to reproducing the scoped change from its historical starting point, with its existing infrastructure available. They are not estimates of remaining work on today's code. Explanations belong in this calibration report; the visible backlog can retain a numerical column without per-item explanations. Values between the references remain freeform.

| Historical scope | Proposed cycles | Planned stages | Calibration role |
| --- | --- | --- | --- |
| [Suppress a duplicate thread-card preview](../../../../v3/docs/plans/thread_card_remove_preview/thread_card_remove_preview_step4_implementation_summary.md) | 0.10 | 2 | Small conditional presentation change |
| [Make about-page copy specific to the club profile](../../../../v3/docs/plans/mitrapclub_about_copy/mitrapclub_about_copy_step4_implementation_summary.md) | 0.20 | 2 | Small content and template integration |
| [Resolve short numeric QDB links](../../../../v3/docs/plans/qdb_numeric_permalinks/qdb_numeric_permalinks_step4_implementation_summary.md) | 0.25 | 2 | Bounded routing and lookup extension |
| [Emphasize the QDB score through live updates](../../../../v3/docs/plans/qdb_score_display_emphasis/qdb_score_display_emphasis_step4_implementation_summary.md) | 0.30 | 3 | Presentation with client-state preservation |
| [Show and update toolbar identity status](../../../../v3/docs/plans/toolbar_identity_status/toolbar_identity_status_step4_implementation_summary.md) | 0.35 | 4 | Server and browser state across shared views |
| [Recover dependencies through ordinary startup](../../../../v3/docs/plans/startup_dependency_recovery/startup_dependency_recovery_step4_implementation_summary.md) | 1.00 | 5 | Provisional full-cycle reference with existing installer |
| [Synchronize private-message history across devices](../../../../v3/docs/plans/private_message_history_sync/private_message_history_sync_step4_implementation_summary.md) | 1.20 | 8 | Challenge full scope before approving one cycle |
| [Publish images with protected delivery and portable backups](../../../../v3/docs/plans/image_content/image_content_step4_implementation_summary.md) | 1.20 | 8 | Challenge full scope before approving one cycle |
| [Declutter the composer and shared image widget](../../../../v3/docs/plans/compose_thread_declutter/compose_thread_declutter_step4_implementation_summary.md) | 0.60 | 7 | Moderate UI integration with incomplete verification |
| [Install dependencies through an explicit CLI mode](../../../../v3/docs/plans/local_server_dependency_install/local_server_dependency_install_step4_implementation_summary.md) | 0.90 | 5 | Effort comparison only; recovery gap prevents a clean anchor |

The differences between 0.25, 0.30, and 0.35 express an initial ordering and approximate size. They do not establish that one feature requires exactly 20 percent more work than another. These values are reference examples, not allowed increments or score bands. The sample contains no completed pure wording edit, so it does not validate the handoff's 0.02 example.

## Evidence behind the references

### Small changes

**Duplicate preview suppression** changes one template conditional and adds direct-render coverage. The summary records manual page checks, a successful focused test, and six existing full-suite failures. An initial full-page fixture rendered no thread cards, requiring a different test approach. This is a useful 0.10 reference because it includes locating the correct shared template and verifying both changed and preserved behavior, rather than counting only the conditional itself. Git records two implementation commits touching three unique files, including the summary.

**Club about-page copy** sounds editorial but requires extending existing per-profile data and wiring a template while retaining functional inline links and byte-identical output for other profiles. Both stages report 817/817 tests passing on their final runs. A leaked comment and whitespace difference were corrected during byte comparison. This warrants a larger reference than a wording substitution: 0.20 includes content integration and regression review. Its two stages also touch three unique files, illustrating why file count cannot distinguish these two small cases by itself.

### Modest features

**Numeric QDB permalinks** reuse existing quote numbering and the last-resort route path. The two-stage slice adds repository lookup, redirects, shared card links, missing-number behavior, and non-QDB protection. The summary reports 9/9 focused tests and a live local click-through, with seven pre-existing full-suite failures. Existing numbering infrastructure is essential to the 0.25 comparison; building numbering from scratch would be additional work.

**QDB score emphasis** spans two renderers, theme styles, and optimistic/confirmed/restored score updates. Its three stages report 92/92 focused tests; the full run retains one longstanding failure. Diff inspection confirms structured score capture/restore code and a newly added confirmed-refresh test running in Node with a mocked DOM. That test does not launch a browser or directly exercise structured failed-vote rollback. Use 0.30 as a scope reference without treating the reported test count as proof of every recovery path.

**Toolbar identity status** spans server seeding, shared markup, local-key correction, and cross-tab storage events. It used four stages plus a user-directed icon-link restyle. The summary reports route renders, simulated browser-state checks, and a headless Chromium review; the cross-tab verification is simulated rather than a documented real two-tab journey. The committed test diff only updates two constructor calls, so the reported ad hoc behavior checks are not preserved as new regression tests in these commits. Its 0.35 reference includes the recorded restyle and verification work. The fourth stage adds just one event listener; four stages here are not evidence of half of a full cycle.

### Substantial features

**Startup dependency recovery** is the strongest full-cycle candidate in this sample because it has a concrete normal entry, usable outcome, and extensive refusal/recovery evidence. It extends an existing installer to Ubuntu 22.04 as well as 24.04, then integrates reviewed terminal recovery into startup while preserving caller privileges and options. Five stages cover mapping, installation, continuation, failure boundaries, and acceptance/documentation. The [acceptance record](../../../../v3/docs/plans/startup_dependency_recovery/acceptance_evidence.md) describes fresh-image missing-runtime and missing-extension journeys, password-sudo, manual repairs, HTTP writes/reads, shutdown, and cancellation checks.

This is a proposed 1.00 reference conditional on the installer and test machinery already existing. Final acceptance still exposed a prompt-boundary signal defect, so the reference must include corrective work during verification. Chroot checks do not certify the user's complete WSL environment. The record supports a substantial, bounded slice with strong acceptance documentation; it does not prove an absolute maximum or that elapsed work stayed within a day.

**Private-message history synchronization** adds cryptographic key transfers, a separate private database, authorized APIs, donor coordination, reader integration, partial-donor reconciliation, and restore/rollback handling. Eight stages plus a review change touch 44 unique files. The retained release results record 18/18 focused checks and eight full-suite failures: seven reproduced in a baseline worktree; the remaining task-queue failure was attributed to existing history but did not reproduce there. Real-key browser journeys and retained results make this useful evidence of delivery breadth, without making it a green full-suite claim.

At 1.20, the proposal is that this much coupled protocol, storage, UI, and recovery work deserves a split discussion before approval. The fact that it was completed in eight stages is not grounds to redefine it automatically as 1.00. A split must still deliver verified reading from normal entry with required privacy and retry behavior; storage-only or API-only stages do not count as releasable features.

**Image content** couples signed publication, private storage, protected serving, static-cache invalidation, shared compose/rendering, revision-bound backups, independent restore, and Apache behavior. Eight stages plus the optional-description follow-up touch 52 unique files. Final browser integration found signed-byte newline normalization defects; capacity checks also exposed storage-cache behavior. The summary reports successful browser/Apache journeys and eight full-suite failures reproduced on the pre-feature baseline, while identifying inline/dialog browser coverage and process-kill fault injection limits.

The proposed 1.20 reflects the combined preservation, access-control, and verification obligations. This is not proof of an overrun. The recommended planning response is to narrow a complete first release, for example to one normal authoring surface while retaining protected reads and backup/restore, then add other authoring surfaces in a separate usable release. Required recovery cannot simply be deferred to make the first slice smaller.

### Cautionary cases

**Composer decluttering** bundles page wording, two disclosure sections, warning auto-open behavior, a shared image widget on five surfaces, and an action menu. Seven planned stages plus a follow-up touch 15 unique files. The final summary reports 241/241 selected PHP tests and browser fixtures passing, but explicitly leaves several promised real-browser checks unperformed: events-enabled layout, QDB add, the paned dialog, mobile menu width, and the real unsupported-character warning flow. A later DOM-order check covers one narrow issue and does not close that matrix.

The initial 1.10 judgment over-weighted stage count and missing verification. Static implementation review revises it to **0.60**: native disclosure elements, one summary/warning/menu script, CSS and markup changes, and a visibility change in the existing uploader. Existing upload transport, signing, storage, backup and restore are reused. Completing the intended verification is part of this revised estimate; no measured remaining cost is available. Separating the shared image-widget redesign could still simplify review, but incomplete checks alone do not establish that the feature exceeded cycle capacity.

**Explicit dependency installation** has substantial real Ubuntu 24.04 package, privilege, cancellation, and local-server testing across five stages. However, the later [failure report](../../../../v3/docs/plans/local_server_dependency_recovery_railspike_failure_report.md) documents that the known Ubuntu 22.04 user's manual recovery remained vague despite the original requirement for actionable fallback. This case cannot establish a comfortably complete cycle merely because its summary said all stages were complete.

Its proposed 0.90 describes the substantial bounded implementation work, with the missed fallback called out separately. The subsequent startup-recovery cycle both repairs that gap and introduces newly selected behavior, including startup prompting and continuation. Charging all of that later cycle to the original installer would confuse rework with added scope. Neither failure nor test volume supplies an automatic numerical multiplier.

## Findings from implementation review

The diffs support the broad distinction between small extensions, moderate integration, and new subsystems. They also expose composition that file totals hide: much of the about-page patch is literal profile content; the startup cycle reuses the earlier installer; image support adds private storage and independent restoration; private-history work adds transfer authorization, cryptographic bindings and reconciliation. Test harness construction is substantive work, but recorded JSON evidence is a separate category. Startup recovery's 1,168 added evidence lines must not be mistaken for application code.

Stage history also captures work missing from the final result. Private-history status UI was built and then removed on review. The installer changes its sudo/session handling during acceptance, and startup cancellation handlers change again in the last stage. These are different kinds of revision: a requested presentation change, corrective implementation work, and test-harness corrections. Committed diffs cannot recover discarded local attempts or time spent investigating them.

Only the composer estimate changes in this revision, from 1.10 to 0.60. Startup recovery remains the provisional 1.0 reference because the diffs corroborate substantial lifecycle and acceptance work on top of known infrastructure. The eight-stage subsystem examples remain split-discussion candidates. Their 1.20 values remain judgments about comfortable scope, not measured overruns. The comparison does not establish the exact spacing between any numerical references.

## Working estimation rubric

Estimate relative work to deliver a defined outcome from the current starting point, including code inspection, necessary planning, implementation, verification, documentation, and ordinary corrective work. Exclude approval waiting and unrelated failures or cleanup. Human review remains part of the completion obligation, but the score does not forecast reviewer availability or approval delay. Do not translate 1.0 into hours.

Compare the request with the closest historical reference using four questions:

1. What normal entry, end-to-end outcome, and required recovery must be delivered?
2. Which shared components and existing capabilities are already available?
3. Which state transitions, failure paths, environments, and surfaces require verification?
4. Can the complete slice be reviewed and delivered comfortably within the existing day/eight-stage guardrail, allowing ordinary correction during verification?

The fourth question is an approval judgment, not a formula. Do not divide stage count by eight, divide file count by a reference size, or infer active-work duration from Git timestamps. Known crypto, backup, platform, or access-control obligations increase the work to perform. An undefined request needs clarification or a bounded investigation, rather than an arbitrary uncertainty surcharge.

For an actionable backlog item whose scope remains open, retain the requested numerical interface by estimating a clearly bounded next investigation or deliverable and updating its existing request text accordingly. Do not silently substitute an investigation estimate for implementation. For ActivityPub, a compatibility assessment with an explicit deliverable can be estimated; full integration still needs agreed scope.

Scores above 1.0 trigger a split discussion, not a rule that exactly two cycles are needed. Scores below 1.0 preserve all applicable approval and acceptance obligations. A low score also does not make unrelated changes one coherent release. Shared dependencies and repeated planning/verification mean estimates are not necessarily additive.

Use zero only when no implementation work is being assigned to the row. Preserve status/category distinctions between non-actionable material, reported-fixed behavior, and verified completion. The score alone must not turn a historical fixed report into a current verification claim. The live backlog continues to show numbers without individual scoring explanations.

## Prospective calibration

Use these references provisionally for the next six well-scoped items, with at least two small changes, two modest features, and two candidates near the ceiling. Keep initial estimates fixed in a separate study record; revise the current backlog number after code inspection or approved scope changes without erasing the earlier estimate.

For each item retain the request and starting revision, scope and required evidence, estimate before inspection, estimate after planning, planned versus completed stages, review corrections, omitted checks, scope changes, and whether a separate release or follow-up became necessary. Distinguish corrective work from newly requested capability. Record active-work time only if it can be collected consistently; elapsed calendar time cannot substitute for it.

Have two raters independently score the same scoped items before seeing each other's values. Compare their chosen reference examples and whether they put an item on opposite sides of 1.0. No independent-rating result is claimed by this study. Disagreements should refine scope or reference descriptions before creating a numerical conversion formula.

After those six outcomes, review whether items near 1.0 needed unplanned stages, omitted verification, repeated corrections, or rescoping. Conversely, check whether supposedly full cycles routinely had room for another coherent increment. These observations can move the references; they cannot reveal a measured maximum from six cases. Freeze the revised reference set for the next batch so changes to the unit remain visible.

## Recommendation

Use the comfortably approved definition and the revised historical references for a limited trial. Startup dependency recovery supplies the provisional 1.0 example; the smaller features show why an actual completed cycle may represent far less than full capacity. Keep coupled eight-stage features visible as boundary challenges and track incomplete acceptance separately from size. Do not use sums of these initial judgments as a delivery forecast or adopt a Railspike instruction change on the strength of this retrospective sample alone.
