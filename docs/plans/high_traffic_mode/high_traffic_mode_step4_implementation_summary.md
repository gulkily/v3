> **Feature plan:** [Step 1](./high_traffic_mode_step1_solution_assessment.md) · [Step 2](./high_traffic_mode_step2_feature_description.md) · [Step 3](./high_traffic_mode_step3_development_plan.md) · [Step 4](./high_traffic_mode_step4_implementation_summary.md)

Debug mode: enabled

Branch: `feature/high-traffic-mode`. Planning commit: `78732a6a`.

## Stage 1 - Shared page generation

- Changes: shared route allowlist and isolated-render contract extending the existing static renderer, with embedded UTC timestamp and referenced asset preservation.
- Verification: `php tests/run.php HighTrafficTest` passed (route rejection, isolated real rendering with UTC timestamp, nonexistent-thread rejection); renderer PHP lint passed.
- Notes: CLI renderer process has no request/session globals. Actual Apache/browser checks remain pending later stages. No schema change.

## Stage 2 - Bounded cold path

- Changes: isolated bounded renderer subprocess, per-page coordination with a bounded lock-file set, global generation slots, cache page/byte limits, atomic publication, and PHP miss handling with a bounded wait/503 response. Existing files remain readable during refresh.
- Verification: `php tests/run.php HighTrafficTest` passed 2 tests, including real generation, held global-capacity behavior, warm reuse, and rejected over-capacity publication retaining existing HTML. PHP lint passed.
- Notes: generation fencing/private-withdrawal wiring follows in Stage 3; no Apache bypass is installed yet. Renderer runtime capped at 30 seconds; application limits complement sysop worker/rate controls. Stage 1 commit: `b3826102`.

## Stage 3 - Withdrawal and generation safety

- Changes: canonical write hooks fence older renderers while preserving routine cached pages; feature/access/retraction writes retire the serving symlink before publication. Strict artifact invalidation retires the bypass. Import, archive and record-deletion paths retire it before destructive changes. Render publication rechecks mode, retractions/read-model suppression and Fastmod classification under existing publication guards.
- Verification: focused HighTraffic tests passed (routine retention and feature restriction withdrawal); RetractionSweep tests passed 16 cases including independent publisher serialization and stale-model rejection. FeatureFlagEvaluator passed 17 tests; ContentImportRecovery/Planner passed 12 tests. Broader ImportedContentPublication check: private-destination test passed; full-publication test failed because this summary was edited during its documentation snapshot, so a stable-doc rerun is pending Stage 8 (test interference, not a product assertion failure).
- Notes: external configuration/deployment changes require sysop disable-first procedure because Apache hits cannot observe PHP-only changes. Rendering and provider work remain outside publication locks. Stage 2 commit: `b354f4d8`.

## Stage 4 - Apache switching and CLI recovery

- Changes: shared local mode/status service; managed atomic rewrite block, cache symlink and normal-routing backup; CLI enable/disable/status/refresh/purge/restore; session-bearing warm hits, HEAD and bypass rules; retained fingerprinted asset delivery. The actual repository public routing is not enabled during development.
- Verification: isolated `python tests/high_traffic_apache_test.py` passed: no PHP invocation for two session-bearing warm requests/HEAD, query/Authorization bypass, direct storage rejection, custom rule retention, disable, private-instance refusal and restore after invalid Apache configuration. `php tests/run.php HighTrafficTest WebServerRoutingTest` passed 5 tests. PHP lint passed.
- Notes: isolated Apache runs in its own process session after correcting the harness's termination behavior. Host support/AllowOverride and request probes must be verified by the sysop before production activation; candidate templates have real Apache coverage, not syntax-only coverage. Stage 3 commit: `0362a6ff`.

## Stage 5 - Popularity refresh

- Changes: bounded end-of-file access-log sampling (no cursor/rotation management), eligible route popularity ranking, fresh-page skips, deduplicated bounded cron with reserved low-priority capacity, atomic refresh and abandoned-temp cleanup. Active-mode aliases redirect to canonical cache URLs. CLI `high-traffic cron [access.log]` is the refresh entry point.
- Verification: focused PHP tests passed 4 cases including recent-line/byte caps and popularity ranking. Isolated Apache acceptance passed with a forced-stale page refreshed by the real cron command, preserving direct warm serving. Larger 20-page/timing and concurrency evidence follows in Stage 8.
- Notes: sysop owns cron scheduling and log operations; missing logs are reported while pending low-priority work can continue. Freshness is load-dependent. Stage 4 commit: `92993126`.

## Stage 6 - Timestamp refresh hints

- Changes: fingerprinted deferred client checks embedded UTC age and sends one low-priority hint without identity dependencies or polling; live no-store endpoint admits only canonical stale existing pages, rejects cross-site requests, deduplicates and bounds queue growth. Cron verifies server freshness before work.
- Verification: JS syntax and PHP lint passed. HighTraffic tests passed 4 cases including fresh/stale/duplicate/ineligible hints and refreshed-file suppression; isolated Apache regression passed. Existing two-session warm-hit behavior remains verified; signed-action browser acceptance remains Stage 8.
- Notes: page-local sessionStorage suppresses repeated hints for a minute; server age/admission is authoritative. No new identity-specific UI was introduced. Stage 5 commit: `6d7204b3`.
