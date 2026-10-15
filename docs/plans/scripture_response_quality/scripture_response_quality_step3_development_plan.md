> **Feature plan:** [Step 1](./scripture_response_quality_step1_solution_assessment.md) · [Step 2](./scripture_response_quality_step2_feature_description.md) · [Step 3](./scripture_response_quality_step3_development_plan.md) · [Step 4](./scripture_response_quality_step4_implementation_summary.md)

Debug mode: enabled

# Scripture Response Quality and Latency Development Plan

## Completion Contract

- Normal scripture/Expand requests must reach complete visible results with existing permissions, moderation, deduplication, source checks, and explicit recovery. Meet every [Step 2 quality, latency, and budget criterion](./scripture_response_quality_step2_feature_description.md); failed gates require reassessment.
- Use isolated live HTTP/browser/worker trials; preserve historical replies and unrelated modes. No schema change or remote deployment. Document worker setup and rollback. Commit approved plans first, then each stage with its summary. Each stage targets one hour.

## Key Risks

- **High risk: misleading timing.** Minute-based cron and missing initial polling can dominate waiting. Validate actual scheduling/browser discovery first; never benchmark direct fulfillment as normal delivery.
- **High risk: publication regressions.** Test busy-worker, crash, and duplicate recovery before live calls; scope settings/dispatch to scripture and retain rollback configuration.
- **Accuracy/overfitting:** Freeze cases and candidates; independently check quotations/context against source passages. Reject critical errors; no judge-model calls or mid-trial tuning.

## Frozen Evaluation

- A: current working-tree prompts/settings, including the approved scope edit. B: revised guidance/examples, same settings. C: B with `gpt-5-nano` low reasoning, preserving completion allowance. [Official support](https://developers.openai.com/api/docs/guides/latest-model?model=gpt-5); validate compatibility before trials, without silently substituting candidates.
- Eight questions: (1–2) the exact late-start and overloaded-day posts linked in Step 1; (3) “I am impatient with a slow coworker. How should I respond?”; (4) “I promised to help a friend but now my day is busy. What should I do?”; (5) “Does forgiveness require trusting someone again? Explain differing Christian interpretations.”; (6) “My father died and I worry that my grief shows weak faith.”; (7) “Give a detailed biblical discussion of Sabbath rest, context, and Christian interpretations.”; (8) “Use NIV. Is ‘God helps those who help themselves’ in Hezekiah 4:12? What should I make of this advice?” Exclude cases 3–8 from prompt examples. Expand cases 1, 5, 8.
- Repeat each twice; interleave matched variants. Time submission through rendered completion, recording worker cadence, refresh behavior, cold/warm state, failures, and non-overlapping stage durations. Review anonymized outputs before labels; rank qualifying candidates by quality, then latency, then cost. Reserve worst-case cost before calls; retries consume the approved budget.

## Stage 1

- Goal: Reproducible normal-flow measurement.
- Dependencies: Step 3 approval.
- Expected changes: Isolated fixtures, frozen rubric/prompt hashes, timing/budget ledger.
- Verification approach: Stub HTTP/browser/worker runs and budget-stop tests.
- Risks or open questions: Measurement/isolation gaps block trials; no secrets in reports.
- Canonical components/API contracts touched: Browser fixtures, exchange diagnostics, queue status.

## Stage 2

- Goal: Evaluate initial-generation candidates.
- Dependencies: Stage 1.
- Expected changes: Coherent guidance/examples; optional scripture-only reasoning through existing options.
- Verification approach: Payload/routing, unsupported-setting, exhaustion, other-mode tests.
- Risks or open questions: Preserve unset/other-provider behavior; defer candidate activation.
- Canonical components/API contracts touched: Generator, provider payload, PostWorkflowService, private configuration, prompts.

## Stage 3

- Goal: Prompt initial execution and visible completion.
- Dependencies: Stage 2.
- Expected changes: Scripture worker dispatch; read-only status discovery and canonical result rendering.
- Verification approach: Browser flow, duplicate requests, busy-worker, launcher failure, explicit retry.
- Risks or open questions: Preserve drafts/locks/scheduled recovery; contention must not strand work; polling cannot regenerate.
- Canonical components/API contracts touched: Request/status routes, launcher/worker, post_analysis.js, thread/post cards.

## Stage 4

- Goal: Equivalent expansion delivery.
- Dependencies: Stage 3.
- Expected changes: Expansion guidance, shared settings/dispatch, timings, operator setup notes.
- Verification approach: Expansion/browser, duplicate, stale-source, revoked-access, contention tests.
- Risks or open questions: Preserve stored-output recovery, deterministic publication, requester auto-open.
- Canonical components/API contracts touched: ScriptureExpansionService, shared queue/generator, expansion API/partial, CLI docs.

## Stage 5

- Goal: Select a provisional winner.
- Dependencies: Stages 1–4 verified; freeze all candidate artifacts.
- Expected changes: 48 live initial samples with source, quality, timing, cost evidence.
- Verification approach: Unchanged Step 2 initial gates; retain every result and trial condition.
- Risks or open questions: No qualifier or insufficient remaining budget requires reassessment.
- Canonical components/API contracts touched: Isolated normal HTTP/worker/publication/browser flow, evaluation report.

## Stage 6

- Goal: Verify expansion and release.
- Dependencies: Stage 5 qualifying candidate.
- Expected changes: 12 expansion trials; promote identical tested artifacts; rollout/rollback documentation.
- Verification approach: Expansion gates; focused integration/browser, cache/offline, other-mode and rollback checks using stubs/stored results.
- Risks or open questions: Failure blocks promotion; no extra live smoke calls or untested edits.
- Canonical components/API contracts touched: Existing rendering, profile/RSS exclusions, snapshots, settings, operator documentation.
