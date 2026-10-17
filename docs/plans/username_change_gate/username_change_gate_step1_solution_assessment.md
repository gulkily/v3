# Username change gate — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./username_change_gate_step1_solution_assessment.md) · [Step 2](./username_change_gate_step2_feature_description.md) · [Step 3](./username_change_gate_step3_development_plan.md) · [Step 4](./username_change_gate_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

We need to fix this situation in two ways:
1) Gate the "choose username" feature behind a feature flag, off by default.
2) When the feature is turned on, it needs to show up in the activity report.
3) We need to find a new caption for the "Re-setup / change name" button when the feature is enabled.

## Understood Intent

"Two ways" is followed by three items; all three are treated as in scope. "The feature" is the signed same-key rename (Account → Choose username, `/api/prepare_profile_name`, `/api/finalize_profile_name`, `records/profile-names/`), added 2026-10-15 without a flag or activity entry ([report](../../research/username_change_report.md)). The "Re-setup / change name" button is the Account "Set up this browser" button, which switches to that text once a key exists; it generates a new key, so "change name" is misleading next to a real rename.

## Problem

Same-key renaming is live for everyone, leaves no activity trace, and sits beside a button whose caption implies it also renames.

## Option A — Registry flag, activity indexed in the read model

Add a site-mutable flag (default off) to `FeatureFlagRegistry`. When off, the Choose-username button is hidden and both endpoints reject requests. When on, rebuild and incremental updates write an `activity` row per verified name record, and the Account button uses the new caption.

- Pros: follows the existing flag pattern; activity shares feeds, filters, pagination and RSS; enforced server-side, not just in the UI.
- Cons: new activity kind needs read-model schema bump and rebuild; touches full-rebuild and incremental paths; replay semantics when the flag is off must be decided.

## Option B — Registry flag, activity derived at query time

Same flag and gating as A, but `ActivityService` merges name records into the feed when it is read, with no read-model change.

- Pros: no schema bump or rebuild; smaller write-path change.
- Cons: breaks the "one indexed activity table" model; pagination, filters and counts must be reconciled by hand; likely slower and duplicated per view.

## Option C — UI-only gate, activity from commit history

Hide the button behind the flag but leave the endpoints open; surface renames via existing commit-based activity ("Set profile name for …").

- Pros: smallest change.
- Cons: not a real gate (API still works); commit messages carry opaque identity IDs; fails the "show up in the activity report" intent.

## Recommendation

Option A: it is the only option that gives a real gate and a first-class activity entry, and it fits one vertical slice (flag → gated UI/API → activity row → caption → tests/docs). Caption candidates for the enabled state, to choose in Step 2: "Replace key (new identity)", "Start over with a new key", "Re-setup with a new key". Open decisions for Step 2: (1) with the flag off, do existing saved names still display (recommended) or revert to bootstrap names; (2) are name-change activity rows hidden when the flag is off; (3) caption when off stays "Re-setup / change name".
