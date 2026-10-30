# Profile delivery

Purpose: Keep profile links available while allowing operators to omit their prebuilt HTML.
Scenario: A PHP-served site disables Static profile pages and runs its usual full build; profile requests use the normal renderer and existing access rules. Static-only sites retain the enabled default. Re-enabling followed by a full build restores artifacts.
Sources: [flag registry](../../src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php), [builder](../../src/ForumRewrite/Host/StaticArtifactBuilder.php), [frontend](../../src/ForumRewrite/Host/FrontController.php), [operator guidance](../reference/profile_static_delivery.md).
Boundary: This controls HTML generation and eligibility, not identity, approval, attribution, directory or profile-history derivation. Runtime requests are slower than static hits. Direct physical HTML requests bypass PHP, and old exported public trees need retirement when changing hosting mode.
Verification: Default/on artifact equality, zero disabled profile artifacts, independent thread/post control, stale-artifact bypass, shared refresh, both transitions, candidate failure/retry and real browser profile links on phone/desktop with JavaScript on/off; [implementation evidence](../plans/qdb_build_time/qdb_build_time_step4_implementation_summary.md).
