> **Feature plan:** [Step 1](./high_traffic_mode_step1_solution_assessment.md) · [Step 2](./high_traffic_mode_step2_feature_description.md) · [Step 3](./high_traffic_mode_step3_development_plan.md) · [Step 4](./high_traffic_mode_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Repeated public-page requests consume PHP capacity even when slightly stale shared HTML would suffice. Provide an operator-controlled mode that serves warm pages directly through Apache while preserving live authenticated operations and reliable recovery.

## User stories

- As a sysop, I want to enable or disable high-traffic mode through operator controls or CLI so that I can protect application capacity and restore ordinary routing.
- As a visitor with an initialized identity/session, I want public pages to load quickly and my authenticated actions to keep working.
- As a sysop, I want popular pages refreshed in the background so that routine updates usually appear within one to two minutes without deliberate cache gaps.

## Core requirements

- One reusable switching service applies normal/high-traffic routing through a dedicated `.htaccess` writer, preserving unrelated hosting rules. Expose effective mode, reason, and failures. Public instances only; disabling precedes private access. Keep operator controls live and provide CLI recovery. Future automation uses this boundary.
- Apache serves shared HTML for an explicit public-route allowlist, including session-bearing GET/HEAD requests; HEAD has no body. Initially exclude query strings and Authorization headers. Private/account/API/write routes retain live authorization. Shared artifacts contain no viewer data, tokens, or session headers; retain existing client-side identity behavior and use neutral placeholders for any identity-specific UI.
- First misses generate on demand. Coordinate one generator per page across requests and cron, cap total generation/admission/storage, and bound waiting. With no cached page or available capacity, respond temporarily unavailable with retry guidance. Publish complete replacements atomically; reject obsolete work after invalidation, deployment, or mode changes. Withdraw restricted/removed content immediately; block direct cache-storage access.
- Cron ranks eligible routes from the latest configurable X access-log lines, deduplicates work, skips fresh pages, and refreshes within a bounded run while old eligible HTML remains available. Sysop owns log operations and host traffic limits. Target one-to-two-minute freshness under supported load; retain eligible stale pages on refresh failure. Embed a UTC generation timestamp; stale pages may request bounded, deduplicated low-priority regeneration, with server-side freshness verification.
- Preserve working files on failed writes; record failures, clean abandoned temporary files, and support manual refresh/purge and normal-mode recovery. Retain assets used by stale HTML and avoid browser/service-worker caching that defeats refresh or withdrawal.

## Delivery scope and completion boundary

**Work type:** application change. Deliver manual enable → cold generation → Apache warm hit → popularity-driven refresh → disable/recovery for a small public-route set, using existing rendering. Step 3 must name those routes and concrete limits. Automatic traffic/load measurement and activation, automatic deactivation, and broader failure dashboards/alerts/repair are deferred follow-ups; no new navigation destination or identity feature is required. Release requires actual Apache verification, existing authenticated-flow checks, and an operator runbook; implementation remains gated by Step 3 approval.

## Risks

- **Access/publication races:** could expose withdrawn content. Validate configuration and invalidation entry points before dependent planning; require coordinated switching/publication and public-to-private race tests.
- **Shared rendering/session compatibility:** could leak viewer state or break authenticated actions. Inspect representative rendering and identity initialization early; use shared output and separately authorized live behavior, blocking unsuitable routes.
- **Host/writer compatibility:** permissions or invalid rules could break routing. Establish supported Apache/log configuration and validation/restore procedure before writer planning; preserve unrelated rules and test failed switches.
- **Capacity/freshness:** log popularity can starve pages and cold floods can exhaust workers. Establish bounded defaults and an Apache load-check scenario in Step 3; cap work, serve eligible stale HTML, and document sysop controls and freshness limits.

## Shared component inventory

Extend existing Tools Feature Flags UI/forms and `/api/set_feature_flag` handling only as appropriate for operator control, using effective local routing state rather than treating a persisted flag as proof of activation. Extend `v3` operator commands through the same new switching service. Reuse `FrontController`, `Application` and public templates for route/render behavior; adapt rather than inherit their cookie-free static eligibility. Reuse/extend `StaticArtifactBuilder`, `StaticArtifactReleasePublisher`, and `StaticArtifactInvalidator` where their contracts fit. Reuse existing task-queue/cron entry points for refresh scheduling where suitable; add bounded recent-log sampling and stale-page refresh intake. Existing asset delivery, service worker, identity initialization, and authenticated APIs require compatibility verification, not parallel replacements.

## Simple user flow

1. Sysop configures the log sample and refresh schedule, enables the mode, and checks effective state.
2. Visitor opens an eligible public URL; the first miss generates shared HTML and subsequent hits are served by Apache, including with a session cookie.
3. Cron replaces popular stale pages; timestamp-driven requests enqueue lower-priority refresh work without blocking page display.
4. Sysop can refresh/purge or disable; failed refreshes preserve eligible pages and failed switches offer actionable recovery.

## Success criteria

Actual Apache checks demonstrate zero PHP invocation on eligible warm hits, complete old-or-new HTML throughout refresh, session-bearing reuse, correct HEAD/bypass behavior, bounded concurrent cold work, and no obsolete publication after invalidation. Under the defined refresh workload, routine changes appear within two minutes of source availability. Operator UI and CLI agree with applied routing; private transitions and recovery prevent cache access; affected phone/desktop UI and authenticated actions pass relevant repository checks.

## Approval and follow-ups

Step 1 approved by the user. All refinements recorded there are carried forward. Automatic activation and broader failure management remain separate proposed cycles; no implementation or follow-up cycle has started.
