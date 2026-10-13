> **Feature plan:** [Step 1](./rebuild_thread_label_stall_step1_solution_assessment.md) · [Step 2](./rebuild_thread_label_stall_step2_feature_description.md) · [Step 3](./rebuild_thread_label_stall_step3_development_plan.md) · [Step 4](./rebuild_thread_label_stall_step4_implementation_summary.md)

Debug mode: enabled

Planning commit: `686d8766`. Branch: `feature/rebuild-progress`.

## Stage 1 - Label history batching
- Changes: Bulk label provenance with resolution timing, isolated Git fixtures, and process-count/provenance regressions. Combined merge diffs preserve merge-resolution attribution for newly batched families; existing post behavior remains unchanged.
- Verification: `php tests/run.php ReadModelProvenanceTest ReadModelThreadLabelsTest` — 6 passed. Covers ordinary merges, conflicting merge resolution, untracked paths, invalid Git HEAD, no-Git label fixtures, scores and votes. Label history calls stay at one for 1 versus 110 generated records.
- Notes: Before edits, captured three runs on a fixed local 386-commit history with 210 generated labels, 135 reactions, 40 flag changes plus the minimal fixture. Label baseline: 7946.1 / 8484.1 / 11496.9 ms. First batched sample: 94.5 ms; all non-metadata/non-sequence tables matched baseline. Benchmark artifacts retained in `/tmp/rebuild-history-76baf7d9581b` during this run. A failing merge-resolution regression exposed the need for combined diffs; it passes after the correction. No production execution.
