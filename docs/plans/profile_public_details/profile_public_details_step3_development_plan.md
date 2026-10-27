> **Feature plan:** [Step 1](./profile_public_details_step1_solution_assessment.md) · [Step 2](./profile_public_details_step2_feature_description.md) · [Step 3](./profile_public_details_step3_development_plan.md) · [Step 4](./profile_public_details_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/profile_public_details; [ledger](../effort_estimation_trial.md#cycle-v3profile_public_details); standing P2 enrollment
Extended estimates: enabled
Status: Steps 1–2 approved; Step 3 proposed; implementation unapproved

**Cycle estimate: 0.60**

## Completion Contract

Deliver standard `/profiles/<slug>` with collapsed, copyable public-key details and identity-scoped visible Threads/Posts, matching totals and bounded history. Complete direct-link/author-link navigation, missing-key/profile and empty-history states, First page recovery and manual-copy fallback. Preserve username aggregation, Tools approval, messaging, Forte and content-visibility rules. Verify direct and actual host/static paths on desktop/phone across affected themes; record local evidence separately from zenmemes.com acceptance. Stage 5 supplies the production replay procedure; deployment remains separately authorized. Documentation scope includes these plans/debug log, profile/read-model contracts, focused verification records and study ledger. No new dependencies or canonical-record changes.

## Key Risks

- **High risk:** Incorrect eligibility can expose hidden content or misattribute same-name identities. Stage 1 establishes identity/visibility fixtures and count agreement before presentation depends on them; preserve existing suppression rules and exclude bootstrap ownership as authorship.
- **High risk:** Serving an older-page URL as page one or loading entire histories breaks navigation and scale. Stage 3 validates bounded, context-scoped positions; Stage 4 validates FrontController/static routing before acceptance.
- **High risk:** Key blocks can overflow or obscure primary actions. Stage 2 verifies disclosure, copy fallback and long keys; Stage 5 checks themes and preserves warnings and activity outside disclosures.

## Stage 1 — Eligible identity contributions and totals

**Estimate: 0.18**

- Goal: Establish one visibility definition for this identity's lists and full totals.
- Dependencies: Approved plan and planning commit; restore current retraction handoff before reader work.
- Expected changes: Extend existing authored-content reading with identity-scoped eligibility/count support without altering existing aggregate consumers. Cover roots, replies, same-name keys, unapproved identities and hidden/retracted records. Follow CONTRIBUTING.md for query changes: increment the current read-model version (30 at planning), update `read_model_schema_v1.md`, and verify normal startup rebuilds from the prior version before the new query. No new core tables anticipated.
- Verification approach: Focused reader fixtures, count/list parity, prior-version startup regression and isolated `./v3 rebuild`.
- Risks or open questions: Historical 1/1 counts may reflect ineligible content; test authored eligibility rather than preserving unexplained totals. Resolve visibility ambiguity before Stage 3.
- Canonical components/API contracts touched: AuthoredContentRepository, ProfileRepository consumers, ReadModelMetadata and profile/read-model specifications.
- Estimate details: Stage work; driver: visibility and rebuild compatibility; uncertainty: existing thread/post predicate differences.

## Stage 2 — Public key on the normal profile

**Estimate: 0.12**

- Goal: Restore direct key inspection without overwhelming the initial profile.
- Dependencies: Stage 1 committed; shared progressive-disclosure guide.
- Expected changes: Wire a labeled collapsed key block and Copy behavior into standard profile rendering, reusing existing display conventions and supported clipboard handling. Keep manual selection, unavailable feedback and existing Tools links.
- Verification approach: Normal profile route, exact key text, keyboard expansion, clipboard failure/manual selection and long-key overflow; check Account/Tools/Forte/bootstrap key consumers and revise obsolete key-absence assertions.
- Risks or open questions: Do not couple the public viewer to browser-private-key state or duplicate unrelated account controls; validate script loading on public reads.
- Canonical components/API contracts touched: Application profile render data/scripts, profile template, existing key-viewer styles and copy conventions, UserAccessTest and profile presentation checks.
- Estimate details: Stage work; driver: reusable display/copy behavior; uncertainty: safe reuse without account-specific dependencies.

## Stage 3 — Bounded profile history and recovery

**Estimate: 0.22**

- Goal: Browse the identity's threads/posts with accurate totals at its existing URL.
- Dependencies: Stages 1–2 committed; eligibility established.
- Expected changes: Extend the reader with bounded identity-page queries and integrate controller query handling, profile lists and shared pagination. Proposed reader interface: `visibleIdentityPage(PDO $pdo, string $identityId, string $kind, ?array $cursor, int $limit): array`; retain existing aggregate interfaces. Use separate list positions with identity/kind/order context; preserve the other list's position in links. Share existing title/meta presentation. Keep supplementary username navigation distinct.
- Verification approach: Approved/unapproved and shared-name routes; first/middle/last/empty pages, ties, arrivals/deletions, malformed/cross-identity positions, reload/history and JavaScript-disabled links; verify full totals and bounded result fetching.
- Risks or open questions: Cursor recovery must not silently substitute page one; check isolation and return navigation before host integration.
- Canonical components/API contracts touched: AuthoredContentRepository, ProfilePageController, Application profile-render closure, profile template, PageNavigation and pagination partial.
- Estimate details: Stage work; driver: two independently paginated lists and recovery; uncertainty: render-closure/shared-caller wiring.

## Stage 4 — Host/static delivery compatibility

**Estimate: 0.14**

- Goal: Preserve the same profile and history behavior through ordinary serving paths.
- Dependencies: Stage 3 committed.
- Expected changes: Integrate profile history query parameters with existing FrontController/high-traffic/static routing and profile materialization. Preserve identity query context and existing refresh/authorization behavior; never strip a position to serve a first-page artifact. Adjust offline handling only where necessary to preserve the existing supported route contract.
- Verification approach: Direct versus actual HTTP host/static first/older/error URLs, ordinary refresh/rebuild and links back to the profile; focused high-traffic/static and affected offline checks.
- Risks or open questions: Query routing and generated pages may diverge; inspect generated artifacts and response bodies, not just route recognition. Do not add publication guards or new freshness policy.
- Canonical components/API contracts touched: Host FrontController, HighTrafficRoute/cache consumers, StaticArtifactBuilder, affected profile/offline route handling.
- Estimate details: Stage work; driver: serving-path parity; uncertainty: existing query/cache admission behavior.

## Stage 5 — Acceptance and documentation

**Estimate: 0.16**

- Goal: Complete shared acceptance, record limits and provide the named-host replay.
- Dependencies: Stages 1–4 committed and focused checks passing.
- Expected changes: Update profile contract, implementation summary and study ledger; record browser/theme evidence and production handoff. Replay the reported unapproved-profile mismatch with isolated equivalent fixtures, then document the same zenmemes.com URL's post-deployment check without modifying production data.
- Verification approach: Real browser phone/desktop checks across affected themes; collapsed/expanded, empty/unavailable, long content, copy failure and pagination recovery. Verify username, Tools, Forte/detail APIs, post source links and messaging entry compatibility; run focused PHP/browser suites and final rebuild/normal-route checks. On zenmemes.com after authorized deployment, identify the content underlying its counts, confirm key text and eligible list/count parity, follow contributions and exercise available history/recovery. Mark host acceptance pending until this evidence exists.
- Risks or open questions: Local fixtures do not establish production repair; retain pending deployment/physical-device/full-suite limits explicitly and provide Reload/Back, First page and manual-selection recovery instructions in the handoff.
- Canonical components/API contracts touched: Profile/theme/UserAccess regression checks, normal HTTP/browser harnesses, profile spec, this cycle's summary/debug log and effort ledger.
- Estimate details: Stage work; driver: shared cross-theme/end-to-end acceptance; uncertainty: available browser/host fixtures. This stage owns shared acceptance and closeout, not repeated planning overhead.

## Execution and study checkpoint

After Approved Step 3, create a feature branch, organize this cycle's plans/debug log under `docs/plans/profile_public_details/`, repair links/index and commit approved planning files before implementation. Create Step 4 summary afterward; commit each completed stage with its summary before the next. Keep ledger updates for closeout under the study's commit boundary. Stages S1–S5/v1 are atomic review units; split an oversized stage before work and replan changed requirements. Zero dependency delta proposed; no merge, push or deployment authorized.

Checkpoint: 2026-10-27T01:55:37Z; v1/A outcome, original baseline 93c3ea8132df45fd5b334f05758a144fdb90e78e, current source d28ea9165; R1/P2 exact frozen hashes verified; Codex single exposed rater. Whole-cycle projection revised from 0.50 to 0.60 for newly explicit schema-startup and dual-pagination/host verification obligations, not new user scope. Prior 0.50 snapshots retained. S1–S5 first stage-work estimates are 0.18/0.12/0.22/0.14/0.16, separately judged and non-additive; cycle total includes all investigation/planning/verification/documentation. No measured labor or accepted outcome. Await Approved Step 3.

*— Codex · 2026-10-27 UTC*
