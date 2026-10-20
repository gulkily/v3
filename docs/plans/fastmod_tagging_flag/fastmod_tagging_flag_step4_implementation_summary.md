> **Feature plan:** [Step 2](./fastmod_tagging_flag_step2_feature_description.md) · [Step 3](./fastmod_tagging_flag_step3_development_plan.md) · [Step 4](./fastmod_tagging_flag_step4_implementation_summary.md)

Debug mode: enabled

## Status

Implementation started on `feat/fastmod-tagging-flag`; planning commit `a9a009eb`. Step 1 skipped; Steps 2 and 3 approved. Separate follow-up to root meta tagging; no new effort-study selection. No instance activation or deployment is authorized by implementation.

## Stage 1 - Shared flag and precedence

- Changes: one site-mutable/default-off Automatic Fastmod tagging flag, grouped after existing Fastmod scoring flags and dependent on scoring. Narrow legacy fallback follows explicit shared site values; no second meta flag or unrelated precedence change.
- Verification: 23 flag/policy/private-config tests passed, covering true/false precedence, dependency, invalid state, legacy compatibility and existing flag behavior. Updated registry inventory expectation for the one new flag. Syntax/diff checks passed.
- Notes: no instance flag saved. Runtime wiring is Stage 2.
