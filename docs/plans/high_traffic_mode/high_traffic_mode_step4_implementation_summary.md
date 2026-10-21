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
