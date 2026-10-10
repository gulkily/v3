> **Feature plan:** [Step 1](./qdb_quote_id_authoring_scope_step1_solution_assessment.md) · [Step 2](./qdb_quote_id_authoring_scope_step2_feature_description.md) · [Step 3](./qdb_quote_id_authoring_scope_step3_development_plan.md) · [Step 4](./qdb_quote_id_authoring_scope_step4_implementation_summary.md)

## Completion Contract

- Normal entry: a QDB contributor submits Add Quote or `/compose/thread`, signed or unsigned.
- End-to-end outcome: only Add Quote creates a sequential numbered QDB quote; general thread operations create regular IDs and retain their normal thread permalink.
- Required recovery: existing validation and signed-post recovery remain available; non-quote threads remain directly reachable while quote collections continue to exclude them.
- Deployment/external verification: no migration, deployment configuration, or external service change; verify the affected request flows and regression suite.
- Release condition: direct, signed, and UI authoring-path coverage passes; existing quote permalink/listing coverage remains green; the implementation summary records verification.

## Key Risks

- **High risk:** a generic QDB path continues to mint a quote ID. Impact: ordinary threads consume quote numbers and appear as quotes. Early validation: direct generic create/prepare tests. Mitigation: make regular IDs the default contract and expose quote allocation only through the dedicated operations.
- **High risk:** a signed quote is prepared or retried as a regular thread. Impact: finalization fails or produces the wrong ID type. Early validation: signed preparation and retry-path coverage. Mitigation: route all Add Quote browser operations through the same quote-specific contract.
- Existing records could be reinterpreted. Impact: broken QDB permalinks or listings. Early validation: existing quote-number and quote-card tests. Mitigation: keep ID parsing and collection policy unchanged.

## Stage 1

- Goal: establish distinct server-side contracts for quote authoring and regular thread authoring.
- Dependencies: approved Steps 1–2.
- Expected changes: scope QDB quote-ID allocation to explicit quote-create and quote-prepare operations; leave current generic create/prepare operations regular on every profile; add the corresponding HTTP/API routes without changing existing record formats.
- Verification approach: extend focused write/API coverage to prove a QDB generic create and prepare produce regular IDs, while dedicated quote create and prepare produce the next numbered quote ID.
- Risks or open questions:
  - Impact: an incomplete route-to-writer mapping can select the wrong ID type.
  - Early warning / validation: exercise each new and existing direct operation in one QDB fixture.
  - Mitigation: keep allocation selection at the shared writer boundary and test every exposed operation.
- Canonical components/API contracts touched: `Application`, `WritePostAndIdentityApiController`, `LocalWriteService` create/prepare contracts, `QdbQuoteNumbers`.

## Stage 2

- Goal: make the QDB Add Quote form select the quote contract while the general composer keeps the regular-thread contract.
- Dependencies: Stage 1's dedicated server operations and verified contracts.
- Expected changes: extend the shared compose-form configuration for its authoring operation; wire `qdb_add.php` to the quote operation; update browser signing's unsigned, prepared, and retry submission selection to preserve that operation; retain `/compose/thread` behavior and presentation.
- Verification approach: exercise signed and unsigned submissions from each form path, including a signed retry, and assert their returned IDs and destinations match the selected authoring path.
- Risks or open questions:
  - Impact: a browser-only route choice can diverge from normal form submission.
  - Early warning / validation: cover both JavaScript-enhanced and server form paths for Add Quote and `/compose/thread`.
  - Mitigation: derive submission selection from the shared form configuration rather than duplicated route checks.
- Canonical components/API contracts touched: `qdb_add.php`, `thread_compose_form.php`, `browser_signing.js`, `ComposeAndAccountKeyController` where form submission handling is shared.

## Stage 3

- Goal: lock the authoring distinction into the QDB regression contract without changing quote presentation.
- Dependencies: Stages 1–2 complete and their focused checks passing.
- Expected changes: add or update integration coverage for Add Quote, `/compose/thread`, generic API defaults, signed finalization, quote numeric permalinks, and quote-only collection filtering; update the Step 4 summary with verification evidence.
- Verification approach: run the focused QDB/write tests and the applicable full test suite; confirm no schema version or migration is needed and inspect created IDs in both flows.
- Risks or open questions:
  - Impact: a regression could hide regular threads or alter old quote rendering.
  - Early warning / validation: run existing quote-number, quote-card, and profile-routing coverage alongside the new cases.
  - Mitigation: preserve `QdbBoardPolicy` and numeric-resolution behavior, changing only authoring selection.
- Canonical components/API contracts touched: `WriteApiSmokeTest`, `QdbQuoteNumbersTest`, `QuoteCardDisplayNumberTest`, `QdbBoardPolicyTest`, existing presentation/profile regression contracts.
