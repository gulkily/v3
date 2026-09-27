# Fastmod Audit and Backfill Step 1 Solution Assessment

## Problem

Operators need to measure unscored historical content and its estimated Fastmod
cost before deliberately deciding whether to backfill it.

## Option A — Separate audit and backfill commands

- Add a read-only audit command that reports historical candidate counts,
  existing states, and a cost range based on the configured model and observed
  Fastmod usage.
- Add a separate, explicitly confirmed, bounded backfill command.
- Pros: clear no-write preview; deliberate policy reversal; supports spend and
  volume limits.
- Cons: two commands and associated operator documentation.

## Option B — Extend the existing status and enqueue commands

- Add historical auditing to status and an opt-in historical mode to the
  existing enqueue command.
- Pros: fewer commands; familiar workflow.
- Cons: blurs normal new-content processing with an exceptional corpus-wide
  action; makes accidental backfill easier.

## Option C — Document manual SQLite queries and one-off queue writes

- Keep the product unchanged and provide operator instructions only.
- Pros: no feature work.
- Cons: error-prone; no standardized estimate, guardrails, or audit trail.

## Recommendation

Choose **Option A**. A read-only audit followed by a separately confirmed,
bounded backfill preserves the current new-content-only default while making a
historical run measurable, intentional, and recoverable.

## Next

If this assessment is accepted, reply **Approved Step 1**. I will then create
the Step 2 feature description.
