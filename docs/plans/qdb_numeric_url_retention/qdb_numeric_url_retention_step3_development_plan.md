> **Feature plan:** [Step 1](./qdb_numeric_url_retention_step1_solution_assessment.md) · [Step 2](./qdb_numeric_url_retention_step2_feature_description.md) · [Step 3](./qdb_numeric_url_retention_step3_development_plan.md) · [Step 4](./qdb_numeric_url_retention_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract

- Listing links open the existing quote experience at `/<number>` with HTTP 200 and no redirect. Refresh/sharing retain that address; voting, flagging, and viewer state work.
- Missing numbers retain HTTP 404; existing long links, exact-ID aliases, unnumbered threads, and non-QDB routes retain their behavior. Missing static details fall back to runtime rendering.
- Verify runtime and local Apache rewrites, with/without prebuilt details and viewer cookies. Release requires browser and regression checks to pass; unavailable deployment verification blocks completion. No schema migration or production deployment.

## Key Risks

- **High risk: Delivery mismatch.** Cookies or absent artifacts bypass static serving and currently cause redirects. Validate the delivery matrix first; reuse the existing runtime detail renderer and preserve the static alias. Resolve failures before release.
- **High risk: Interaction regression.** A numeric route could omit identity or reaction state. Validate the shared renderer and actual vote/flag behavior; retain existing page components and APIs. Resolve before hosted acceptance.
- **Route compatibility.** The fallback checks exact IDs before numbers. Confirm precedence and profile gating before changes; limit direct rendering to numbered-quote resolution and cover legacy/missing routes.

## Stage 1

- Goal: Complete the runtime listing-to-quote journey with the numeric address retained; approximately one hour.
- Dependencies: Explicit Step 3 approval; create the feature branch, organize approved plans under `docs/plans/qdb_numeric_url_retention/`, update navigation/index references, and make the planning-only commit before implementation.
- Expected changes: Wire the existing detail renderer into numeric resolution, preserving not-found handling and exact-ID redirects. Extend the QdbExperience constructor with that dependency; retain `dispatchUnmatched(string $path): ?QdbExperienceRouteResult` and `thread(string $threadId, string $createdPostId = ''): ?string`. Update redirect expectations and numeric-page interaction coverage.
- Verification approach: Run `QuoteCardDisplayNumberTest`, `QdbExperienceRoutingTest`, and relevant `WriteApiSmokeTest` cases for content/status, missing numbers, legacy/non-QDB routes, and voting/flagging state. Browser-check listing click-through and refresh without redirects.
- Risks or open questions: Incorrect resolution or missing viewer context could serve the wrong/incomplete page; compare numeric and long-route content and interactions before advancing, using the same renderer.
- Canonical components/API contracts touched: QdbExperience, Application wiring, QdbExperienceRouteResult, QdbQuoteNumbers, ThreadAndPostPageController, existing quote presentation and vote/flag endpoints.

## Stage 2

- Goal: Complete hosted delivery and release verification; approximately one hour.
- Dependencies: Stage 1 verification, summary update, and stage commit; unresolved routing/interaction failures block this stage.
- Expected changes: Update static-release fallback expectations; retain numeric artifacts/internal rewrites, fixing demonstrated address-retention gaps. Record release evidence and blockers.
- Verification approach: Exercise FrontController and Apache across the delivery matrix: HTTP 200, correct quote, no Location redirect, missing-number 404. Browser-check click-through, refresh, copied-link reopening, and vote/flag state. Run affected suites and the full suite once; compare failures against baseline.
- Risks or open questions: PHP-only checks cannot prove Apache behavior; validate actual rewrite delivery and runtime fallback before claiming completion. Split the stage before implementation if discovered work exceeds its estimate.
- Canonical components/API contracts touched: StaticArtifactBuilder, FrontController, `public/.htaccess`, QuoteCardDisplayNumberTest, shared reaction scripts/APIs; preserve their existing contracts.

Each stage ends with verification, a Step 4 summary update, and a separate commit before the next stage; minimum three commits including planning.
