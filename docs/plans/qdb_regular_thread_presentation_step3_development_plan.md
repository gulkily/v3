> **Feature plan:** [Step 2](./qdb_regular_thread_presentation_step2_feature_description.md) · [Step 3](./qdb_regular_thread_presentation_step3_development_plan.md) · [Step 4](./qdb_regular_thread_presentation_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a reader opens a QDB regular-thread or numbered-quote permalink.
- End-to-end outcome: regular threads render their title/body without quote controls; numbered quotes retain their existing quote card, controls, and numeric link.
- Required recovery: subjectless, malformed, and legacy non-quote roots use regular-thread presentation.
- Deployment/external verification: no schema, migration, deployment configuration, or external service change; validate dynamic and static detail output.
- Release condition: focused presentation/static regressions pass and the Step 4 summary records verification.

## Key Risks

- **High risk:** a non-quote root remains classified as a quote. Impact: its title is hidden and quote controls are exposed. Early validation: direct regular-root rendering. Mitigation: classify from the canonical quote-ID predicate.
- Quote output can regress while changing its branch. Impact: lost numeric link or controls. Early validation: existing numbered-quote detail coverage. Mitigation: retain the current quote branch unchanged for valid IDs.
- Static detail output can differ from dynamic output. Impact: inconsistent public pages. Early validation: generated regular and quote detail pages. Mitigation: use the shared root-card template only.

## Stage 1

- Goal: classify QDB detail roots by actual quote ID rather than site profile.
- Dependencies: approved Step 2.
- Expected changes: update the shared root-card classification to use the established QDB quote-ID contract; retain the regular title/body branch and render quote controls only for numbered quote roots.
- Verification approach: add direct detail-page coverage for a titled regular QDB thread, malformed/legacy roots, and a numbered quote; assert their distinct heading, permalink, body, and control outcomes.
- Risks or open questions:
  - Impact: a permissive ID check can still misclassify regular content.
  - Early warning / validation: fixtures with valid, malformed, and regular IDs.
  - Mitigation: reuse `QdbQuoteNumbers` recognition rather than a new local format rule.
- Canonical components/API contracts touched: `thread_root_card.php`, `QdbQuoteNumbers`, `qdb_quote_actions.php`, `ThreadAndPostPageController` title data.

## Stage 2

- Goal: prove presentation parity across static detail output and preserve the existing quote contract.
- Dependencies: Stage 1 dynamic rendering and focused checks passing.
- Expected changes: extend static-detail regression coverage for regular and numbered QDB roots; update the Step 4 implementation summary with verification evidence.
- Verification approach: run focused quote-card/number/static tests and the applicable suite; inspect generated artifacts for a regular title/no quote controls and an unchanged quote permalink/actions.
- Risks or open questions:
  - Impact: static pages can retain the prior root-card classification.
  - Early warning / validation: compare generated detail artifacts with dynamic assertions for both root types.
  - Mitigation: retain the existing static builder and exercise its shared template output.
- Canonical components/API contracts touched: `StaticArtifactBuilder`, `QuoteCardDisplayNumberTest`, `QdbQuoteNumbersTest`, root-detail template contract.
