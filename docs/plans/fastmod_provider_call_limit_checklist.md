# Fastmod Provider-Call Limit Checklist

Make provider requests, rather than all processed work rows, the primary
Fastmod worker limit. Keep a separate 250-row local-work safety cap.

- [x] Add provider-call preflight and safely release a claimed row when the
  provider-call cap is reached before a request begins.
- [x] Reserve historical-backfill budget only immediately before an actual
  provider request, not for local exclusions.
- [x] Apply `--score-limit` as the 25-call default and add `--work-limit=250`
  as the examined-row cap; report both limits and outcomes.
- [x] Update tests and operator documentation for the two-limit contract.
