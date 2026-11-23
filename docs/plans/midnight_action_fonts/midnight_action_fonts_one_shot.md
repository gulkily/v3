# Midnight post actions match Word 97 — one-shot

Cycle: `v3/midnight_action_fonts`; Mode: one-shot; Record/index: this file, [plans index](../README.md). Follow-up to `v3/word97_action_fonts` (PR #380, merged).
Debug mode: enabled ([log](midnight_action_fonts_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/midnight_action_fonts`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/midnight_action_fonts.md))
Extended estimates: enabled (no real alternatives or work units worth estimating separately)

**Cycle estimate: 0.10**

Status: plan written; next permitted action: implementation on `fix/midnight_action_fonts`.

## Request and authorization

Original request: the maintainer, 2026-11-23: "please one-shot midnight fixes", replying to the PR #380 report that Midnight (Word 97's dark sibling) still shows Reply, Like and Flag serif and underlined. Matching feedback item: [PF-20261111-F19](../../feedback/items/PF-20261111-F19.txt) ("Bring the button styling (Like/Flag) in Midnight in line with the Word 97 theme").

Authorization: that message authorizes this one-shot, pushing the branch and opening a PR. Not merging or deploying. Exclusions: PF-20261030-B05 ("Midnight theme display defect", symptom unknown) is not addressed.

Outcome and work type: application change (CSS only). In Midnight, the post action row gets Word 97's treatment: Reply, every `.thread-reaction-button` (Like, Flag, Agent, state tags, Retract…) and the More, "…", Post analysis and Details toggles use the UI font (Tahoma) with no underline; Like and Flag in the action row draw their raised bevel from the border with side padding, like Word 97's (PF-20261104-F48), in Midnight's greys.

## Approach and risks

- Mirror the two Word 97 rules from `theme-word97.css` into `theme-midnight.css`: the post-action typography rule and the action-row bevel rule (repeating the generic button rule's `:not()` chain so it outranks it). Colours from Midnight's existing button rule: highlight `#6a6a6a`, shadow `#000000`, face `#2b2b2b`.
- Risk: low; font, padding and border colour only.

Reuse decisions: copy the Word 97 rule shapes; reuse `--ui-font` and Midnight's palette. No shared-file change.

Completion conditions: on a thread page in Midnight, the action controls compute to Tahoma with no underline, and Like/Flag have side padding and a border-drawn bevel; a screenshot reads like Word 97's in dark.

Concepts touched: none.

Verification and recovery plan: `./v3 test --smoke PostActionThemeTest Word97ThemeCoverageTest` with Midnight added to the post-action test; headless Chrome computed styles and before/after screenshots. Recovery: revert the CSS.

Starting revisions and branch: `fix/midnight_action_fonts` from `origin/main` `9ae843cf6`.

Planning commit: pending.

## Study history

- 2026-11-23 UTC, intake and execution entry: **0.10**, after the baseline screenshot and reading Midnight's button rules. Claude, single exposed rater (wrote the parent cycle). Scope v1. Reference R1, hashes not re-verified.

## Dated execution history

- 2026-11-23: baseline (headless Chrome, `/threads/root-001`, Midnight): Reply, Like, Flag in Times New Roman with underline, Like/Flag with no side padding and a black border; More, "…", Post analysis, Details in Times New Roman.
