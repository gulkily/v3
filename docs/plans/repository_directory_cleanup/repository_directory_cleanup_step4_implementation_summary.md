> **Feature plan:** [Step 1](./repository_directory_cleanup_step1_solution_assessment.md) · [Step 2](./repository_directory_cleanup_step2_feature_description.md) · [Step 3](./repository_directory_cleanup_step3_development_plan.md) · [Step 4](./repository_directory_cleanup_step4_implementation_summary.md)

Debug mode: enabled

## Approved scope

- User retained `todo.txt` at the root when approving Step 3: eight moves total, six from the root.
- Documentation-only; runtime, UI, deployment, migration, and release checks are not applicable because application code and configuration do not change.

## Stage 1 - Root documentation cleanup

- Changes: Moved six root documents into backlog, product, and specs categories; repaired five live links; added backlog discovery in the root README and four note links in the backlog index. Removed the obsolete “repo root” label from the README heading.
- Verification: `python3 /tmp/verify-repository-cleanup.py` passed six byte-identical moves, unchanged root `todo.txt`, six fewer root files, 161 resolving local Markdown links across edited documents and plan navigation, and approved-file-scope checks. `git diff --check` passed.
- Notes: Repository-root code paths in proposal prose remain repository-root references. Existing historical bare filenames, quoted requests, parser examples, and already-stale plan references were preserved. No runtime/API/catalog changes; runtime, UI, deployment, migration, and release checks are not applicable.
