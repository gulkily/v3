> **Feature plan:** [Step 1](./root_approval_pages_step1_solution_assessment.md) · [Step 2](./root_approval_pages_step2_feature_description.md) · [Step 3](./root_approval_pages_step3_development_plan.md) · [Step 4](./root_approval_pages_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Classic/Forte account and profile history opens readable root seed evidence, with direct URLs, approved key, recorded reason, available current profile/account links, raw source, and available repository evidence. Ordinary approvals continue opening post pages.
- Unknown authorizer/acceptance time remains unknown. Missing/invalid records show safe errors; private-site access follows existing profile/source rules. Missing profile or Git history does not prevent reading a valid seed.
- Reuse canonical validation and evidence components; no authority, canonical-record, schema, import, Activity, or approval-workflow changes. No production deployment. Complete after focused PHP and desktop/phone checks pass and each stage is committed with its summary.

## Key Risks

- **High risk:** A readable page can imply an invented author or acceptance date. Validate available seed fields first; explicitly separate canonical seed facts, current profile data, and repository provenance.
- **High risk:** New direct URLs could expose private evidence or select an invalid file. Test route access and malformed identities/path mismatches before linking pages; reuse canonical path validation and membership guards.
- Renames, missing profiles, absent Git, or uncommitted seed changes can mislead evidence links. Exercise these cases early; keep current source readable and label/link repository evidence only when verified for that path.

## Stage 1

- Goal: Deliver the history → readable root seed → current profile/raw-source flow.
- Dependencies: Approved Step 3 and existing canonical seed/profile services.
- Expected changes: Add a dedicated root approval page/route using the canonical seed loader; show validated key/reason and explicitly unknown historical facts, plus optional current profile/account links. Update the shared Approval evidence link for root seeds across classic/Forte histories. Handle missing/invalid seeds and apply established private-site access rules.
- Verification approach: Focused route/render tests for existing seeds, filename/identity mismatch, malformed route, missing record/profile, escaped reason text, anonymous/member access on private sites, all history variants, and unchanged ordinary post links. Confirm current raw-source destinations.
- Risks or open questions: Resolve authorization/path validation and false historical attribution here before adding provenance; seed validity must not depend on optional profile data.
- Canonical components/API contracts touched: Application route/access classification, dedicated root approval page handler, CanonicalRecordRepository::loadApprovalSeed, ProfileRepository, shared approval_history partial, themed page components, focused PHP tests. No new public API format.

## Stage 2

- Goal: Complete repository evidence, responsive presentation, and release verification.
- Dependencies: Stage 1 verified and committed.
- Expected changes: Add available seed-specific commit/history evidence through existing source/provenance helpers, clearly labeled as repository history; preserve current-source access when provenance is unavailable or differs from current bytes. Reuse existing evidence presentation and theme variables; finish the Step 4 summary.
- Verification approach: Git-backed fixtures for matching/changed seed content and absent history; follow source/commit links and confirm dates are not labeled acceptance dates. Run canonical seed/history/private-route regressions. Inspect Light, Dark, Console, Chicago, and Word 97 at desktop/phone widths for card spacing, key wrapping, readable text, and no page overflow; check links from classic/Forte account/profile pages.
- Risks or open questions: Resolve unavailable/mismatched provenance without inventing history or replacing canonical facts. Record unrelated baseline failures if encountered; deployment remains outside scope.
- Canonical components/API contracts touched: Existing source URL/commit helpers and source metadata presentation, root approval detail page, page asset wiring, focused PHP/browser fixtures, implementation summary. No database migration or external services.
