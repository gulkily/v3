# Errors with expandable details — one-shot

Cycle: `v3/error_details_guideline`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](error_details_guideline_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/error_details_guideline`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/error_details_guideline.md))
Extended estimates: enabled (no alternatives or work units, so only the cycle estimate applies)

**Cycle estimate: 0.05**

Status: plan written; next permitted action: edit the guideline documents listed below.

## Request and authorization

The maintainer, 2026-11-27 14:29 ET, in a Claude session, after Turn on for notifications stuck on "Working…" on a friend's iPhone with nothing to say why: "we have seen this pattern several times now, where the diagnostics are inadequate, and the error messages are opaque. we need to report useful error messages to users that are not too complex, but we need to be able to expand them to be able to diagnose. let's update the project guidelines so that we do better from now on."

Authorizes a documentation-only one-shot through an open PR. Excludes: application code (the push Turn on fix is its own cycle, `v3/push_turn_on_diagnostics`), sweeping existing messages, merge and deployment.

Feedback item: none matched in `docs/feedback/items/` (searched "error message", "diagnos", "opaque", "Try again").

## Outcome and work type

Documentation only. Authorized paths: `PRINCIPLES.md`, `CONTRIBUTING.md`, `AGENTS.md`, `docs/reference/progressive_disclosure.md`, this record, its debug log, the plans index and the cycle's ledger file.

Reader outcome: a contributor or agent building any flow that can fail knows that (1) the person sees a short plain message with a next step, (2) a collapsed Details disclosure holds what is needed to diagnose it (step, error, status, environment), copyable, (3) nothing waits forever: every step that can stall has a timeout that names the step, (4) a flow that mostly fails on other people's devices also sends those details to the server, and (5) failure states, details included, are part of verification.

## Approach and risks

- Principle in `PRINCIPLES.md` (Interface) plus a summary bullet; the concrete rules in a new `CONTRIBUTING.md` section; a short pointer in `AGENTS.md`; one link from the progressive disclosure guide, which already allows expandable failure evidence.
- Risk: the rule conflicts with "Remove text before rewriting it". Resolved by keeping the visible message short and putting evidence in a closed disclosure, which the disclosure guide already sanctions.
- Risk: details leak secrets. The rule lists what never goes in them.
- Risk: a reporting rule invents infrastructure. It names the need and leaves the mechanism to each cycle.

## Reuse decisions

- `docs/reference/progressive_disclosure.md`: reused for how the details are shown (native `details`/`summary`, closed, labelled); not duplicated.
- `PRINCIPLES.md` "Never lose someone's work silently" and "State limits plainly": left as they are; the new principle sits beside them.
- `CONTRIBUTING.md` "UI copy and verification": its failure-state check is extended by reference, not rewritten.

## Concepts touched

None.

## Completion conditions

The five reader outcomes above are stated in the documents, linked from `AGENTS.md`, and consistent with the UI copy and progressive disclosure rules. Documentation-only: runtime checks inapplicable.

## Verification and recovery plan

Read the edited sections together for conflicts with UI copy rules; check links resolve; `git diff --stat` stays inside the authorized paths. No tests (no code).

Starting revision `f5b6f1099` on `main`; branch `docs/error-details-guideline`.
Planning commit: pending.

## Execution history

- 2026-11-27: plan written.

## Study history

- 2026-11-27 intake **0.05**, single Claude rater, no prior exposure to an estimate for this work. Execution entry: reaffirmed 0.05.

*— Claude · 2026-11-27 UTC*
