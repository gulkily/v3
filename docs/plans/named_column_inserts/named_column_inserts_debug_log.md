# named_column_inserts Railspike debug log

## 2026-11-21 UTC — one-shot closeout

### Necessary/useful instructions

- `one_shot.md`, planning commit gate: the audit was written down before any edit, which caught the read-model fingerprint problem before it forced a rebuild. Retain.
- `implementation_standards.md`, verification: the probe-file check found that the new test was not running at all. Retain.
- Host `CONTRIBUTING.md`, read-model schema changes: explained why four statements had to stay as they are. Retain.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- Concept inventory updates: no concept touched.

### Uncertain assessments

- Host gap: nothing says that `tests/run.php` lists test files by name. A new test file is silently skipped, and `./v3 test --smoke NewTest` still reports success.
