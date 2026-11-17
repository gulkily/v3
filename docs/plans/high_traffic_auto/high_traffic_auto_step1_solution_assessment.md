> **Feature plan:** [Step 1](./high_traffic_auto_step1_solution_assessment.md) · [Step 2](./high_traffic_auto_step2_feature_description.md) · [Step 3](./high_traffic_auto_step3_development_plan.md) · [Step 4](./high_traffic_auto_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/high_traffic_auto`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: [high-traffic mode](../high_traffic_mode/high_traffic_mode_step1_solution_assessment.md), which deferred this work.

Extended estimates: enabled

Cycle estimate: 0.60 (revised from 0.45; see [Study Checkpoint](#study-checkpoint))

## Original Query

Is it possible to automatically switch to high traffic mode if we detect conditions for it?

(Follow-up: "yes please", to sketching the controller against the actual code.)

## Understood Intent

Two stress probes against zenmemes.com prompted this. In normal mode, PHP saturated near 7–8 requests per second, with p95 over 2 seconds at 8 readers. With high-traffic mode on, every request was a cache hit and 8 readers got 29 requests per second at a 503 ms p95, still not the ceiling. The maintainer wants the site to turn the mode on by itself when traffic calls for it, instead of relying on someone to notice and run `./v3 high-traffic enable`.

The [high-traffic mode assessment](../high_traffic_mode/high_traffic_mode_step1_solution_assessment.md) deferred exactly this. It left `HighTrafficMode` as the one switch that automation must use, and required override precedence and anti-flapping rules before any automatic activation.

## Problem Statement

High-traffic mode only turns on when an operator runs it, so a traffic spike saturates PHP until someone notices.

## Concepts Touched

High-traffic mode (`HighTrafficMode`, `HighTrafficRefresh`, the per-minute cron), access-log sampling, operator controls on the operations page and CLI, safety withdrawals in `HighTrafficSafety`.

## Facts That Shape Every Option

- **Once the mode is on, PHP stops seeing most traffic.** Apache serves warm hits straight from `.high-traffic/`. Only the access log sees the whole picture, which is why the refresh job already reads it.
- **Before the mode is on, PHP sees everything.** So PHP can tell when to turn the mode on, but cannot tell when it is safe to turn it off.
- **Turning on clears the cache.** `enable` deletes old pages, so every page starts cold. Each miss waits at most 250 ms for one of two generator slots, then gets a 503 with `Retry-After: 5`. Switching on mid-spike means a burst of 503s until popular pages are rendered.
- **Some turn-offs are for safety.** Moderation flags, feature-flag and approval changes turn the mode off with "operator may re-enable after review". Deploys and imports must also disable it first. Automation must never undo either.
- **The cron line already runs every minute**, but `run` returns at once while the mode is off. Sites that never enabled the mode may not have it installed.

## Option A — Per-minute controller on the access log

**Estimate: 0.45**

Extend the existing per-minute cron job. Before refreshing, it reads the same bounded tail of the access log, counts requests per minute from the log's timestamps, and asks `HighTrafficMode` to enable when the rate stays above a threshold for consecutive minutes. Optionally it disables again after a long quiet period, but only for episodes it started itself. Before flipping the switch it renders the most popular routes so the switch does not land on an empty cache.

- Pros: one signal for both directions, including hits PHP never sees; no cost on the request path; reuses the bounded log reader, cron line, locks and switch; every decision is a line in the cron log and state file.
- Cons: reacts in one to two minutes, not seconds; timestamp parsing depends on the sysop's log format; the cron line must run on every public site, not only where the mode was enabled.
- Driver: precedence rules and warm-before-switch. Uncertainty: medium. Pre-rendering while disabled may need a change to `HighTrafficCache`, which currently refuses to generate when the mode is off.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Option B — Trigger inside the front controller

**Estimate: 0.55**

Each PHP request updates a small shared counter of recent requests and requests in flight. When either crosses a threshold, the request that notices it switches the mode on.

- Pros: reacts within seconds; measures PHP concurrency, the bottleneck the first probe showed.
- Cons: adds a locked write to every request, contended under exactly the load it is meant to detect; a web request would run the `.htaccess` writer and publication lock, with web-user permissions and added latency; cannot decide when to turn off, since hits bypass PHP.
- Driver: hot-path shared state under load. Uncertainty: high.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Option C — Leave the mode on permanently

**Estimate: 0.10**

No detection. Keep zenmemes.com in high-traffic mode all the time and document that as the recommended setting for public sites.

- Pros: no new moving parts; no cold-switch burst during a spike; post refresh already updates a thread page immediately after a reply.
- Cons: `/` and `/threads/` stay up to about a minute or two behind at all times; safety withdrawals still turn it off, so a quiet re-enable after review is still manual; it does not answer the request for sites that should normally run live.
- Driver: runbook and default change only. Uncertainty: low.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Option D — Request counter turns on, log controller turns off (A + B)

**Estimate: 0.60**

Each side uses the signal it can see. While the mode is off, every request goes through PHP, so a cheap per-request counter turns it on within seconds. Once it is on, Apache serves most hits without PHP, so the per-minute cron job reads the access log and turns it off after a long quiet period.

- Pros: fast to turn on, where seconds matter; accurate when turning off, where minutes are fine; neither trigger needs a signal it cannot see.
- Cons: two triggers to keep consistent with one set of precedence rules; reacting in seconds leaves no time to pre-render before switching, so cold misses need a different answer.
- Driver: a per-request cost that must be shown to be negligible, plus shared precedence rules. Uncertainty: medium.

Option B's cons overstated two points. The operations page already enables the mode from a web request, so the web user has the needed permissions, and only the one request that triggers the switch pays its latency. A locked shared counter is not required either: appending one byte to a file named for the current second is atomic on local disks without a lock, and the file size is the count.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Recommendation

**Option D** (revised; Option A alone was the original recommendation). The maintainer chose it because cached pages lack some per-user features and UI changes, which also rules out Option C: the mode should be on only while traffic needs it, and should end promptly afterwards.

Proposed rules for Step 2 to settle:

- **Turning on (request counter):** each PHP request adds one byte to a per-second file outside the public root. At most once per second, the first request into a new second sums the last 10–20 seconds with `stat` calls and, above the threshold, enables the mode through `HighTrafficMode` with the measured rate as the reason. No shared lock and no third-party extension.
- **Cost requirement:** the counter must add no measurable latency. Step 3 verifies this with the stress probe before and after, in normal mode, and records the numbers; if it does not hold, the counter does not ship.
- **Turning off (log controller):** the per-minute cron job reads the access-log tail it already reads and disables only after a much lower rate for about 30 minutes. It ends only episodes that automation started.
- **Precedence, highest first:** safety withdrawal (stays off until an operator re-enables) › operator pin (on or off, until cleared) › automation. Neither trigger acts after a safety withdrawal or during maintenance.
- **Anti-flapping, one way only:** turning off waits for about 30 quiet minutes; turning on has no cooldown, so a renewed spike re-enables at once. The slow turn-off alone limits the mode to at most one off-and-on cycle per quiet period.
- **Cold misses:** enabling keeps cached pages that are still fresh instead of clearing them all, so a quick re-enable starts warm. A kept page must also predate no safety withdrawal or other withdrawing change; anything else is cleared as today. Step 2 sets the age limit for "still fresh".
- **Starting thresholds** from the probes: normal mode saturated near 7 requests per second, so turning on around 3 per second leaves headroom. Configurable like the other `FORUM_HIGH_TRAFFIC_*` limits; starting values, not capacity claims.
- **Visibility:** every automatic change records its trigger, rate and reason in the mode state, shown on the operations page and in `./v3 high-traffic status`.

**Vertical-slice viability:** on a test Apache instance, a stress-probe ramp turns the mode on within seconds of crossing the threshold, with no measurable added latency below it; a quiet period in the access log turns it off; a moderation flag turns it off and neither trigger turns it back on.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Open Questions

- Answered: automation turns the mode off too, through the log controller.
- Answered: Option C is not enough for zenmemes.com, since cached pages lack some per-user features and UI changes.
- Open: pages are refreshed only while the mode is on, and the current freshness window is 60 seconds. After a 30-minute quiet period few pages would still count as fresh, so keeping them mainly helps re-enables that follow soon after a turn-off. Step 2 should decide whether that is enough, or whether a longer limit for kept pages is acceptable, with cron refreshing them within a minute of the switch.

## Design Refinement

- **Request:** use Option B for turning on, if it adds no performance hit, and Option A for turning off. **Disposition:** added as Option D and made the recommendation; the no-measurable-cost condition became a requirement verified with the stress probe. **Approval status:** Step 1 revision awaiting review; no approval to advance. **Outcome:** supersedes the original Option A recommendation; options A–C kept for comparison.
- **Request:** for cold misses, keep still-fresh pages on enable. **Disposition:** adopted in the Option D rules; the alternative of a lower turn-on threshold is dropped. **Approval status:** Step 1 revision awaiting review. **Outcome:** limitation of the 60-second freshness window recorded under Open Questions.
- **Request:** make anti-flapping one way only, with no cooldown before turning back on. **Disposition:** adopted; only the slow turn-off remains. **Approval status:** Step 1 revision awaiting review. **Outcome:** a renewed spike right after a turn-off re-enables immediately.

## Enhancement Requests

None.

## Study Checkpoint

2026-11-11T22:40:58Z; scope v1; starting revision `273bf93`; reference R1, hashes not re-verified. Claude, single exposed rater; the high-traffic mode, safety, refresh and cache code and the parent plans were read before rating, so no pristine intake is claimed.

- Intake after inspection and Step 1 handoff: **0.45** projected total for Option A with automatic turn-off.
- Alternatives: B 0.55, C 0.10, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

**Revision 1** — 2026-11-11T23:34:00Z; scope v2 (changed scope, not new understanding of v1): the maintainer chose Option D, request-counter turn-on plus log-controller turn-off, with a cost check. Original v1 values above are preserved.

- Revised Step 1 handoff: **0.60** projected total for Option D. Driver: two triggers sharing precedence rules, plus per-request cost verification. Options A 0.45, B 0.55 and C 0.10 reaffirmed unchanged; non-additive. Same reference, rater and exposure.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
