# Import output noise one-shot

Cycle: `v3/import_output_noise`; Mode: one-shot; Record/index: [this record](./import_output_noise_one_shot.md), [plans index](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/import_output_noise`; [ledger entry](../effort_ledger/import_output_noise.md). Standing P2 enrollment.

Extended estimates: disabled

**Cycle estimate: 0.25**

Status: implemented; PR pending. Next: the maintainer reruns the import on WSL.

## Original request

The maintainer attached the full output of `./v3 import-instance zenmemes.com` on WSL (a first import into a fresh instance, 1,092 lines, 40 s) and wrote: "we need to get rid of the spam".

## Authorization

The request itself, 2026-11-17, under `AGENTS.md`'s one-shot default. Scope: what `import-instance` prints. Excluded: what is imported, the saved `report.json`, the read model, and the import page's step list.

## Outcome and work type

Application change. A default `import-instance` run prints a short, readable log: phase lines, one line per kind of review cause, and the final counts. Nothing is lost: `--verbose` still lists every file, and `report.json` still holds all of it.

The attached run spent its lines on:

- About 305 `Review cause:` lines, one per rejected file, even without `--verbose`.
- About 90 `Progress: <step> n/m` lines that repeat a human line next to them. These exist for the Tools → Import page.
- About 520 `Read model: … (n/m).` and `Installed N record files...` counter lines, every 100 records, and 45 `Downloaded N bytes...` lines.

## Approach and risks

1. `Progress:` lines print only with a new `--progress-lines` option. `InstanceImportTask` passes it, so the import page keeps its step list.
2. Without `--verbose`, review causes are grouped by state and reason: `Review: 64 unsupported — Unsupported record family or path`. With `--verbose`, the per-file `Review cause:` lines print as today.
3. A counter line prints at most once a second when the previous printed line had the same wording apart from its numbers. A line whose count reaches its total (`n/n`) always prints, so every step still shows where it ended.

Risks:

- **The import page loses its steps.** Impact: no progress view on Tools → Import. Early validation: `InstanceImportTaskTest` and `ImportPageTest`. Mitigation: the task passes `--progress-lines`, and `Progress:` lines are never throttled.
- **A stall looks like a hang.** Impact: the last counter line could be up to a second old. Early validation: the throttle keeps the first line of each run of counters and every final line. Mitigation: a one-second limit.
- **Scripts that read `Review cause:` lines break.** Impact: anything parsing the default output. Early validation: grep for readers. Only `ImportInstanceCommandTest` reads them; `--verbose` and `report.json` keep the per-file data.

## Reuse decisions

- `scripts/import_instance.php` `$progress` closure: the single place every import line passes through, so the option filter and throttle live there. No new output class.
- `ImportProgressView.php` and `InstanceImportTask.php` keep parsing `Progress: <step>` unchanged; only the task's command line gains the option.
- The review-cause data already comes from `ContentImportRunner` as `review_causes` with `state`, `reason` and `affected_count`; grouping uses those fields.
- `InvocationSupervisor::stage()` still receives every line, including hidden and throttled ones, so timeout messages name the real stage.

## Completion conditions

- Normal entry: `./v3 import-instance <host>`, `--dry-run`, `--verbose`, `--resume`, and Tools → Import.
- End-to-end: the zenmemes.com import prints under 150 lines by default, with the same final counts; `--verbose` still lists every file; the import page still shows its steps.
- Recovery: unchanged; `--resume` and `--cancel` messages are not filtered.
- Release: focused tests and the smoke set pass; the maintainer reruns the import on WSL and confirms the output reads well.

## Concepts touched

[Operator command progress](../../concepts/operator_command_progress.md) and [Instance content import](../../concepts/instance_content_import.md): check both against the new default output and update them if they describe it.

## Verification and recovery plan

- Update `ImportInstanceCommandTest` for grouped causes, `--verbose` per-file causes, and `Progress:` lines only with `--progress-lines`; add a throttle check.
- Run `InstanceImportTaskTest`, `ImportPageTest`, `ImportProgressViewTest`, `ImportInstanceCommandTest`, `LocalAppSmokeTest` and the smoke set.
- Replay against the zenmemes.com archive (served on loopback, since PHP downloads are blocked in this sandbox) and count output lines before and after.
- The reported environment (WSL) stays unverified until the maintainer reruns it.

## Starting revisions and branch

Branch `fix/import_output_noise` from `origin/main` `80107069`.

Planning commit: `7f5ac58d`.

## Dated execution history

- 2026-11-17: built the three planned changes in a new `ImportOutputFilter` class instead of script functions, so they can be unit-tested; the script only loads it.
- 2026-11-17, discovery: "Missing dependency" reasons carry the missing post's ID, so grouping by raw reason printed one line per post. The group drops the ID; `--verbose` and `report.json` keep it.
- 2026-11-17, correction: a first throttle compared all lines with digits removed, which could drop `--verbose` per-file lines whose paths differ only in digits. Throttling now applies only to counter lines (`... (n/m).` and `Installed N record files...`).
- 2026-11-17, discovery: the replay still printed 218 lines, about 130 of them read-model sub-steps. Added within the authorized output scope: without `--verbose`, `Read model:` detail lines print only after five quiet seconds. The import already prints "Refreshing read model…" and "Read-model promotion complete."
- 2026-11-17, correction: counters without a total stopped at their first value (`Installed 100 record files...` then nothing). The filter now returns the lines to print and flushes the last held-back counter before the next different line.
- 2026-11-17, correction: hiding `Progress:` lines left planning silent in terminals again, the problem `v3/import_planning_speed` started from. Without `--progress-lines`, `Progress: plan n/m` becomes `Checking records (n/m).`, throttled like other counters.
- 2026-11-17, correction: grouped lines first used a `Review:` prefix, which clashed with the final `Review: …/report.json` line. They now follow the existing `Excluded: reason (N entries)` style as `Invalid: Missing dependency (31 files)`. Excluded causes are left to the `Excluded:` lines, which already count them.

## Outcome

Delivered: a default `import-instance` run of the zenmemes.com archive into a fresh checkout prints 70 lines instead of 1,093, with the same final counts. Planning, install, download and image steps each show a few counter lines. Review causes take 5 lines instead of 303. `--verbose` still lists every file and rebuild step; Tools → Import still gets its `Progress:` lines.

Limitations: a failure exit may lose one held-back counter value, because the last counter is printed only when the next line arrives. WSL output is unverified until the maintainer reruns it.

## Verification

- Passed: `./v3 test --smoke ImportOutputFilterTest ImportInstanceCommandTest InstanceImportTaskTest ImportPageTest ImportProgressViewTest LocalDevelopmentImportTest ImportedContentPublicationTest ContentImportRecoveryTest ImportDeadlineRecoveryTest`: 523 run, 523 passed. Full suite not run.
- Passed: new `ImportOutputFilterTest` (6 tests): `Progress:` only on request; planning counter on terminals; counters once a second with their last value flushed; per-file lines never throttled; read-model detail hidden unless verbose or quiet for five seconds; grouping without record IDs and without excluded causes.
- Passed: `ImportInstanceCommandTest` now checks that a default run prints no `Progress:` lines, that `--progress-lines` prints the full step order, and that a preview prints the grouped `Conflict: … (1 file)` line and no per-file `Review cause:` lines.
- Passed: `InstanceImportTaskTest` runs a real import through the worker and still records step times from `Progress:` lines, so the page path works with the new option.
- Passed: live replay, zenmemes.com archive and `images.zip` from 2026-11-17 served on loopback, fresh checkout. `main` `80107069`: 1,093 lines (165 `Progress:`, 303 `Review cause:`). This branch: 70 lines. `--verbose`: 1,554 lines with all 303 per-file causes. Counts identical on all runs: import 7435, invalid 226, unsupported 89, excluded 725.
- Passed: `./v3 import-instance --help` shows the new option and wording.
- Pending: the maintainer's WSL rerun.

## Final review

Checked the diff against `origin/main`: one new class and its test, the CLI using it, one option added to the task's command, test updates, and docs (CLI reference, runbook, operator-progress concept). No change to what is imported or saved. The `--dry-run` and `--resume` paths share the same closure. The concept entry for operator command progress now names the import filter.

## Study history

- Intake (after reading the output and the printing code): 0.25. Claude single exposed rater, 2026-11-17 UTC, R1, P2.
- Closeout retrospective: 0.30. Above intake because of the read-model detail, the held-counter flush and the planning counter, each found in the live replay.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
