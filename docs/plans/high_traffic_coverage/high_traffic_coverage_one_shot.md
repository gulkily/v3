Cycle: `v3/high_traffic_coverage`; Mode: one-shot; Record/index: this file · [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/high_traffic_coverage`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: disabled (one-shot with no alternatives or work units)

**Cycle estimate: 0.08**

Status: planning committed; next permitted action: write the checklist.

## Original request

2026-11-17 00:21 ET, maintainer: "please make a checklist files of all the things we need to add support for in htm"

## Understood intent

"htm" is high-traffic mode. This follows from the previous answer, which listed what still reaches PHP while the mode is on, after [asset_bypass](../asset_bypass/asset_bypass_step4_implementation_summary.md) (PR #293, merged).

## Authorization

The request authorizes writing one checklist file. That is bounded documentation work, so one-shot mode fits. It does not authorize implementing any item, pushing or opening a PR.

## Outcome and work type

Documentation-only. Allowed files:
- `docs/plans/high_traffic_coverage/high_traffic_coverage_checklist.md` (new)
- this record
- `docs/plans/README.md` (index entry)
- the effort study ledger
- this cycle's debug log

No application, test or configuration changes.

Reader outcome: the maintainer can pick the next high-traffic coverage cycle from one list. Each item says what reaches PHP today, why it matters, and what is known or unknown.

## Approach and risks

- Approach: derive the items from the code on `origin/main` (`f83c0b0d`), not from memory. Sources: `HighTrafficRoute`, `public/.htaccess`, `HighTrafficCache`, the Application route table and the page scripts' requests.
- Risk: an item might be stated as fact when it is only an inference. Mitigation: label unmeasured items "unknown" and make measurement the first item.
- Risk: a list of every route is noise. Mitigation: group items by priority, and list the intentionally dynamic pages once, as out of scope.

## Reuse decisions

- Format: Markdown checklist under `docs/plans/`, like `docs/plans/ui_copy_audit_checklist.md`. It is a planning document, not a canonical record, so the `Key: value` format does not apply.
- Facts come from the [runbook](../../runbooks/high_traffic_mode.md) and the [concept entry](../../concepts/high_traffic_mode.md), without duplicating their procedures.

## Completion conditions

The checklist exists and every item names its route or file and its current behavior. Paths and links resolve. Its handoff is the maintainer choosing an item, which then becomes its own cycle.

## Concepts touched

[High-traffic mode](../../concepts/high_traffic_mode.md), used unchanged. No inventory edits.

## Verification plan

- Check each claim against the source paths above.
- Check that the links resolve.
- Run `git diff --check`.
- Confirm only the allowed files changed.
- Runtime, UI and deployment checks do not apply.

Starting revision and branch: `origin/main` `f83c0b0d`; branch `docs/htm_coverage_checklist`.

Planning commit: pending.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*
