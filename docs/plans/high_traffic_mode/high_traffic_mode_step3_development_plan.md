> **Feature plan:** [Step 1](./high_traffic_mode_step1_solution_assessment.md) · [Step 2](./high_traffic_mode_step2_feature_description.md) · [Step 3](./high_traffic_mode_step3_development_plan.md) · [Step 4](./high_traffic_mode_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

Deliver operator UI/CLI enable → bounded first generation → session-bearing Apache warm hit → atomic popularity-driven cron refresh → disable/recovery. Initial routes: `/`, `/threads/`, `/threads/{visible-thread-id}/`, `/about/`; existing aliases redirect through PHP to these canonical URLs. Other profiles/routes, queries, Authorization headers, and non-GET/HEAD requests bypass. Preserve authenticated actions and client-side identity behavior. Verify on isolated Apache 2.4 with rewrite/headers support, PHP, local-filesystem atomic rename/locks, and the repository's browser harness; production enablement additionally requires sysop-confirmed permissions, log format/path, cron, and host traffic controls. No production deployment is authorized here. No read-model schema change is planned. Deferred: automatic activation/deactivation and broader failure management.

Proposed configurable defaults: latest 10,000 log lines (16 MiB read ceiling, 16 KiB line ceiling); freshness threshold 60 seconds; cron every minute, at most 50 renders/45 seconds per run; two concurrent generators globally, one per canonical page; cold wait at most 250 ms then 503 with Retry-After: 5; at most 1,000 cached pages/256 MiB and 1 MiB per HTML file; at most 1,000 pending refresh routes and one accepted client refresh hint per route per minute. Popularity work has priority, with up to five render slots per run reserved for low-priority work. Limits apply across processes; reject excess work without unbounded fallback. Supported freshness check: 20 eligible pages requiring at most one second each refresh, with updates visible within 120 seconds. These are acceptance fixtures, not production capacity claims.

## Key Risks

- **High risk: access/data races.** Inspect all flag/import/retraction/config paths first; reuse publication guards and a fixed lock order. Block dependent activation until invalidation and private transitions cannot republish or serve withdrawn files. Out-of-band configuration/deployment must disable routing first; PHP cannot detect such changes on Apache hits.
- **High risk: rollback/routing.** Validate a candidate against a representative Apache fixture and preserve custom rules; fail activation on unsupported ownership/permissions. Keep CLI/restorable normal routing available if web requests fail.
- **High risk: shared UI and capacity.** Compare two session-bearing clients and existing signed actions before allowlisting; reuse shared rendering. Enforce the stated limits and verify concurrency/freshness with real Apache. Browser/service-worker caching must revalidate HTML; host worker/rate limits remain sysop-owned.

## Stage 1 — Shared page generation

- Goal: generate the initial public-route set through the existing CLI rendering path.
- Dependencies: approved plan; inspect route visibility and existing session/HTML behavior first.
- Expected changes: canonical allowlist, isolated shared render context, machine-readable UTC timestamp and generation identity; reuse single-route rendering and retain referenced assets.
- Verification approach: two distinct session inputs produce shared artifacts without viewer/session data; errors/redirects are not published; aliases and HEAD mapping are explicit.
- Risks or open questions: unexpected personalization blocks the affected route; resolve before routing work.
- Canonical components/API contracts touched: `StaticArtifactBuilder`, `Application`, public templates, fingerprint assets; define shared route/metadata contract.

## Stage 2 — Bounded cold path

- Goal: complete miss → coordinated generation → reusable file through PHP before enabling Apache bypass.
- Dependencies: Stage 1.
- Expected changes: dedicated high-traffic cache storage, per-page/global admission, bounded wait/storage, atomic replacement and refresh hints metadata; cache-write failure may still return successfully rendered HTML.
- Verification approach: concurrent same/distinct misses obey limits; capacity returns 503/Retry-After; readers see only complete HTML; errors and abandoned work release resources.
- Risks or open questions: locks must cover cron/request processes without holding session locks while waiting; validate under parallel workers.
- Canonical components/API contracts touched: `FrontController`, shared renderer, execution locks; one generation entry point for all callers.

## Stage 3 — Withdrawal and generation safety

- Goal: distinguish ordinary stale-tolerant writes from immediate withdrawals and reject obsolete publication.
- Dependencies: Stage 2; audit existing invalidation/write/import paths before changes.
- Expected changes: integrate cache with retraction/config/publication guards; routine edits mark refresh work without unlinking cached HTML; restriction/removal/feature changes withdraw affected output before becoming effective; mode/deployment generations fence old work.
- Verification approach: race a renderer with edit, removal, purge, flag change, and private transition; prove forbidden content is withdrawn and cannot reappear.
- Risks or open questions: incomplete invalidation coverage blocks activation; reuse strict guards rather than best-effort unlink behavior.
- Canonical components/API contracts touched: `StaticArtifactInvalidator`, `PublicationGuard`, `LocalWriteService`, configuration/import paths.

## Stage 4 — Apache switching and CLI recovery

- Goal: enable direct warm serving and restore normal routing from CLI.
- Dependencies: Stage 3 safety evidence.
- Expected changes: idempotent serialized switch/status service and managed `.htaccess` section; preserve custom rules, block storage URLs, coordinate feature/private transitions; CLI enable/disable/status/refresh/purge, candidate validation, backup/restore.
- Verification approach: isolated Apache proves zero PHP invocation on warm session-bearing hits, correct headers/HEAD/bypasses/storage blocking; failed write/invalid candidate preserves routing; CLI restores after a broken-file fixture.
- Risks or open questions: Apache syntax-check alone may not validate distributed config; use actual request probes and refuse unsupported layouts. Out-of-band private config requires documented disable-first operation.
- Canonical components/API contracts touched: `public/.htaccess`, `v3`, flag/config mutation entry points; local effective mode/reason/result independent of canonical flag replication.

## Stage 5 — Popularity refresh

- Goal: cron refreshes pages without deleting the serving copy.
- Dependencies: Stage 4.
- Expected changes: bounded latest-X-lines reader for documented Combined/Common access-log input, canonical route counts, freshness skips, deduplicated priority work, bounded cron execution and cleanup; reuse generation limits and existing task/cron entry points where suitable.
- Verification approach: sample ranking, malformed/oversized inputs, missing log, duplicate runs, storage limits; old HTML remains during refresh and after failure; 20-page freshness fixture meets 120 seconds.
- Risks or open questions: low traffic may leave insufficient samples; preserve pending hints and existing eligible pages, report unreadable logs; sysop manages log lifecycle.
- Canonical components/API contracts touched: `v3` cron/task worker integration, cache metadata and shared generation contract.

## Stage 6 — Timestamp refresh hints

- Goal: a displayed stale artifact can request low-priority regeneration without delaying display.
- Dependencies: Stage 5.
- Expected changes: lightweight client timestamp check and live refresh-hint endpoint; verify current server age, normalize route, rate-limit/deduplicate, schedule within reserved low-priority capacity; retain neutral identity placeholders and existing live session behavior.
- Verification approach: stale-client hints for already-current files do nothing; flooding cannot exceed admission; fresh/malformed/withdrawn routes rejected; two-session browser checks preserve existing authenticated actions.
- Risks or open questions: background PHP requests can erode savings; only stale pages hint, bound retries, no polling loop.
- Canonical components/API contracts touched: shared browser bootstrap, timestamp contract, live refresh API and cron queue.

## Stage 7 — Operator control

- Goal: authenticated sysop can toggle and recover from the existing Tools interface.
- Dependencies: Stage 6.
- Expected changes: extend existing Feature Flags tools surface with instance-local mode control/status and existing operator authorization, using the CLI's switching service; show failed/pending actions and recovery, no new navigation destination.
- Verification approach: phone/desktop initial, enabled, disabled, pending and failure states across affected shared themes; keyboard, overflow and signed authorization checks; UI state agrees with Apache/CLI.
- Risks or open questions: canonical feature flags are replicated and insufficient as local routing truth; keep effective local state authoritative and controls outside cache.
- Canonical components/API contracts touched: Tools controller/template and operator action API; reuse existing signing/authentication, no parallel permission system.

## Stage 8 — Acceptance and operations

- Goal: release-ready local slice with documented sysop setup/recovery.
- Dependencies: Stages 1–7; resolve material failures before closeout.
- Expected changes: operator runbook and relevant reference updates, focused Apache/browser acceptance harness, recorded load/failure results; update diagram if final flow differs.
- Verification approach: combine warm hits, edits, cron, hints, cold floods, withdrawals, disable/private transitions and failed writes; check asset retention/service-worker refresh and existing auth regressions. Record passed/failed/blocked checks, production limitations, and rollback replay; run relevant repository checks.
- Risks or open questions: no production capacity claim from a local fixture; document configurable defaults and sysop host limits. Any stage exceeding about one hour requires a split/review before dependent work; if total scope exceeds eight stages/a day, return to Step 2.
- Canonical components/API contracts touched: existing Apache/browser fixtures, operator documentation, feature implementation summary.

## Approval and artifact boundary

Step 2 approved; diagram/source are review aids, not additional application scope. Keep all drafts uncommitted. After explicit Step 3 approval, establish the feature branch and organize/commit approved plans plus required index/reference fixes and this feature's debug log before implementation. Diagram review aids remain outside that narrow initial planning commit; handle them with the approved Stage 8 documentation. Each completed implementation stage must be verified, summarized, and committed before the next. No automatic-mode or broader failure-management cycle is started.
