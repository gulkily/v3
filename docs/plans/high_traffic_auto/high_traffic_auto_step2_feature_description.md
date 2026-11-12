> **Feature plan:** [Step 1](./high_traffic_auto_step1_solution_assessment.md) · [Step 2](./high_traffic_auto_step2_feature_description.md) · [Step 3](./high_traffic_auto_step3_development_plan.md) · [Step 4](./high_traffic_auto_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/high_traffic_auto`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: [high-traffic mode](../high_traffic_mode/high_traffic_mode_step1_solution_assessment.md).

Extended estimates: enabled

**Cycle estimate: 0.65**

**Concepts touched:** [static publication](../../concepts/static_publication.md), changed: its high-traffic note gains one line saying the mode can turn itself on under load and off after a quiet period. No new concept.

## Problem

High-traffic mode turns on only when an operator notices a spike. Until then PHP saturates near 7–8 requests per second and pages take seconds. Once on, the mode stays on until someone turns it off, and cached pages lack some per-user features and UI changes the whole time.

## User stories

- As a reader during a spike, I want pages to stay fast without anyone stepping in, so that a link going around does not make the site unusable.
- As a member, I want the site back to live pages soon after the spike, so that per-user features return.
- As the operator, I want to choose separately whether the mode turns on by itself and whether it turns off by itself, so that I can leave it on after a spike when I prefer.
- As the operator, I want my own on/off choice and every safety withdrawal to win over automation, so that it never undoes a decision I or the site made deliberately.
- As the operator, I want to see why the mode is on or off, so that I can tell automation from my own actions.

## Core requirements

- **Three separate controls, no combined control.** The existing high-traffic switch on the operations page stays as it is. Two new switches sit beside it in the same style: **Turn on automatically** and **Turn off automatically**. The CLI gets matching commands. Both new switches start off, so a site where nobody changes them behaves exactly as today. No deployment setting.
- **Turn on automatically** (works on any site): while the mode is off, PHP counts its requests per second with no shared lock and no new extension. When the rate over the last 10–20 seconds passes a threshold, the request that notices turns the mode on through `HighTrafficMode`, recording the trigger and rate as the reason.
- **Turn off automatically** (needs cron and the access log): the per-minute cron job turns the mode off after about 30 minutes below a much lower rate, only for episodes automation started. No cooldown before turning back on. The switch can be turned on only when cron has run recently with a readable access log; otherwise it stays off with a one-line reason.
- **Turning on without turning off** is supported: with only the first switch on, a spike turns the mode on and it stays on until the operator turns it off. The reason line says so, for example "Turned on automatically at 18:42, 4.2 requests/s".
- **Precedence, highest first:** safety withdrawal, then the operator's own use of the mode switch, then automation. A safety withdrawal keeps automation out until an operator turns the mode on by hand. A manual on is never turned off automatically. A manual off holds against automatic turn-on until the request rate falls back below the threshold, so the next spike can turn it on again. Before maintenance, the runbook says to turn off **Turn on automatically** as well as the mode.
- **Keep still-fresh pages.** Turning on keeps cached pages younger than a kept-page age limit, and clears any page from before the latest safety withdrawal or other withdrawing change. Proposed default: 60 minutes, configurable. Cron refreshes kept popular pages within a minute.
- **Isolated rendering.** Automatic turn-on never renders from the triggering request; every cached page still comes from the isolated worker with no visitor state.
- **No measurable cost.** In normal mode, the counter adds no latency distinguishable from run-to-run variation in the stress probe. If it does, it does not ship.

## Delivery scope

- Work type: application change.
- Expected areas: the front controller's request path (counter and trigger), `HighTrafficMode` (who changed the mode and why; the two automation settings; keeping fresh pages on enable), `HighTrafficRefresh` and the cron command (rate from log timestamps; turn-off; recording that cron ran with a readable log), `HighTrafficSafety` (withdrawals marked as such), the operations page (two new switches beside the existing one) and `./v3 high-traffic` CLI, tests, the static publication concept, the high-traffic runbook and CLI reference, and this cycle's plans and ledger entries.
- Each site opts in from the operations page or CLI; both automation switches start off. Thresholds and limits stay overridable like the other `FORUM_HIGH_TRAFFIC_*` values, as tuning rather than a switch. Proposed starting values: turn on above 3 requests per second over 15 seconds; turn off below 0.5 per second for 30 minutes.
- No third-party components; no read-model schema change.

## Completion boundary

- Normal entry: the operator turns on **Turn on automatically** (and, where cron is installed, **Turn off automatically**) on the operations page; later, with the mode off, traffic rises past the threshold.
- End-to-end outcome: the mode turns on within seconds; later readers get cache hits; the reason line shows the automatic turn-on and measured rate. With turn-off enabled, after 30 quiet minutes cron turns it off and the reason line says so; without it, the mode stays on.
- Recovery: the operator uses the existing mode switch from the operations page or CLI, or turns either automation switch off, at any time. If the access log cannot be read or its timestamps parsed, automation does not turn the mode off and status reports why; the mode stays on, which is the safe side. If the trigger fails, the request is still served and the error is logged.
- Release condition: merged, deployed and checked on zenmemes.com with the cron line installed.

## Known user or environment

The maintainer, on zenmemes.com. Entry: turn on both automation switches on the operations page, then run `scripts/stress_probe.pl` against the site with the mode off. Evidence: the probe's cache hits climb from 0% to near 100% within the first step that crosses the threshold, and `./v3 high-traffic status` shows an automatic turn-on with its rate. A second probe run, in normal mode before automation is enabled, gives the before-and-after comparison for the cost requirement. If the mode does not turn on, the maintainer can turn it on by hand as today and read the logged reason.

## Risks

- **Counter cost on every request.** Earliest check: measure the counter alone, then the stress probe in normal mode with and without it, before building anything else. Mitigation: sample a subset of requests if a write per request proves measurable.
- **Re-enabling past a withdrawal.** A withdrawn state written by older code is not marked as a withdrawal. Earliest check: list every path that writes the mode state. Mitigation: treat any off state automation did not write itself as an operator off.
- **Kept pages served stale or withdrawn.** Earliest check: confirm withdrawing changes always bump the generation and that kept pages can be tied to the generation they were rendered under. Mitigation: keep only pages from the current generation since the last withdrawal; clear the rest as today.
- **Three switches read as one confusing control.** Earliest check: a draft of the operations row in every theme. Mitigation: reuse the existing feature-flag row style, one label per switch, and the reason line as the only extra text.
- **Access-log format.** The turn-off needs timestamps the current parser ignores. Earliest check: read the zenmemes.com log format with the sysop. Mitigation: support the standard combined format and report anything else as unreadable.

## Shared component inventory

- `HighTrafficMode::change`: reuse as the only switch for both triggers; extend its state with who made the change (automatic, operator, withdrawal) and keep-fresh behavior on enable.
- `HighTrafficSafety::beforeWrite`: reuse; its withdrawals become marked as withdrawals.
- `HighTrafficRefresh::popular` and `run`: reuse the bounded log reader; extend it to read timestamps for a rate; the turn-off runs in the existing cron command.
- `FrontController`: extend its request path with the counter, beside the existing high-traffic checks.
- Operations page (`templates/pages/operations.php` feature-flag rows, `high_traffic_control.js`, `LocalWriteService::changeHighTraffic` with its signed operator action) and `scripts/high_traffic.php`: reuse the existing switch unchanged; add the two automation switches with the same row style and the same signed-action path. Keep UI copy to switch labels, states and the reason line.
- `HighTrafficCache::generate` and `scripts/high_traffic_render.php`: unchanged; they remain the only way pages are rendered.

Nothing new duplicates an existing component; the counter is the only new piece, because nothing counts PHP requests today.

## User flow

1. The operator turns on **Turn on automatically**, and **Turn off automatically** if cron is installed. The mode is off and pages render live.
2. A link spreads; PHP requests pass 3 per second over 15 seconds.
3. The request that notices turns the mode on, keeping still-fresh pages; the reason line shows "Turned on automatically, 4.2 requests/s".
4. Readers get cached pages; cron keeps popular ones fresh.
5. Traffic falls. With **Turn off automatically** on, cron turns the mode off after 30 quiet minutes; otherwise it stays on until the operator turns it off.
6. At any point, the operator can use the mode switch or either automation switch.

## Success criteria

- A stress-probe ramp turns the mode on within 20 seconds of crossing the threshold, and cache hits reach near 100% in that step.
- Below the threshold, the normal-mode probe's p50 and p95 with the counter are within run-to-run variation of the runs without it.
- After a simulated 30-minute quiet log, cron turns the mode off when **Turn off automatically** is on, and leaves it on when that switch is off; an operator-chosen on is never turned off.
- **Turn off automatically** cannot be switched on where cron has not run with a readable log, and says why.
- After a moderation flag withdraws the mode, a further probe ramp does not turn it back on.
- No cached page contains operator or member UI rendered from the triggering request.
- With both automation switches off, behavior is exactly as before.

## Enhancement Requests

- **Request:** put automation on the operations page rather than in a deployment setting; allow turning on without turning off; use separate controls, not a four-way control. **Disposition:** in-scope revision of this step: the existing switch stays and two separate switches are added; the deployment setting is dropped. **Approval status:** Step 2 revision awaiting review. **Outcome:** requirements, scope, flow and criteria above.

## Study Snapshots

2026-11-12T01:07:41Z — Step 2 handoff. Scope v2 from Step 1 Revision 1 (Option D with one-way anti-flapping and keep-still-fresh pages); starting revision `273bf93`; R1/P2, hashes not re-verified. Projected total **0.65**, revised from 0.60 for new understanding, not new scope: handing control back to automatic needs an operations-page and CLI change, and the turn-off needs timestamp parsing the log reader lacks. Single exposed Claude rater. Not hours or measured labor; no approval to advance.

**Revision 1** — 2026-11-12T01:59:00Z. Scope v3: separate on-page switches for turning on and off automatically, turn-on without turn-off, no deployment setting. Projected total **0.65** reaffirmed: two switches reusing the existing row and signed-action path cost about what the deployment setting plus "back to automatic" would have. Same reference, rater and exposure. v2 values preserved above.

Step 1 approved on 2026-11-11 ("approved step 1").

Status: Step 2 drafted; awaiting approval. Planning uncommitted.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*
