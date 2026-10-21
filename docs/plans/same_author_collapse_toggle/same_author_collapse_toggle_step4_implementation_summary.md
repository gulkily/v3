> **Feature plan:** [Step 2](./same_author_collapse_toggle_step2_feature_description.md) · [Step 3](./same_author_collapse_toggle_step3_development_plan.md) · [Step 4](./same_author_collapse_toggle_step4_implementation_summary.md)

Debug mode: enabled

# Same-author collapse toggle — implementation

## Stage 1 - Operator flag and thread rendering
- Changes: registered default-enabled, site-mutable rendering flag; thread controller passes its evaluated value into continuation classification. Existing run-tail logic now restores separate metadata, actions, and reply counts when disabled. No schema or navigation changes.
- Verification: `php tests/run.php FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest LocalAppSmokeTest::testThreadMergesSameAuthorQuickRepliesIntoContinuations` passed (21 tests). Covers defaults, site/environment precedence, locking, disabled metadata/actions/counts, and restoration. Registry inventory and test environment cleanup updated after initial failures.
- Notes: repository search confirms only `thread.php` classifies continuations; alternate post views do not group independently. Planning commit: `fc79dc71`. Browser acceptance remains Stage 2.
