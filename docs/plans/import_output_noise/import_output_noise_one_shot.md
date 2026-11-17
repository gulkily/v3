# Import output noise one-shot

Cycle: `v3/import_output_noise`; Mode: one-shot; Record/index: [this record](./import_output_noise_one_shot.md), [plans index](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/import_output_noise`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: disabled

**Cycle estimate: 0.25**

Status: planned; next permitted action: implement.

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

Planning commit: pending.

## Study history

- Intake (after reading the output and the printing code): 0.25. Claude single exposed rater, 2026-11-17 UTC, R1, P2.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
