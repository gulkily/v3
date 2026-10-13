> **Feature plan:** [Step 1](./case_insensitive_tags_step1_solution_assessment.md) · [Step 2](./case_insensitive_tags_step2_feature_description.md) · [Step 3](./case_insensitive_tags_step3_development_plan.md) · [Step 4](./case_insensitive_tags_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Entry/outcome: `/tags/`, mixed-case saved links, and Forte filters reach one lowercase group containing each eligible thread once; supported offline browsing matches its snapshot.
- Recovery: preserve unknown-tag, empty/missing-snapshot, back-navigation, visibility, and access behavior. Revert code and regenerate artifacts for rollback; no stored-record or schema changes.
- Release: verify dynamic, static, Forte, and offline navigation locally, including worker activation and static regeneration. No external service or production deployment is required; record verification evidence and release/rollback commands in Step 4.

## Key Risks

- **High risk:** duplicate membership inflates counts. Validate overlapping board tags/labels first; deduplicate before previews and counts.
- **High risk:** route changes could bypass access rules. Review found application/front-controller gates first; accept uppercase tag characters only, preserving surrounding route and access contracts.
- **High risk:** stale or inconsistent consumers break navigation. Inspection found separate Forte row metadata, offline grouping/worker matching, and static route enumeration; verify each before release and regenerate artifacts. Unresolved risks block dependent work.

## Stage 1

- Goal: complete case-insensitive online directory-to-thread browsing (about one hour).
- Dependencies: Step 3 approval; feature branch, organized plans/index, and planning commit.
- Expected changes: merge ASCII case variants, canonicalize group names/links/headings, accept mixed-case tag routes in dispatch and route classification; preserve ordering and five-thread previews.
- Verification approach: extend `LocalAppSmokeTest` and `PrivateSiteAuthTest` for overlapping variants, six-plus unique threads, both slash forms, missing tags, hidden content, and access outcomes.
- Risks or open questions: inflated counts/access drift; validate fixtures and gates before editing, normalize only browsing comparisons.
- Canonical components/API contracts touched: `TagGrouping`, `TagsPageController`, `Application`; reuse tag templates/thread cards. No signature changes planned.

## Stage 2

- Goal: keep Forte selection and visible rows consistent (about one hour).
- Dependencies: Stage 1 verified and committed with its summary.
- Expected changes: align requested selections, canonical groups, row tag metadata, and browser filtering without altering reaction behavior.
- Verification approach: extend `ForteBoardReaderTest` and relevant application smoke tests; exercise mixed-case saved filters, filter switching, selected threads, and back/forward navigation.
- Risks or open questions: canonical groups could hide mixed-case rows; compare server and browser membership before proceeding.
- Canonical components/API contracts touched: `ForteBoardController`, paned board thread-list partial, `paned_board_reader.js`; reuse existing controls.

## Stage 3

- Goal: complete equivalent offline directory-to-thread navigation (about one hour).
- Dependencies: Stage 2 verified and committed with its summary.
- Expected changes: align snapshot grouping, lookup/headings, path parsing, and service-worker eligibility; retain missing-snapshot recovery.
- Verification approach: extend `OfflineSnapshotPresentationTest` and `OfflineNavigationWorkerTest`; check duplicate variants, previews, mixed-case links, unknown tags, and online/offline transitions in a browser.
- Risks or open questions: old workers could retain lowercase-only routing; validate worker update/activation and deployed asset freshness.
- Canonical components/API contracts touched: `offline_reader.js`, `service_worker.js`; preserve snapshot format and storage.

## Stage 4

- Goal: deliver consistent static pages and release evidence (about one hour).
- Dependencies: Stage 3 verified and committed with its summary.
- Expected changes: canonicalize static tag route enumeration/lookup; regenerate an isolated static release and document rollout/rollback.
- Verification approach: extend static application smoke coverage; verify front-controller mixed-case requests, missing-artifact fallback, and fresh lowercase links. Run affected suites via `php tests/run.php <ClassName>` and record browser/static checks.
- Risks or open questions: stale variants may survive old artifacts; validate fresh release publication and dynamic fallback before handoff.
- Canonical components/API contracts touched: `StaticArtifactBuilder`, `FrontController`, existing publication/invalidation flow; use `v3 build-static` with isolated instance paths.

Every stage includes tests and a summary in its commit before the next begins. Target four implementation commits plus the planning commit; split any stage exceeding one hour before implementation.
