# Effort study ledger, one file per cycle

The `railspike-effort` study (reference R1, protocol P2) indexes each cycle in its own file here, so PRs for different cycles never edit the same ledger file. Protocol, R1 and history before 2026-11-15 are in [the original ledger](../effort_estimation_trial.md), which is frozen for appends.

## File name

`<feature_prefix>.md`, the part of the cycle ID after `v3/`. Example: `v3/local_telemetry` → `local_telemetry.md`. Reuse the file across worktrees and checkpoints of the same cycle; a follow-up cycle gets its own file and a parent link.

## Contents

```text
# Cycle v3/<feature_prefix>

Earlier entries: [original ledger](../effort_estimation_trial.md#cycle-v3feature_prefix)   ← only if the cycle has a section there

| Stable cycle ID | Status | Scope / reference / protocol | Projected total | Canonical checkpoints | Parent / acceptance |
| --- | --- | --- | --- | --- | --- |
| `v3/<feature_prefix>` | ... | v1 / R1 / P2 | ... | [record](../<feature_prefix>/<feature_prefix>_one_shot.md#...) | ... |

<UTC date>: <checkpoint line, as in the original ledger>
```

Update the status row in place; append one dated line per checkpoint and keep every earlier line. Snapshots stay in the cycle's own artifacts; this file links to them.
