# Fastmod Status Clarity Checklist

Improve `./v3 fast-score status` so an operator can immediately see what needs
work, whether an explicitly requested backfill is progressing, and what to run
next. Preserve the current priority rule: regular Fastmod work runs before
historical backfill work.

- [ ] Replace overlapping work/score count lines with separate outstanding-work
  and retained-outcome summaries.
- [ ] Show regular and backfill pending work separately, including each active
  batch's processed/remaining progress and reserved estimate versus cap.
- [ ] Print a contextual next action, including an explicit explanation when a
  queued backfill is waiting behind regular work.
- [ ] Hide repetitive per-post records by default; provide `--verbose` to show
  them with shortened hashes and existing failure details.
- [ ] Update Fastmod reference documentation and focused command/store tests.
