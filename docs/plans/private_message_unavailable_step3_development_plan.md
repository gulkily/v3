# Compact unavailable private messages Step 3 development plan

> **Feature plan:** [Step 1](./private_message_unavailable_step1_solution_assessment.md) · [Step 2](./private_message_unavailable_step2_feature_description.md) · [Step 3](./private_message_unavailable_step3_development_plan.md) · Step 4 pending

## Completion Contract

- Deliver Messages → conversation → expand unavailable group → details/retry → verified recovery or honest failure, preserving history, replies, drafts, warnings, and unread boundaries. Twenty eligible messages become one summary, expandable to twenty ordered placeholders.
- Release requires the focused suites and normal encrypted browser journey. Approved Step 2 exclusions apply; no schema/API changes, deployment, merge, or push. Document coordinated asset rollout/rollback and manual-device limitations.
- Six ≤1-hour stages. After approval, branch `feature/private-message-unavailable`; commit approved Steps 1–3 only, then each verified stage with its summary. Group four artifacts/update index in Stage 1. Rescope above eight stages/one day.

## Key Risks

- **High risk:** misclassification hides recovery/warnings; Stage 1 tests all outcomes, leaves unknowns visible, and gates plaintext on verification.
- **High risk:** regrouping loses chronology/focus/anchors; Stage 2 preserves identities and validates shared presentation lookup before history/retry integration.
- **High risk:** collapsed latest messages distort acknowledgment; Stage 2 tests summary visibility, Stage 5 stresses unchanged boundaries. Unresolved risks block dependents.

## Stage 1

- Goal: compact single-message recovery.
- Dependencies: approved plan.
- Expected changes: conservative categories, accessible details/manual retry, direct loading/support retry, prominent signature warnings.
- Verification approach: reader/list tests for all categories, recovery, verification gating, and no retry loops.
- Risks or open questions: broad catches obscure cause; test classification first and keep unknowns visible.
- Canonical components/API contracts touched: reader/results, item, styles, preview consumer.

## Stage 2

- Goal: collapsed initial history.
- Dependencies: Stage 1.
- Expected changes: same-sender/date runs ≥2, count/time summaries, accessible expansion; `regroup(root)` and `presentationFor(root, card)` supply shared grouping/visible lookup.
- Verification approach: twenty-to-one expansion, run breaks, keyboard, Latest navigation, initial summary acknowledgment.
- Risks or open questions: hidden cards invalidate selectors; test ordering/visibility consumers together, retain identities, exclude pending reads.
- Canonical components/API contracts touched: formatting, timestamps, items, Latest, seen visibility.

## Stage 3

- Goal: stable older history/restart.
- Dependencies: Stage 2.
- Expected changes: settled regrouping, cross-page anchors/expanded choices, obsolete-summary cleanup, retained concurrent sends.
- Verification approach: three pages, joins, delayed reads, failure/retry, exact message coverage, user-controlled scrolling.
- Risks or open questions: joins hide focused content; test early, preserve either run's open state and identity-based anchors.
- Canonical components/API contracts touched: history, presentation lookup, dates, cursor/restart consumers.

## Stage 4

- Goal: nondisruptive retry/send recovery.
- Dependencies: Stage 3.
- Expected changes: visible progress, ordered recovered plaintext, stable details/focus; focus recovered message if retry disappears.
- Verification approach: split/merge, repeated failure, concurrent history/send/newer draft, keyboard continuity.
- Risks or open questions: late completion targets obsolete cards; test races, validate ownership, isolate composer state.
- Canonical components/API contracts touched: reader settlement, grouping/append, shared composer.

## Stage 5

- Goal: unchanged unread recovery.
- Dependencies: Stage 4.
- Expected changes: normal-flow coverage and visibility/event-ordering corrections.
- Verification approach: collapsed/expanded latest, hidden/unfocused tab, history loading, stale retry, identity change, later/backdated arrivals, own sends.
- Risks or open questions: grouping races settlement; test delayed outcomes and require settled representation without broadening receipts.
- Canonical components/API contracts touched: presentation lookup, seen/unread controllers, browser recovery scenarios.

## Stage 6

- Goal: verified handoff.
- Dependencies: Stages 1–5.
- Expected changes: final fixtures/reporting, rollout notes, checklist, summary.
- Verification approach: focused messaging/release suites, full browser journey, before/after layout, desktop/375px/zoom/keyboard, no overflow, static/offline exclusion, syntax/links/whitespace; audit seven commits.
- Risks or open questions: asset/browser mismatch; validate isolated release, document coordinated rollback and untested physical devices/engines.
- Canonical components/API contracts touched: browser harness, release isolation, FDP artifacts.
