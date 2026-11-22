# test_runner_unlisted_tests Railspike debug log

## 2026-11-22 UTC — one-shot closeout

### Necessary/useful instructions

- Host `AGENTS.md`, implementation standards, reuse: found the smoke-entry check and shared one helper with it rather than adding a second lookup. Retain.
- `one_shot.md`, completion through the ordinary entry: the by-hand `./v3 test` runs are what show the reported failure is gone. Retain.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- UI, theme, concept and migration checks: test tooling only.

### Uncertain assessments

- Choice made without the maintainer: unlisted files are loaded automatically instead of being rejected with an error. Either closes the gap; loading asks nothing of a contributor. Easy to switch if an explicit list is preferred.
