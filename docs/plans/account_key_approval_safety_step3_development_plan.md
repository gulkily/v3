> **Feature plan:** [Step 1](./account_key_approval_safety_step1_solution_assessment.md) · [Step 2](./account_key_approval_safety_step2_feature_description.md) · [Step 3](./account_key_approval_safety_step3_development_plan.md) · [Step 4](./account_key_approval_safety_step4_implementation_summary.md)

## Completion Contract

- Settings → explicit approval/invitation review → grant/denial → account/operator history. Support stale-request refresh/review/retry; distinguish committed-but-unrefreshed success from failed writes.
- Default allows cross-account approval; historical grants survive toggles/rebuilds. Reuse existing projections plus portable evidence; no database migration planned.
- Six stages estimated at ≤1 hour each; resolve risks before dependents. Release requires all checks below and deployment/rollback instructions. Production changes require separate authorization. Exceeding eight stages/day requires rescoping.

## Key Risks

- **High risk: unauthorized grants.** Generic posts accept protocol tags; validate signatures/issuers/seeds/races early; reserve protocol writes.
- **High risk: false history/access loss.** Preparation predates acceptance; invitation reconstruction uses today's clock. Validate delay/expiry fixtures early; record acceptance and retain legacy uncertainty.
- **High risk: disclosure/rollback.** Enumerate page/API/feed/cache entries before visibility work; authenticate operators. Older code/permissive flags can reopen access.

## Stage 1

- Goal: Operator-selected restriction works across grant paths.
- Dependencies: Approved Step 3.
- Expected changes: Default-enabled flag; shared `ApprovalPolicy::evaluate(actorId, targetId): array`; locked direct/manual/invitation enforcement. Root seeds remain explicit administration.
- Verification approach: Both flag values, role/username matrix, forged protocol posts, concurrent claims, stale-index rejection/recovery.
- Risks or open questions: Bypass impact; inspect all writers first; reserve protocol writes and verify root seeds, never labels.
- Canonical components/API contracts touched: FeatureFlags, LocalWriteService, approval/invitation APIs, generic posting APIs, CLI.

## Stage 2

- Goal: Users review the actual account-access consequence.
- Dependencies: Stage 1.
- Expected changes: Three approved labels/count warning; signed context and renewed review after changes. Invitations explain scope; redemption checks target and issuer.
- Verification approach: Mobile/desktop approval, denial, expiry, target substitution, changed flag/count, retry.
- Risks or open questions: Misleading consent; compare preparation/finalization early; invalidate changed context instead of silently broadening authorization.
- Canonical components/API contracts touched: Pending/profile controls, browser signing, invitation UI, prepare/finalize payloads.

## Stage 3

- Goal: Every new grant has durable acceptance evidence.
- Dependencies: Stages 1–2.
- Expected changes: Server-owned receipt bound to grant bytes, committed together without altering signatures; acceptance time/context. Shared invitation replay preserves accepted grants after expiry.
- Verification approach: Delayed acceptance, post-expiry rebuild, tampered receipt, failed commit, duplicate retry, import round-trip.
- Risks or open questions: Fabricated/partial evidence; inject failures first; validate bindings, clean incomplete writes, preserve committed success and legacy unknowns.
- Canonical components/API contracts touched: `ApprovalAcceptance::read/encode`, writer, ArchiveRecordCatalog/import/export, full/incremental approval derivation. Imports remain trusted repository administration.

## Stage 4

- Goal: Account holders inspect grants and evidence.
- Dependencies: Stage 3.
- Expected changes: Shared `ApprovalHistory::forUsername(token): array`; bounded lookups; classic/Forte history, consistent summaries/API/audit; invalidate username/profile/activity artifacts.
- Verification approach: Direct/invitation/operator history, unknown legacy attribution, pagination/scale, refresh, rebuild parity.
- Risks or open questions: False certainty/stale views; compare audit fixtures early; preserve unknowns and acceptance facts independently of current counts.
- Canonical components/API contracts touched: ProfileRepository, profile/username views and API, summary partials, ApprovalAudit, StaticArtifactInvalidator.

## Stage 5

- Goal: Operators correlate grants and policy changes.
- Dependencies: Stages 3–4.
- Expected changes: Classic/Forte activity filter joins grants/flag changes; authenticate root-seeded viewers across pages/APIs/feeds; exclude restricted static/offline publication.
- Verification approach: Enable/approve/disable sequence; anonymous, member, forged identity hint, operator; direct URLs/feed/cache requests.
- Risks or open questions: Access leakage; enumerate alternate entries first; shared authenticated authorization and no-store restricted responses.
- Canonical components/API contracts touched: ActivityService/controllers/templates, flag history, route authorization, static/offline publishers.

## Stage 6

- Goal: Verify and document the releasable outcome.
- Dependencies: Stages 1–5.
- Expected changes: Operator instructions, acceptance-evidence contract, deployment/rebuild checks, rollback limits, Step 4 summary with stage commit evidence.
- Verification approach: Targeted regressions, complete permission matrix, browser flows, repeated rebuild/archive round-trips, cached surfaces; local deployment smoke.
- Risks or open questions: Older code bypasses restriction; validate compatibility before release; preserve records and disable approval writes during unsafe rollback.
- Canonical components/API contracts touched: Tests/browser fixtures, README/spec/runbook, v3 audit, Step 4 summary.
