Name: Feature flags
Purpose: Keep operator-visible feature choices in one canonical registry.
Scenario: The Feature Flags page presents each registered flag once in its assigned group. Site-mutable flags accept signed site settings; environment overrides retain their existing precedence.
Sources: src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php; src/ForumRewrite/Support/FeatureFlags/FeatureFlagEvaluator.php; templates/pages/feature_flags.php; CONTRIBUTING.md#feature-flag-presentation-order
Verification: tests/FeatureFlagEvaluatorTest.php; tests/FeatureFlagsBehaviorTest.php; tests/FeatureFlagChangePageTest.php
Example: `FORUM_PUSH_ENABLED` (Notifications, off by default) shows the Preferences row and opens the `/api/push/` routes; with it off they answer 404 and nothing is sent, while stored subscriptions stay until members turn them off.
