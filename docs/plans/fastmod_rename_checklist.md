# Fastmod Rename Checklist

## Naming rule

Use **Fastmod** as the public product name. Keep stable internal identifiers
unchanged: `fast-score` commands, `FAST_SCORING_*` configuration,
`fast_score_sweep` task type, database filenames, source namespaces, and
storage tables.

## Public product copy

- [x] Show `Fastmod` beside the fractional score on `/posts/{id}`.
- [x] Rename the README feature link and description to Fastmod.
- [x] Rename the Fast Post Scoring reference document's title and prose to
  Fastmod, preserving literal command and configuration names.
- [x] Update the active productization plan and `todo.txt` to call the feature
  Fastmod.

## Operator-facing copy

- [x] Change `Fast-score status` and prune output to `Fastmod` in
  `scripts/fast_score.php`.
- [x] Change task-queue progress output from `Fast-score` to `Fastmod` in
  `scripts/task_queue.php`.
- [x] Change the retired score API's explanatory message to refer to Fastmod.
- [x] Update Fastmod prose in the CLI reference and operator/deployment
  runbooks, preserving literal command and configuration names.

## Verification

- [x] Search public-facing templates, documentation, scripts, and API messages
  for obsolete product-copy references.
- [ ] Run the relevant CLI, smoke, and documentation checks.
