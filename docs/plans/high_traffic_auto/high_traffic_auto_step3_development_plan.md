> **Feature plan:** [Step 1](./high_traffic_auto_step1_solution_assessment.md) · [Step 2](./high_traffic_auto_step2_feature_description.md) · [Step 3](./high_traffic_auto_step3_development_plan.md) · [Step 4](./high_traffic_auto_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/high_traffic_auto`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.70**

**Concepts touched:** [static publication](../../concepts/static_publication.md): one added line in its high-traffic note (Stage 7).

## Completion Contract

- Normal entry: the operator turns on **Turn on automatically**, and **Turn off automatically** where cron runs, on the operations page or with `./v3 high-traffic`.
- End-to-end outcome: with the mode off, a request rate above the threshold turns the mode on within seconds, keeping still-fresh pages; the reason line shows the trigger and rate. With turn-off on, 30 quiet minutes in the access log turn it off; without it, the mode stays on.
- Recovery: the existing mode switch and both automation switches work at any time. A manual on is never turned off automatically; a manual off holds until the rate falls below the threshold; a safety withdrawal keeps automation out until a manual on. An unreadable log keeps the mode on and reports why. A failed trigger still serves the request.
- Deployment and external verification: the maintainer deploys to zenmemes.com and confirms the cron line; Stage 7's stress-probe replay runs there.
- Release condition: merged, deployed, stress-probe replay passes on zenmemes.com, cost check recorded.

## Key Risks

- **High risk: counter adds latency on every request.** Early validation: Stage 1 measures it before anything depends on it. Mitigation: sample a subset of requests; if still measurable, stop and return to Step 2.
- **High risk: automation re-enables past a safety withdrawal.** Early validation: Stage 2 lists every writer of the mode state. Mitigation: any off state automation did not write is treated as an operator off.
- **Kept pages served after a withdrawing change.** Early validation: Stage 4 test that a withdrawal before enable clears every page. Mitigation: keep only pages rendered under a generation newer than the last withdrawal.
- **Access-log timestamps unreadable on the host.** Early validation: Stage 5 parses a sample of the zenmemes.com log the maintainer supplies. Mitigation: combined format only; anything else reported, mode left on.

## Stage 1

**Estimate: 0.10**

- Goal: count PHP requests per second cheaply, and prove the cost is negligible before anything uses it.
- Dependencies: none.
- Expected changes: a small `HighTrafficRate` class: `record(): void` appends one byte to a file named for the current second in the cache's private directory; `perSecond(int $window): float` sums recent buckets by size; old buckets pruned at most once per second. Called from `FrontController` beside the existing high-traffic checks, only while **Turn on automatically** is on (stubbed true for measurement).
- Verification approach: unit test of counts and pruning; microbenchmark of `record()` per call; local Apache harness (`tests/high_traffic_apache_test.py` setup) with `scripts/stress_probe.pl` in normal mode, three runs with and three without; results recorded in the Step 4 summary.
- Estimate details: driver: measurement setup; uncertainty: low.
- Risks or open questions:
  - Impact: a measurable cost blocks the trigger design.
  - Early warning / validation: p50/p95 outside run-to-run variation.
  - Mitigation: sample one request in N; otherwise return to Step 2.
- Canonical components/API contracts touched: `FrontController` request path (extend); `HighTrafficCache` root and `atomic` (reuse for paths only). New `HighTrafficRate` because nothing counts PHP requests today.

## Stage 2

**Estimate: 0.10**

- Goal: the mode state records who changed it, and holds the two automation settings.
- Dependencies: none (parallel with Stage 1).
- Expected changes: `HighTrafficMode::change(bool $enable, string $reason, string $source = 'operator')` with sources `operator`, `automatic`, `withdrawal`; `HighTrafficMode::setAutomation(?bool $turnOn, ?bool $turnOff, string $reason): array`; `HighTrafficSafety::beforeWrite` writes source `withdrawal`; an existing off state with no source reads as `operator`. CLI: `./v3 high-traffic auto-on on|off`, `auto-off on|off`; `status` shows both settings, source and reason.
- Verification approach: tests for each source, legacy state, and withdrawal marking; CLI smoke.
- Estimate details: driver: finding every state writer; uncertainty: low.
- Risks or open questions:
  - Impact: an unmarked withdrawal path would let automation re-enable.
  - Early warning / validation: grep of all `state.json` writers listed in the summary.
  - Mitigation: default unknown off states to `operator`.
- Canonical components/API contracts touched: `HighTrafficMode` (extend), `HighTrafficSafety` (extend), `scripts/high_traffic.php` (extend).

## Stage 3

**Estimate: 0.12**

- Goal: automatic turn-on from the request counter.
- Dependencies: Stages 1 and 2.
- Expected changes: at most once per second, the first request into a new bucket checks `perSecond(15)` against the threshold (`FORUM_HIGH_TRAFFIC_AUTO_ON_RATE`, default 3); if **Turn on automatically** is on, the mode is off, the source is not `withdrawal`, and no manual-off hold applies, it calls `change(true, 'Turned on automatically, N requests/s', 'automatic')`. A manual off sets a hold cleared once the rate falls below the threshold. The triggering request is served normally; errors are logged, never shown.
- Verification approach: tests for each precedence case (withdrawal, manual on, manual-off hold and its release); harness ramp turns the mode on within 20 seconds; check that the triggering request's response is not cached.
- Estimate details: driver: precedence rules; uncertainty: medium.
- Risks or open questions:
  - Impact: concurrent requests try to enable at once.
  - Early warning / validation: harness ramp with 8 readers.
  - Mitigation: `change` already serializes under the publication lock and is idempotent when on.
- Canonical components/API contracts touched: `HighTrafficMode::change` (reuse as the only switch), `FrontController` (extend), `HighTrafficCache::generate` and `high_traffic_render.php` (unchanged; still the only renderer).

## Stage 4

**Estimate: 0.08**

- Goal: turning on keeps still-fresh pages.
- Dependencies: Stage 2.
- Expected changes: on enable, `clearPages` keeps pages younger than `FORUM_HIGH_TRAFFIC_KEEP_SECONDS` (default 3600) and rendered under a generation newer than the latest withdrawal; others are cleared as today. The latest withdrawal's generation is kept in the mode state.
- Verification approach: tests for kept, aged-out and withdrawn pages; cron refreshes a kept popular page on its next run.
- Estimate details: driver: tying pages to generations; uncertainty: medium.
- Risks or open questions:
  - Impact: a kept page could show withdrawn content.
  - Early warning / validation: withdrawal-then-enable test.
  - Mitigation: anything without a provable generation is cleared.
- Canonical components/API contracts touched: `HighTrafficMode::change` and `clearPages` (extend); `v3-cache-generation` meta written by `HighTrafficCache::generate` (reuse).

## Stage 5

**Estimate: 0.10**

- Goal: automatic turn-off from the access log, and the health record that gates its switch.
- Dependencies: Stage 2.
- Expected changes: `HighTrafficRefresh::recentRate(string $log, int $seconds): ?float` reads combined-format timestamps from the bounded tail; the cron command, when **Turn off automatically** is on and the source is `automatic`, calls `change(false, 'Turned off automatically after 30 quiet minutes', 'automatic')` once the rate stays below `FORUM_HIGH_TRAFFIC_AUTO_OFF_RATE` (default 0.5) for `FORUM_HIGH_TRAFFIC_AUTO_OFF_SECONDS` (default 1800). Each cron run records its time and whether the log was readable; `setAutomation` refuses turn-off when the latest run is older than five minutes or unreadable, with a one-line reason. The cron command now runs these checks even while the mode is off.
- Verification approach: tests with synthetic logs (quiet, busy, unreadable, other format, manual-on episode); a sample of the zenmemes.com log parsed.
- Estimate details: driver: timestamp parsing over a backward read; uncertainty: medium.
- Risks or open questions:
  - Impact: wrong parsing turns the mode off during a spike.
  - Early warning / validation: busy-log test.
  - Mitigation: any parse failure means "not quiet".
- Canonical components/API contracts touched: `HighTrafficRefresh::popular` reader (extend), `run` and `scripts/high_traffic.php` cron path (extend).

## Stage 6

**Estimate: 0.10**

- Goal: two new switches on the operations page.
- Dependencies: Stages 2 and 5.
- Expected changes: **Turn on automatically** and **Turn off automatically** rows in the existing feature-flag row style beside the mode switch; changes go through the existing signed operator action (`LocalWriteService::changeHighTraffic`, extended with the two settings) and `high_traffic_control.js`. The mode row shows the reason line; the turn-off row shows its one-line reason when unavailable. No other copy.
- Verification approach: browser test extending `tests/browser/high_traffic_control.mjs`; operator vs non-operator views; first-paint check; visual check in Word97, Chicago, Midnight, Gloss and Engagify.
- Estimate details: driver: signed-action plumbing and theme checks; uncertainty: low.
- Risks or open questions:
  - Impact: three switches read as one confusing control.
  - Early warning / validation: screenshots in each theme reviewed with the maintainer.
  - Mitigation: one label per switch; the reason line is the only extra text.
- Canonical components/API contracts touched: `templates/pages/operations.php` rows, `high_traffic_control.js`, `LocalWriteService::changeHighTraffic`, `ToolsPageController` (all extend). No new navigation destination or icon.

## Stage 7

**Estimate: 0.08**

- Goal: documentation and the known-environment replay.
- Dependencies: Stages 1–6.
- Expected changes: runbook (switches, precedence, maintenance step, thresholds), CLI reference, static publication concept line, Step 4 summary.
- Verification approach: full ramp on the local Apache harness (on, kept pages, cron off, withdrawal stays off); after the maintainer deploys, run `scripts/stress_probe.pl` against zenmemes.com with both switches on and the mode off, expecting cache hits to climb from 0% to near 100% within the step that crosses the threshold, and `status` showing an automatic turn-on. Smoke tests plus changed tests; full suite not run. Final diff review.
- Estimate details: driver: depends on deployment timing; uncertainty: low.
- Risks or open questions:
  - Impact: production differs from the harness.
  - Early warning / validation: the replay itself.
  - Mitigation: record failures as blocked until replayed; manual switch remains available.
- Canonical components/API contracts touched: `docs/runbooks/high_traffic_mode.md`, `docs/reference/v3_cli.md`, `docs/concepts/static_publication.md` (extend).

## Study Checkpoint

2026-11-12T02:05:00Z — Step 3 handoff. Scope v3 from approved Step 2 Revision 1; starting revision `273bf93`; R1/P2, hashes not re-verified. Projected total **0.70**, revised from 0.65 for new understanding: verification needs harness ramps, a cost study and five-theme checks. Stage work: S1 0.10, S2 0.10, S3 0.12, S4 0.08, S5 0.10, S6 0.10, S7 0.08; independent annotations, not summed. Single exposed Claude rater. Not hours or measured labor; no approval to advance.

Step 2 approved on 2026-11-11 ("Approved step 2").

Status: Step 3 drafted; awaiting approval. Planning uncommitted.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*
