> **Feature plan:** [Step 1](./member_directory_access_split_step1_solution_assessment.md) · [Step 2](./member_directory_access_split_step2_feature_description.md) · [Step 3](./member_directory_access_split_step3_development_plan.md) · [Step 4](./member_directory_access_split_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- Deliver Users → member → contributions/messaging and Tools → User Access → identity review → approval → confirmed outcome on standard pages. Forte retains its combined directory/access presentation, filters, detail API, and key information. Preserve public evidence, authorization, target-specific retry, empty/missing states, legacy links, and static freshness.
- Verify through local application/host requests and browser journeys at desktop/mobile widths; report unavailable checks explicitly. No production deployment or external-service changes are included. Release requires focused regressions passing, copy checklist updated, and remaining limitations disclosed.

## Key Risks

- **High risk:** Wrong-key approval. Existing controls carry profile slugs and server policy resolves identities; validate multi-key usernames before dependent approval changes, retaining authoritative signing/policy checks.
- **High risk:** Permission or stale-content regression. Queue currently requires an approved viewer while history is public; verify an anonymous/unapproved/approved access matrix and host/static behavior before exposing Tools views. Keep restricted views dynamic.
- Lost recovery/history navigation. Pagination and signing callbacks currently point at member pages; map and retarget them, retaining legacy paths before removing standard-page sections. Preserve Forte defaults in shared components and check its history links and pending selection before dependent changes. No schema changes; revert this feature's commits and regenerate affected artifacts if rollback is needed.

## Stage 1 — Account history through Tools

- Goal: Browse existing accounts and key history through Tools → User Access.
- Dependencies: Step 3 approval, feature branch, organized and committed approved plans.
- Expected changes: Add Tools account list/detail destinations using existing profile/history data and public visibility; keep the restricted queue separate.
- Verification approach: Navigate Tools to accounts with multiple keys; check evidence, history pagination, empty/missing records, and anonymous visibility.
- Risks or open questions: Validate current visibility before wiring routes; block dependent work on mismatches.
- Canonical components/API contracts touched: Tools registry/nav, application routing, ProfileRepository, history service/partial with caller-specific navigation preserving Forte behavior, new management controller/views.

## Stage 2 — Identity review and approval

- Goal: Review and approve an exact pending identity within Tools.
- Dependencies: Stage 1 committed; identity and visibility checks passed.
- Expected changes: Add restricted pending queue and profile review; reuse policy consequences, signed actions, and identity-specific success/retry. Return Tools approvals to Tools using caller-specific navigation; preserve existing shared callers.
- Verification approach: Exercise new account and additional-key approval, rejected/cancelled signing, policy denial, duplicate submission, and retry; confirm target and resulting history.
- Risks or open questions: Callback currently targets `/profiles/`; preserve legacy submissions and validate review against server preparation before confirming.
- Canonical components/API contracts touched: Pending queue, profile review, pending_approvals/browser signing, existing preparation/finalization APIs and profile approval handler.

## Stage 3 — Standard member browsing

- Goal: Separate standard Users and public subpages from management.
- Dependencies: Stage 2 committed with working replacement journey.
- Expected changes: Remove queue links, key inventories/history, diagnostics, and approval controls; retain activity, messaging, status/attribution and contextual Tools links. Forward legacy pending routes; provide history-fragment/pagination compatibility.
- Verification approach: Browse directory/username/profile, send an existing supported message, follow old pending/history links and approval feedback; check member-only pending usernames.
- Risks or open questions: URL fragments never reach the server; retain a visible history-anchor link to Tools rather than rely on server fragment detection.
- Canonical components/API contracts touched: ProfilePageController, public templates, shared history links, legacy routing/callback notices.

## Stage 4 — Serving consistency and release verification

- Goal: Confirm both journeys through real serving paths and finalize concise copy.
- Dependencies: Stage 3 committed.
- Expected changes: Align host/member-only route handling, static generation/invalidation and compatibility; update UI copy audit checklist and focused regressions.
- Verification approach: Run relevant PHP checks and approval/browser suites; replay both complete journeys, authorization matrix, legacy links, history pagination and post-approval refresh through direct and host/static paths. Inspect desktop/mobile UI; confirm Forte pending filters, detail API, key history, pagination and profile links still work.
- Risks or open questions: Stale artifacts can preserve removed controls; validate regeneration/invalidation and restricted-route bypass before release.
- Canonical components/API contracts touched: FrontController, StaticArtifactBuilder/Invalidator, route allowlists, existing test harness and approval suites, UI copy checklist.

## Execution and approval

- Four stages, targeted at at most one hour each; stop and revise if a stage exceeds that boundary. Complete applicable checks and commit each stage with its implementation-summary update before the next. Initial planning commit contains approved plans and required organization only; exclude pre-existing todo edits.
- Request: Exclude Forte because its technical audience can retain combined views. Disposition: Scope reduced in revised Step 2, approved by the user; this plan removes Forte implementation and retains compatibility checks only. Outcome: No Forte redesign in this cycle.
- Awaiting `Approved Step 3` for this revised plan.
