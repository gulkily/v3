> **Feature plan:** [Step 1](./username_change_gate_step1_solution_assessment.md) · [Step 2](./username_change_gate_step2_feature_description.md) · [Step 3](./username_change_gate_step3_development_plan.md) · [Step 4](./username_change_gate_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Same-key username changes shipped on 2026-10-15 for everyone, with no operator control and no activity entry. The Account button "Re-setup / change name" generates a replacement key, so its caption misleads next to a real rename.

## User stories

- As an operator, I want username changes off until I enable them so that I control when identities can be renamed.
- As a reader, I want a rename shown in the activity report so that I can see when a member changed their name.
- As a member, I want a button caption that says it replaces my key so that I do not lose my approval by mistake.

## Core requirements

- A site-mutable feature flag, off by default, controls username changes. While off, Account hides "Choose username" and both naming endpoints reject requests; the gate is not UI-only.
- While on, each verified name change appears in the activity report with the member's old and new names, time and link to the record, across every activity view and the feed.
- While on, the key-replacing Account button uses a new caption (chosen below); while off it keeps "Re-setup / change name".
- Approvals, keys and identity IDs are never altered by the flag. Already-saved names keep displaying when the flag is off (assumption; confirm).

## Delivery scope

**Work type:** Application change. Excludes new rename rules, name uniqueness, changes to the key-replacement flow itself, and the "composite identity" definition (tracked in `todo.txt`).

## Completion boundary

**Entry:** An operator enables the flag in the feature-flag settings. **Outcome:** A member renames from Account and the change appears in activity; with the flag off, neither the button nor the endpoints work. **Recovery:** Turning the flag off stops new renames and hides rename activity without deleting records; the read model rebuilds from canonical records. **Release:** Automated checks pass and the flag ships off by default.

## Risks

- **Read-model change:** Activity rows need a schema bump and rebuild. Earliest validation: rebuild a copy of `state/local_repository` (has one real record). Mitigation: Step 3 decides full-rebuild and incremental behavior together.
- **Flag-off semantics:** Reverting existing names would silently relabel members and move them between composite users. Mitigation: keep saved names (assumption above) and settle before Step 3.
- **Caption choice:** Wording must signal a new key and identity. Candidates: "Replace key (new identity)", "Start over with a new key", "Re-setup with a new key". Recommend the first; the user chooses now.

## Shared component inventory

- Feature flags: extend `FeatureFlagRegistry`, evaluated through `TemplateRenderer` and the page script options like the guest-keypair flag.
- Account UI: `templates/pages/account_key.php` (both buttons, caption) and the rename handler in `browser_signing.js`.
- API: `WritePostAndIdentityApiController` and `LocalWriteService` naming endpoints, plus the `Application` routing allowlist.
- Activity: `ReadModelBuilder::indexActivity`, `IncrementalReadModelUpdater`, `ActivityService`, `activity.php`, the Forte activity views, RSS, detail article, commit manifest and SQLite viewer catalog; extend the existing `activity` table and views, no parallel feed.
- Docs: `invitation_entry.md`, `read_model_schema_v1.md`, `profile_read_contract_v1.md`.

## Simple user flow

1. Operator enables the username-change flag.
2. Member opens Account, chooses a username and signs.
3. The new name shows on profiles; the activity report lists the change.
4. Operator disables the flag; renaming stops, saved names remain.

## Success criteria

- Fresh installs have the flag off; with it off, the button is hidden and both endpoints refuse, in tests.
- With it on, a rename yields exactly one activity row in the report, filters, feed and detail view, after full and incremental rebuilds.
- Approval state and identity ID are unchanged by a rename in tests.
- The enabled-state caption appears only while the flag is on.

Status: Step 2 drafted for review. Decisions needing confirmation: flag-off keeps saved names; rename activity hidden when flag off; caption.
