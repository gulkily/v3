> **Feature plan:** [Step 1](./work_tracking_step1_solution_assessment.md) · [Step 2](./work_tracking_step2_feature_description.md) · [Step 3](./work_tracking_step3_development_plan.md) · [Step 4](./work_tracking_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/work_tracking`; [host ledger](../effort_estimation_trial.md#cycle-v3work_tracking). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 2 approved; Step 3 proposed; implementation unapproved.

**Cycle estimate: 1.00** (revised; Step 2 0.95)

## Completion Contract

- Normal entry: an agent runs `./v3 work search <words>` before a fix, then `./v3 work claim <item>`; people browse `/tags/work` or the item thread.
- End-to-end outcome: a second session searching the same issue sees `active` and the claimant; a landed commit carrying `Work: <thread-id>` sets the item `done` on its thread, `/work.txt` and the next export.
- Required recovery: when zenmemes.com is unreachable, `claim` saves the claim locally and says so; `./v3 work flush` posts it later. Re-running sweeps or post-back creates no duplicates.
- Deployment/external verification: deployed to zenmemes.com by the usual SSH pull; the ten-item pilot runs on production and is recorded as done or explicitly not done.
- Release condition: pilot success criteria from Step 2 met on production.

## Key Risks

- **High risk:** status must be single-valued, but agent-applied labels only support `add`; label removal is operator-local. Mitigation: represent status as a `status:` reply directive with latest-valid-wins, following the existing `title:` directive. Validated in Stage 1 before anything depends on it.
- Export cannot be pushed from the server's separate content repository. Mitigation: the server only publishes `/work.md`; `./v3 work export` in any checkout fetches it and commits to a dedicated `work-export` branch. No CI added (that would need dependency approval).
- Agents skip the check. Mitigation: `AGENTS.md` rule plus `Work:` commit trailer; pilot counts misses.
- Claim latency. Validate one-request publish timing in Stage 3; local fallback covers outages.

## Stage 1

**Estimate: 0.15**

- Goal: a `#work` thread shows one current status set by `status:` replies.
- Dependencies: none.
- Expected changes: parse `status: open|active|done|dropped` (optional note) beside the existing `title:` directive; derive current status, claimant and last activity in the read model; show status on the thread. Approved members may set status.
- Verification approach: fixtures for latest-wins, invalid values, unauthorized authors, retracted replies; rebuild produces the same result; thread view on phone and desktop.
- Estimate details: driver: read-model derivation and rebuild parity; uncertainty: directive authorization reuse.
- Risks or open questions:
  - Impact: wrong status misleads every later stage.
  - Early warning / validation: rebuild-parity test first.
  - Mitigation: derive only, never store separately from replies.
- Canonical components/API contracts touched: reply title directive parsing, thread read model, `templates/pages/thread.php`.

## Stage 2

**Estimate: 0.12**

- Goal: one unsigned request answers "is anyone on this?"
- Dependencies: Stage 1.
- Expected changes: `/work.txt?q=&status=` returning one line per item (status, stale flag, claimant, title, link), reusing the existing search path; staleness after a configurable period (default 7 days).
- Verification approach: HTTP tests for filters, empty results, stale flag, Unicode titles; latency check on production-sized data.
- Estimate details: driver: reuse of search; uncertainty: search ranking on short titles.
- Risks or open questions: none beyond Stage 1.
- Canonical components/API contracts touched: `/search` controller, read routes beside `/llms.txt`.

## Stage 3

**Estimate: 0.15**

- Goal: agents search, claim and change status from the CLI, with offline fallback.
- Dependencies: Stage 2.
- Expected changes: `./v3 work search|claim|status|flush`, using the one-request signed publish; unreachable-site claims queue in an ignored local file; `flush` posts them in order.
- Verification approach: timed claim against a local server; simulated outage then flush; double-flush idempotence.
- Estimate details: driver: signing reuse and outage handling; uncertainty: identity setup in fresh workspaces.
- Risks or open questions:
  - Impact: slow or failing claims push agents to skip them.
  - Early warning / validation: timing measured before Stage 4.
  - Mitigation: fallback queue; clear CLI messages.
- Canonical components/API contracts touched: `frm.py` one-request publish, `./v3` CLI.

## Stage 4

**Estimate: 0.06**

- Goal: agents follow the rule.
- Dependencies: Stage 3.
- Expected changes: `AGENTS.md` search-and-claim rule and `Work:` commit trailer; `docs/reference/work_tracking.md`; CLI reference entries.
- Verification approach: documentation review; a fresh session follows the rule unprompted in the pilot.
- Estimate details: driver: wording; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `AGENTS.md`, `docs/reference/v3_cli.md`.

## Stage 5

**Estimate: 0.12**

- Goal: status is greppable in the repository.
- Dependencies: Stage 2.
- Expected changes: server publishes `/work.md` (generated header, one line per item, stable thread-ID order); `./v3 work export` fetches it and commits to `work-export` only when content changed.
- Verification approach: unchanged content creates no commit; diff shows one-line status changes; run from a cloud workspace.
- Estimate details: driver: branch handling in arbitrary checkouts; uncertainty: push permissions per environment.
- Risks or open questions: see Key Risks (export path).
- Canonical components/API contracts touched: static/text read routes, `./v3` CLI.

## Stage 6

**Estimate: 0.15**

- Goal: inbox entries become items without duplicates.
- Dependencies: Stage 3.
- Expected changes: `./v3 work sweep` creates `open` items from new `todo.txt` lines and `#request` threads; each item records a source fingerprint, and the sweep skips fingerprints already present on the site, so no local imported-record file is needed and `todo.txt` is never edited.
- Verification approach: repeated sweeps create nothing new; edited lines create one new item; blank and indented lines handled.
- Estimate details: driver: `todo.txt` line grouping; uncertainty: indented sub-items.
- Risks or open questions:
  - Impact: noisy items erode trust.
  - Early warning / validation: dry-run output reviewed by the maintainer before first real sweep.
  - Mitigation: `--dry-run` default for the first run.
- Canonical components/API contracts touched: Stage 3 CLI, thread creation API.

## Stage 7

**Estimate: 0.12**

- Goal: landed work updates its thread automatically and in batches.
- Dependencies: Stages 1 and 3.
- Expected changes: after an application update, the server posts `status: done` with the commit link for new `Work:` trailers; `./v3 work sweep` repeats the check for anything missed; both skip items already `done`.
- Verification approach: simulated update with trailers; rerun is a no-op; batch catches a trailer the update missed.
- Estimate details: driver: hook placement in application update; uncertainty: server agent identity.
- Risks or open questions: server-side posting identity needs operator approval.
- Canonical components/API contracts touched: application update task, internal task queue.

## Stage 8

**Estimate: 0.10**

- Goal: production pilot and outage replay.
- Dependencies: Stages 1–7.
- Expected changes: deploy; create ten pilot items (including the 2026-11-03 double-fixed issue); two-session claim test; outage replay with `flush`; export run; add the *work item status* concept entry.
- Verification approach: Step 2 success criteria checked on zenmemes.com; results recorded honestly.
- Estimate details: driver: coordination; uncertainty: production deploy timing.
- Risks or open questions: none beyond earlier stages.
- Canonical components/API contracts touched: concept inventory.

## Concepts Touched

[Tag browsing](../../concepts/tag_browsing.md) used unchanged (`/tags/work`); [thread title](../../concepts/thread_title.md) directive parsing reused; [application update](../../concepts/application_update.md) changed (Stage 7); proposed *work item status* created in Stage 8.

## Study Checkpoint

2026-11-04T00:49:31Z; scope v2/A, original baseline `561f7dd1113c3021ba0d7d1b28361865c40de2e2`, R1/P2, hashes verified earlier this session. User explicitly approved Step 2. Whole-cycle projection revised **0.95 → 1.00**: label removal is operator-only, so status needs a reply directive, and automatic post-back needs an application-update hook. Scope unchanged. Eight stage-work originals 0.15/0.12/0.15/0.06/0.12/0.15/0.12/0.10, non-additive. Claude, single exposed rater. No implementation, measured labor or accepted outcome. Await Approved Step 3; planning uncommitted.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
