> **Feature plan:** [Step 2](./feature_flags_toolbar_step2_feature_description.md) · [Step 3](./feature_flags_toolbar_step3_development_plan.md) · [Step 4](./feature_flags_toolbar_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
- Application change: enter through Tools → Feature Flags; search name/key/description and select All/Enabled/Overridden/Locked with accurate counts, matching groups, and confirmed save updates. Clear search/select All to recover empty results; retain failed-save feedback and usable keyboard focus. Without JavaScript, keep the complete list and working save forms.
- Release: targeted PHP and browser checks pass; verify 375px/desktop, light/dark/console, sticky scrolling, keyboard access, and no-JavaScript behavior. Use isolated local fixtures and the existing asset loading path; no database/API changes or external deployment required. Remove the todo entry after verification. Revert this feature's application commits to roll back.

## Key Risks
- **High risk:** filtering a saved row can lose focus or hide feedback. Existing saves update rows before pending controls are restored; validate success/failure with active filters in Stage 2, refresh only confirmed state, and recover focus to search only when the focused row disappears.
- Status ambiguity corrupts counts. Inspection confirms `isDefault()` compares effective/default, while `isLocked()` excludes invalid site data and differs from viewer permissions; Stage 1 must expose those canonical values and search only name/key/description.
- Sticky overlap/overflow harms access. The version banner already sticks at the viewport top; Stage 1 must account for its visible height and layering, wrap controls, and enforce hidden-row styles. Validate scrolling with banner present and absent before Stage 2.

## Stage 1
- Goal: ship the complete search/filter interaction on the existing grouped page (~1 hour).
- Dependencies: approved Step 3; create feature branch, organize this feature's plans/log under `docs/plans/feature_flags_toolbar/`, repair references/index, and commit approved planning artifacts first.
- Expected changes: extend existing template, tool stylesheet, and script initialization with initially hidden enhanced controls, canonical row metadata, search and single-select status chips/counts, empty-group/result handling, live result count, slash shortcut, and responsive sticky styling.
- Verification approach: add a focused browser check using the real rendered template and script with isolated representative states; cover case/whitespace, each searchable field, overlapping statuses, search-relative counts, combined filtering, empty recovery, keyboard shortcut exclusions, read-only viewers, and no-JS list/forms. Check sticky layout and themes at both widths; run PHP syntax and relevant feature flag tests.
- Risks or open questions: stale metadata or CSS overriding `hidden`; validate rendered states and computed visibility early, using explicit canonical attributes and scoped hidden rules. Resolve sticky overlap before proceeding.
- Canonical components/API contracts touched: `templates/pages/feature_flags.php`, `public/assets/feature_flags.js`, `public/assets/tool-details.css`; existing state model reused unchanged. Add targeted browser/fixture coverage using existing test conventions. Update Step 4 summary and commit stage.

## Stage 2
- Goal: keep results accurate and keyboard access usable through signed saves; finish verification (~1 hour).
- Dependencies: Stage 1 verification, summary update, and stage commit complete.
- Expected changes: integrate filter/count refresh with confirmed toggle/reset updates and pending-control completion; preserve failed-save state/feedback and avoid stealing focus if the user moved elsewhere. Remove the completed toolbar todo entry.
- Verification approach: extend browser coverage for toggle/reset entering or leaving matches, count updates, failed saves, focus recovery, and moving focus during a pending request. Exercise the normal Tools entry and real signed-save path using the existing operator browser harness; retain existing messaging-navigation checks. Run `node --check public/assets/feature_flags.js`, targeted feature flag/evaluator/controller PHP checks, and the toolbar browser checks; record final responsive/theme/no-JS results.
- Risks or open questions: asynchronous saves and navigation refresh may interfere with focus; validate delayed responses and existing operator navigation coverage, refreshing from successful responses and recovering focus after controls are restored.
- Canonical components/API contracts touched: existing `feature_flags.js` save lifecycle, targeted browser checks/fixtures, `todo.txt`; prepare/finalize response contracts unchanged. Update Step 4 summary and commit stage before handoff.
