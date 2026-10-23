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

## Stage 7 - Operator control

- Changes: instance-local control in existing Feature Flags Tools view using the established prepared-record/OpenPGP signing flow and operator policy; UI and CLI share switching/status logic. Added pending/failure recovery feedback and no routine success notice. Existing publication guard now tracks nested writer/service use to avoid self-deadlock; no permission system or navigation destination added.
- Verification: `node tests/browser/high_traffic_control.mjs` passed real operator signing, enable/disable, pending state, actual unsupported-routing failure and retry, keyboard activation, unauthorized hiding/403, and phone/desktop overflow/control checks for enabled/disabled/failure states across 13 shared themes. Inspected rendered desktop and phone failure screenshots. Focused PHP HighTraffic/FeatureFlags/Retraction checks passed 22 tests; PHP/JS syntax passed.
- Notes: initial browser-harness retries exposed the existing automatic authentication-resume navigation; acceptance now waits for that established flow before interacting. CLI recovery stays available outside the web route. No copy cleanup or nav/icon change. Stage 6 commit: `83a07b96`.

## Stage 8 - Acceptance and operations

- Changes: [sysop runbook](../../runbooks/high_traffic_mode.md), CLI reference, [diagram](./high_traffic_mode_diagram.png) and [Mermaid source](./high_traffic_mode_diagram.mmd). Final acceptance covers twenty-page source updates, concurrent Apache reads/cold requests, failures, private transitions and signed browser controls. Storage admission includes retained assets, with per-render staging/cleanup; log sampling scans backward without exploding the entire suffix into lines. Classification changes withdraw the serving pointer before commit, synchronized with mode activation. Saved root/profile binding prevents accidental concurrent use by another serving context. Shared pages skip redundant automatic sign-in/reload; live actions retain authorization. Ordinary likes preserve the mode; moderation flags withdraw it. Routine writes coalesce refresh work without cancelling active renders, and stale hints are retired once the server copy is fresh. Missing pages retain 404 and renderer jobs do not count as visitors.
- Verification: **passed** 75 selected PHP regressions (including a full-project static build/access check), 10 existing private-authentication cases, current HighTraffic cases and signed-reaction checks; PHP/JS/Bash lint and diff integrity. **Passed** the unchanged two-test import publication recovery matrix via `python tests/high_traffic_publication_test.py` with an isolated two-document inventory (25.75 seconds); actual code/templates/assets/canonical fixtures are reused. **Passed** isolated Apache: zero PHP for warm session-bearing hits/HEAD, bypasses, retained assets/revalidation, cold limits/burst, twenty-page source refresh (2.415 seconds in the recorded run), complete old/new reads, failed-refresh retention, obsolete-renderer rejection, non-cancelling routine writes, real private transition, import retirement, permission refusal and routing restore. **Passed** browser: signed mode changes, no forced shared-page sign-in/reload, signed likes/live actions from cached HTML, timestamp hint, unauthorized rejection, pending/failure/retry, keyboard, and phone/desktop enabled/disabled/failure states across 13 shared themes.
- Notes: one active public root/profile per canonical repository; external configuration/deployment/import maintenance must disable first. No production deployment, cron installation, host traffic-limit configuration or production throughput validation is in scope. No read-model schema change, migration, new nav destination or automatic traffic/load policy. Source review confirms the existing service worker is network-first for HTML and does not cache ordinary HTML pages; revalidation headers and retained assets were checked in Apache. Stage 7 commit: `db2360dc`.

## Requests and verification limits

- TODO: integrate the high-traffic control into the standard Feature Flags switch rows, preserving instance-local applied state, signed authorization, pending/error feedback and CLI recovery. The user chose to retain the separate button for this release and defer this UI refinement; no UI change was made.
- Request: automatic traffic/load activation soon. Disposition: shared policy-neutral switch service delivered; measurement/activation/cooldown/override policy remains a separate proposed cycle, unapproved.
- Request: separate broader failure management. Disposition: minimum recovery delivered; dashboards/alerts/automated repair deferred, unapproved.
- In-scope acceptance refinements: prevent classification/activation races, count retained assets in storage admission, bind mode to saved public root/profile, bound log-reader memory, distinguish positive votes from moderation, avoid public authentication reload loops, and prevent routine writes from starving refresh. These enforce approved access/capacity requirements rather than adding features.
- Production host setup/request probes, cron installation and sysop traffic controls: not performed; deployment is not authorized. Full unfiltered repository suite: not run; report focused checks only. The first full-project repeated import-publication attempt failed because this summary was edited during its snapshot; the stable full-project repeated matrix was later interrupted after 770 seconds of rebuilding the entire documentation site. Its unchanged recovery matrix passed with the bounded documentation fixture; that is not a claim that the full-project repeated matrix passed. The separate full-project static-build/access regression passed.

## Operator CLI correction

- User-requested follow-up: record shared CLI conventions in `CONTRIBUTING.md`; use readable output by default with explicit `--json`, show the cron line after enable/status, and align `cron` (setup instructions) / `run` (refresh worker) with task-queue. Top-level help, reference and runbook updated. Existing cron worker schedules must change from `cron` to `run`; no schedule is installed or inspected. Separate UI button remains deferred.
- Verification: isolated Apache acceptance passed, including readable/JSON status, enable cron guidance, quoted schedule paths, help and invalid-argument checks; all four HighTraffic PHP tests passed. PHP/Bash syntax and diff integrity passed. No production mode or schedule changed.
