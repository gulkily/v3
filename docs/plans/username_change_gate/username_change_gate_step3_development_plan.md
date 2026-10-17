> **Feature plan:** [Step 1](./username_change_gate_step1_solution_assessment.md) · [Step 2](./username_change_gate_step2_feature_description.md) · [Step 3](./username_change_gate_step3_development_plan.md) · [Step 4](./username_change_gate_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Normal entry: an operator enables the new site-mutable flag (default off) in feature-flag settings; a member renames from Account.
- End-to-end outcome: flag off — button hidden, both naming endpoints refuse, rename activity hidden, saved names still display, caption stays "Re-setup / change name". Flag on — rename works, each verified rename is one activity row in every activity surface, and the key-replacing button shows the approved new caption.
- Recovery: toggling the flag never deletes records; a rebuild from canonical files restores all derived state.
- External verification: rebuild a copy of `state/local_repository` (one real record) and check its activity; one real-browser Account check with the flag on and off.
- Release condition: all stages committed, tests green, flag ships off by default.

## Key Risks

- **High risk (data/rollback):** schema bump 20 → 21 forces a rebuild. Validate on a copy of local state first; rollback is reverting the commit and rebuilding.
- **High risk (usability):** flag-off must not relabel members. Saved names keep replaying; only new renames and activity visibility are gated. Test it.
- Activity surfaces are many (page, Forte, RSS, detail, manifest, SQLite catalog); one missed consumer shows a broken row. Mitigation: a single stage inventories each against a fixture.
- Caption wording is the user's decision, needed before Stage 3 (recommended: "Replace key (new identity)").

## Stage 1
- Goal: Server-side gate with a default-off flag.
- Dependencies: none.
- Expected changes: new site-mutable flag constant and definition in `FeatureFlagRegistry` (group ACCESS); `prepareProfileName` and `finalizePreparedProfileName` refuse with a clear error when off; docs flag list entry.
- Verification approach: extend `WriteApiSmokeTest`: off by default refuses both endpoints; on succeeds; existing rename tests enable the flag.
- Risks or open questions:
  - Impact: a prepared token could outlive a toggle. Mitigation: finalize re-checks the flag.
- Canonical components/API contracts touched: `FeatureFlagRegistry`, `LocalWriteService`, `/api/prepare_profile_name`, `/api/finalize_profile_name`.

## Stage 2
- Goal: Hide "Choose username" when the flag is off.
- Dependencies: Stage 1.
- Expected changes: pass the flag to the Account template through the existing renderer/flag pattern; omit the button and rename handler wiring when off.
- Verification approach: render test of `/account/key/` with the flag off and on; `BrowserSigningNormalizationTest`.
- Risks or open questions:
  - Impact: cached presentation shows stale markup. Mitigation: confirm flag toggles already invalidate generated pages.
- Canonical components/API contracts touched: `TemplateRenderer`, `account_key.php`, `browser_signing.js`.

## Stage 3
- Goal: New caption for the key-replacing button while the flag is on.
- Dependencies: Stage 2; caption chosen.
- Expected changes: ready-state caption in the Account script depends on the flag; off keeps "Re-setup / change name"; update the older plan reference if needed.
- Verification approach: Account render/script test for both states and both button states (ready/not ready).
- Risks or open questions:
  - Impact: caption mismatch with docs. Mitigation: grep docs/tests for the old text.
- Canonical components/API contracts touched: `account_key.php`, the same flag option as Stage 2.

## Stage 4
- Goal: One activity row per verified rename in the read model.
- Dependencies: Stage 1.
- Expected changes: new record family for profile names in `indexActivity`, reusing `ProfileNames` ordering and showing previous and new name; `ReadModelMetadata::SCHEMA_VERSION` 21; confirm incremental updater keeps these rows (name writes already full-rebuild).
- Verification approach: unit/smoke rebuild with two renames yields two ordered rows; unsigned/malformed record yields none; rebuild of a copy of local state shows the real record.
- Risks or open questions:
  - Impact: old name for the first rename is the bootstrap name. Mitigation: derive from the previous record or bootstrap username in replay.
- Canonical components/API contracts touched: `ReadModelBuilder`, `ProfileNames`, `IncrementalReadModelUpdater`, `ReadModelMetadata`, activity table.

## Stage 5
- Goal: Rename rows appear in the shared activity query and main page, hidden when the flag is off.
- Dependencies: Stage 4.
- Expected changes: `ActivityService` view filters, counts and cursors exclude the family when off; row label and source link in `activity.php` and the detail article.
- Verification approach: activity page and pagination tests on a fixture with the flag on/off; counts match visible rows.
- Risks or open questions:
  - Impact: counts or cursors include hidden rows. Mitigation: filter in the shared view SQL, not per template.
- Canonical components/API contracts touched: `ActivityService`, `activity.php`, `paned_activity_detail_article.php`.

## Stage 6
- Goal: Remaining activity consumers show rename rows correctly.
- Dependencies: Stage 5.
- Expected changes: Forte activity views, classic and RSS feeds, commit manifest, SQLite viewer catalog and its tests handle the new family.
- Verification approach: feed/Forte/manifest tests with a rename fixture, flag on and off.
- Risks or open questions:
  - Impact: a consumer assumes post-backed rows (no post id). Mitigation: fixture row without post/thread ids exercises each.
- Canonical components/API contracts touched: `ForteActivityController`, `ActivityPageController`, `ActivityService` manifest code, `sqlite_query_catalog.sql`.

## Stage 7
- Goal: Docs and end-to-end verification.
- Dependencies: Stages 1–6.
- Expected changes: update `invitation_entry.md`, `read_model_schema_v1.md` (version 21), `profile_read_contract_v1.md:57`, flag docs, and the research report; full test run; real-browser Account check.
- Verification approach: full `php tests/run.php`; browser check flag off/on; rebuild copy of local state.
- Risks or open questions:
  - Impact: unrelated suite failures. Mitigation: report them, do not hide them.
- Canonical components/API contracts touched: docs only, plus verification of all above.
