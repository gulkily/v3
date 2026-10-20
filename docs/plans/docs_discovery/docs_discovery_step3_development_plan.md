> **Feature plan:** [Step 1](./docs_discovery_step1_solution_assessment.md) · [Step 2](./docs_discovery_step2_feature_description.md) · [Step 3](./docs_discovery_step3_development_plan.md) · [Step 4](./docs_discovery_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

Tools → Platform Docs must support finding all eligible docs, opening documents/assets and related links, and returning to preserved results on dynamic and static sites. Preserve URLs; missing/stale content offers library recovery and retry. Require inventory parity, safe access and mobile/keyboard acceptance. Verify locally served dynamic and built static releases; production deployment is outside scope. No database changes. Retain the prior static release for rollback.

## Key Risks

- **High risk: exposure/execution.** Inspect example contents and path boundaries first; enforce eligibility and inert assets before publishing.
- **High risk: scale/usability.** Measure full-inventory search/build costs early; bound results, retain paths and distinguish history/workflow without marking every plan obsolete.
- **High risk: publication/rollback.** Compare revision-consistent inventories and exercise removal/rebuild and prior-release recovery; unresolved risks block dependent stages.

## Stage 1 — Browse and open automatically discovered Markdown

- Goal: Reach the complete Markdown library from the existing entry.
- Dependencies: Step 3 approval, feature branch and planning commit.
- Expected changes: Shared inventory, root allowlist, titles/groups, bounded folder browsing and root readers; preserve curated guides and URLs.
- Verification approach: Catalog/page regressions for unique complete coverage, additions, root/history/workflow documents, oversized states and unsafe paths; measure inventory cost.
- Risks or open questions: Inspect examples/sizes first; resolve eligibility and classification before expansion.
- Canonical components/API contracts touched: PlatformDocsCatalog, PlatformDocsController, route dispatch and docs templates.

## Stage 2 — Search and return to results

- Goal: Find documents and preserve search/filter position after reading.
- Dependencies: Stage 1 verified and committed.
- Expected changes: Title/path/heading/body search, category/folder/history filters, current-first ranking, bounded results, counts, clear/reset and empty states.
- Verification approach: Feature/operations/history and body-only queries, combined filters, ranking, return/back journeys; measure repository-scale search.
- Risks or open questions: Validate index cost before static integration; share search behavior across delivery modes.
- Canonical components/API contracts touched: Catalog search metadata, controller/index, shared docs browser asset and reader return navigation.

## Stage 3 — Follow links and safely open assets

- Goal: Reach related documentation/assets and recover from missing content.
- Dependencies: Stage 2 verified/committed; exposure review passed.
- Expected changes: Approved asset viewing/downloads, root/local links and anchors, oversized/unavailable states, missing-document library/retry actions.
- Verification approach: Format coverage, inert HTML, response headers, traversal/encoding/symlink rejection, unrelated root/private-file rejection, oversized/deleted targets and return recovery.
- Risks or open questions: Examples must contain placeholders; inspect before publication and stop for scope review if sensitive content appears.
- Canonical components/API contracts touched: Catalog eligibility/resolution, PublicMarkdownRenderer links, controller/routes and recovery template.

## Stage 4 — Publish the complete searchable library

- Goal: Deliver the same journey from static output.
- Dependencies: Stage 3 verified and committed.
- Expected changes: Shared build inventory snapshot, complete readers/assets/search data, existing URL support and missing-route recovery.
- Verification approach: Extend PlatformDocsStaticTest; build/serve full output, compare inventory, search/read/return, add/delete/rebuild without registration, verify stale removal and prior-release recovery; record duration/size.
- Risks or open questions: Validate actual static host routing/headers; block acceptance on unsafe delivery or missing recovery.
- Canonical components/API contracts touched: StaticArtifactBuilder enumeration/rendering/lookup, existing host/publication and shared search assets.

## Stage 5 — Acceptance and maintenance guidance

- Goal: Establish release evidence and document upkeep.
- Dependencies: Stage 4 verified and committed.
- Expected changes: Acceptance fixes, existing eligibility/publication/recovery guidance and ui_copy_audit_checklist.md updates; favor removing redundant copy.
- Verification approach: Desktop/mobile keyboard journeys from Tools on dynamic/static sites, related links/assets, filtering/reset and stale/missing recovery; focused docs/renderer/navigation regressions and applicable repository checks.
- Risks or open questions: Visually check existing Docs icons in Word97/Chicago/Midnight; change mapping only if necessary. Record failed/blocked checks explicitly.
- Canonical components/API contracts touched: Existing Tools launcher/subnavigation, docs UI, guidance and regression suites.

Stages target about one hour each. Before implementation, organize plans/debug log under docs/plans/docs_discovery/, repair navigation/index references and commit approved plans; create the summary afterward. Each stage requires verification and a commit including its summary update before the next stage.
