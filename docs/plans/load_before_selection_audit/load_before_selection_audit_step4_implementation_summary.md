> **Feature plan:** [Step 1](load_before_selection_audit_step1_solution_assessment.md) · [Step 2](load_before_selection_audit_step2_feature_description.md) · [Step 3](load_before_selection_audit_step3_development_plan.md) · Step 4

Debug mode: enabled
Effort study: railspike-effort / v3/load_before_selection_audit; R1/P2
Extended estimates: enabled

**Cycle estimate: 0.55**

## Entry checkpoint — 2026-10-31 UTC

Step 3 approved. Planning-only commit: 5983cbf2. Branch: audit/load-before-selection. Application baseline: ade35a65d2488bcfc280277e4bbf0bc8beae76a4 (original study baseline remains cfb8364f6db03cc09e27cd26afb93c109c723a08). Documentation-only scope unchanged; no merge/push requested for this implementation turn.

Step 3 independent total 0.55 reaffirmed before execution. Single exposed Codex rater; prior audit/build-time knowledge retained. Original stage work: S1 coverage/seeds 0.08; S2 readers/probes 0.18; S3 maintenance/probes 0.18; S4 consolidation/handoff 0.11. Originals, uncertainty and ownership remain in Step 3; values are non-additive judgments, not hours. Measured labor unknown; maintainer acceptance pending.

Runtime/UI/deployment/migration checks are inapplicable to documentation changes. Source tracing and isolated measurement support only the report's stated claims; no production recovery claim. Dependency delta: zero.

*— Codex · 2026-10-31 UTC*

## Stage 1 — Coverage and seed reconciliation

**Original estimate: 0.08**
**Retrospective stage work: 0.08**

- Changes: [Coverage/evidence conventions](coverage.txt), current revision/environment and inherited seed dispositions. M02 remains in QDB; M04 remains offline; M09 is mitigated by profile default-off but retains route arrays.
- Verification: Read current methods and prior M02/M04/M09 evidence; checked TagPage selection and StaticArtifactBuilder feature gates. Cycle relative-link targets and whitespace checks passed. Changed stage files are cycle documentation only.
- Notes: No probes yet. Historical memory failures are not new observations. Tag/profile/history optimizations are credited separately; source-row scan cost is not inferred from LIMIT. Runtime/UI/deployment checks inapplicable.
- Estimate details: Original scope/uncertainty unchanged; no revisions. Shared conventions owned here; retrospective judgment includes source reconciliation and document checks, not measured labor.

*— Codex · 2026-10-31 UTC*

## Stage 2 — Public readers and consumers

**Original estimate: 0.18**
**Retrospective stage work: 0.18**

- Changes: [Findings](findings.txt) LBS01/LBS04–06 and current-contract exceptions; reader [method/commands](measurements.txt), [archived disposable source](reader_probe_source.txt) and [raw results](reader_results.txt). Traced shared readers through controllers, templates and browser consumers.
- Verification: 18 fresh-process samples at 128 MiB, three per 1000/10000 shape/mode; all exact selected-ID/count checks pass. QDB slices load 1000/10000 bodies for 25 or 5 results, PHP peak 8/60 MiB. Delivered TagPage loads 25 bodies at 2 MiB. Verified cited paths/symbols, cycle links and whitespace; approved documentation scope only.
- Notes: QDB probes isolate reader/policy slices, omit board sort/rendering and do not establish full-route parity. SQL examined rows, browser cost and production PHP 8.3 are unknown. Large timing variance retained, not converted into savings. No failed measured samples; mistaken exploratory file paths corrected. Existing effective-tag/reaction concepts remain accurate and unchanged. Full RSS/text/directory results must not be silently truncated.
- Estimate details: Original scope unchanged; no revisions. Retrospective stage judgment includes isolated probes and consumer tracing, not independent measured labor. Untraced extensions/custom clients remain coverage gaps.

*— Codex · 2026-10-31 UTC*

## Stage 3 — Publication and maintenance

**Original estimate: 0.18**
**Retrospective stage work: 0.18**

- Changes: LBS02/LBS03/LBS07/LBS08, offline-browser boundary and import/export/rebuild/queue exceptions in findings; [six maintenance samples](maintenance_results.txt), [archived source](maintenance_probe_source.txt) and reproduction notes.
- Verification: Actual snapshotThreadRows selection via reflection on isolated fixture: 201 exact IDs including oldest pinned thread; all six samples pass at 128 MiB. 1000/10000 input rows, PHP peak 4/24 MiB. Traced worker dispatch, import planner/archive, export, scoring backfill, source-history and delivered commit/evidence readers; document path/link/whitespace checks passed.
- Notes: Selection-only evidence omits full snapshot publication, selected-post copying, expansion corpus and size failures. Source-history full-log capture remains after earlier indexCommits improvements. Necessary full validation/export traversal is not classified as safely truncatable. Retraction handoff read; no new guards/diagnostics or trust-policy changes. Browser/native/host dimensions and specialized handlers explicitly deferred.
- Estimate details: Original scope unchanged, no revisions. Retrospective includes maintenance probe and caller classification; labor remains unmeasured. No runtime/UI/release/migration tests applicable to document delivery.

*— Codex · 2026-10-31 UTC*
