<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

final class FeatureFlagEvaluatorTest
{
    public function testDefaultsMatchExistingSiteFlags(): void
    {
        $this->withEnvironment([], function (): void {
            $evaluator = new FeatureFlagEvaluator();

            $unicode = $evaluator->evaluate(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);
            $emoji = $evaluator->evaluate(FeatureFlagRegistry::EMOJI_AUTHORED_TEXT);
            $eventSupport = $evaluator->evaluate(FeatureFlagRegistry::EVENT_SUPPORT_ENABLED);
            $notification = $evaluator->evaluate(FeatureFlagRegistry::APP_VERSION_NOTIFICATION);
            $staticDetailPages = $evaluator->evaluate(FeatureFlagRegistry::STATIC_DETAIL_PAGES_ENABLED);
            $agentReplies = $evaluator->evaluate(FeatureFlagRegistry::DEDALUS_AGENT_REPLIES_ENABLED);
            $automaticAgentReplies = $evaluator->evaluate(FeatureFlagRegistry::DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED);
            $agentResponseRequests = $evaluator->evaluate(FeatureFlagRegistry::AGENT_RESPONSE_REQUESTS_ENABLED);
            $conversationRecording = $evaluator->evaluate(FeatureFlagRegistry::LLM_CONVERSATION_RECORDING_ENABLED);
            $conversationUi = $evaluator->evaluate(FeatureFlagRegistry::LLM_CONVERSATION_UI_ENABLED);
            $approvedMembersOnly = $evaluator->evaluate(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY);
            $automaticGuestKeypair = $evaluator->evaluate(FeatureFlagRegistry::AUTOMATIC_GUEST_KEYPAIR_ENABLED);
            $fastScoring = $evaluator->evaluate(FeatureFlagRegistry::FAST_SCORING_ENABLED);
            $fastScoringAutomaticEnqueue = $evaluator->evaluate(FeatureFlagRegistry::FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED);

            assertSame(false, $unicode->effectiveValue);
            assertSame('default', $unicode->source);
            assertSame(true, $unicode->isDefault());
            assertSame(false, $emoji->effectiveValue);
            assertSame('default', $emoji->source);
            assertSame(true, $emoji->isDefault());
            assertSame(false, $eventSupport->effectiveValue);
            assertSame('default', $eventSupport->source);
            assertSame(true, $eventSupport->isDefault());
            assertSame(true, $notification->effectiveValue);
            assertSame('default', $notification->source);
            assertSame(true, $notification->isDefault());
            assertSame(true, $staticDetailPages->effectiveValue);
            assertSame('default', $staticDetailPages->source);
            assertSame(true, $staticDetailPages->isDefault());
            assertSame(true, $agentReplies->effectiveValue);
            assertSame('default', $agentReplies->source);
            assertSame(true, $automaticAgentReplies->effectiveValue);
            assertSame('default', $automaticAgentReplies->source);
            assertSame(true, $agentResponseRequests->effectiveValue);
            assertSame('default', $agentResponseRequests->source);
            assertSame(true, $conversationRecording->effectiveValue);
            assertSame('default', $conversationRecording->source);
            assertSame(true, $conversationUi->effectiveValue);
            assertSame('default', $conversationUi->source);
            assertSame(false, $approvedMembersOnly->effectiveValue);
            assertSame('default', $approvedMembersOnly->source);
            assertSame(false, $automaticGuestKeypair->effectiveValue);
            assertSame('default', $automaticGuestKeypair->source);
            assertSame(false, $fastScoring->effectiveValue);
            assertSame('default', $fastScoring->source);
            assertSame(false, $fastScoringAutomaticEnqueue->effectiveValue);
            assertSame('default', $fastScoringAutomaticEnqueue->source);
        });
    }

    public function testEnvironmentOverridesDefaults(): void
    {
        $this->withEnvironment([
            FeatureFlagRegistry::UNICODE_AUTHORED_TEXT => 'true',
            FeatureFlagRegistry::EMOJI_AUTHORED_TEXT => 'true',
            FeatureFlagRegistry::APP_VERSION_NOTIFICATION => 'false',
            FeatureFlagRegistry::APPROVED_MEMBERS_ONLY => 'true',
        ], function (): void {
            $evaluator = new FeatureFlagEvaluator();

            $unicode = $evaluator->evaluate(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);
            $emoji = $evaluator->evaluate(FeatureFlagRegistry::EMOJI_AUTHORED_TEXT);
            $notification = $evaluator->evaluate(FeatureFlagRegistry::APP_VERSION_NOTIFICATION);
            $approvedMembersOnly = $evaluator->evaluate(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY);

            assertSame(true, $unicode->effectiveValue);
            assertSame('environment', $unicode->source);
            assertSame(true, $unicode->environmentValue);
            assertSame(true, $emoji->effectiveValue);
            assertSame('environment', $emoji->source);
            assertSame(true, $emoji->environmentValue);
            assertSame(false, $notification->effectiveValue);
            assertSame('environment', $notification->source);
            assertSame(false, $notification->environmentValue);
            assertSame(true, $approvedMembersOnly->effectiveValue);
            assertSame('environment', $approvedMembersOnly->source);
            assertSame(true, $approvedMembersOnly->environmentValue);
        });
    }

    public function testRegistryListsCurrentPublicFlags(): void
    {
        $states = (new FeatureFlagEvaluator())->all();
        $keys = array_map(
            static fn ($state): string => $state->definition->key,
            $states
        );

        assertSame([
            FeatureFlagRegistry::APPROVED_MEMBERS_ONLY,
            FeatureFlagRegistry::AUTOMATIC_GUEST_KEYPAIR_ENABLED,
            FeatureFlagRegistry::UNICODE_AUTHORED_TEXT,
            FeatureFlagRegistry::EMOJI_AUTHORED_TEXT,
            FeatureFlagRegistry::EVENT_SUPPORT_ENABLED,
            FeatureFlagRegistry::APP_VERSION_NOTIFICATION,
            FeatureFlagRegistry::THREAD_DENSITY_TOGGLE_ENABLED,
            FeatureFlagRegistry::MEDIA_EMBEDS_ENABLED,
            FeatureFlagRegistry::MEDIA_EMBEDS_INLINE_PLAYER_ENABLED,
            FeatureFlagRegistry::STATIC_DETAIL_PAGES_ENABLED,
            FeatureFlagRegistry::DEDALUS_AGENT_REPLIES_ENABLED,
            FeatureFlagRegistry::DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED,
            FeatureFlagRegistry::AGENT_RESPONSE_REQUESTS_ENABLED,
            FeatureFlagRegistry::LLM_CONVERSATION_RECORDING_ENABLED,
            FeatureFlagRegistry::LLM_CONVERSATION_UI_ENABLED,
            FeatureFlagRegistry::FAST_SCORING_ENABLED,
            FeatureFlagRegistry::FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED,
        ], $keys);
    }

    public function testPrivateConfigFlagsAreReadOnlyAndUsePrivateConfigSource(): void
    {
        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\nDEDALUS_AGENT_REPLIES_ENABLED: true\n");
            $projectRoot = $this->projectRootWithPrivateConfig(<<<'PHP'
<?php

return [
    'DEDALUS_AGENT_REPLIES_ENABLED' => false,
];
PHP);
            $agentReplies = FeatureFlagEvaluator::forApplication($repositoryRoot, $projectRoot)
                ->evaluate(FeatureFlagRegistry::DEDALUS_AGENT_REPLIES_ENABLED);

            assertSame(false, $agentReplies->effectiveValue);
            assertSame('private-config', $agentReplies->source);
            assertSame(false, $agentReplies->canChangeFromSite());
            assertNullValue($agentReplies->siteValue);
        });
    }

    public function testAgentResponseRequestFlagUsesPrivateConfig(): void
    {
        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\n");
            $projectRoot = $this->projectRootWithPrivateConfig(<<<'PHP'
<?php

return [
    'AGENT_RESPONSE_REQUESTS_ENABLED' => false,
];
PHP);
            $requests = FeatureFlagEvaluator::forApplication($repositoryRoot, $projectRoot)
                ->evaluate(FeatureFlagRegistry::AGENT_RESPONSE_REQUESTS_ENABLED);

            assertSame(false, $requests->effectiveValue);
            assertSame('private-config', $requests->source);
            assertSame(false, $requests->canChangeFromSite());
        });
    }

    public function testRepositoryValuesOverrideDefaultsButNotEnvironment(): void
    {
        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\nFORUM_APP_VERSION_NOTIFICATION: false\nFORUM_EMOJI_AUTHORED_TEXT: true\nFORUM_UNICODE_AUTHORED_TEXT: true\n");
            $evaluator = FeatureFlagEvaluator::forRepository($repositoryRoot);

            $unicode = $evaluator->evaluate(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);
            $emoji = $evaluator->evaluate(FeatureFlagRegistry::EMOJI_AUTHORED_TEXT);
            $eventSupport = $evaluator->evaluate(FeatureFlagRegistry::EVENT_SUPPORT_ENABLED);
            $notification = $evaluator->evaluate(FeatureFlagRegistry::APP_VERSION_NOTIFICATION);

            assertSame(true, $unicode->effectiveValue);
            assertSame('site', $unicode->source);
            assertSame(true, $unicode->siteValue);
            assertSame(true, $emoji->effectiveValue);
            assertSame('site', $emoji->source);
            assertSame(true, $emoji->siteValue);
            assertSame(false, $notification->effectiveValue);
            assertSame('site', $notification->source);
            assertSame(false, $notification->siteValue);
            assertSame(false, $eventSupport->effectiveValue);
            assertSame('default', $eventSupport->source);
        });

        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\nFORUM_EVENT_SUPPORT_ENABLED: true\n");
            $eventSupport = FeatureFlagEvaluator::forRepository($repositoryRoot)->evaluate(FeatureFlagRegistry::EVENT_SUPPORT_ENABLED);

            assertSame(true, $eventSupport->effectiveValue);
            assertSame('site', $eventSupport->source);
            assertSame(true, $eventSupport->siteValue);
        });

        $this->withEnvironment([
            FeatureFlagRegistry::UNICODE_AUTHORED_TEXT => 'false',
        ], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\nFORUM_UNICODE_AUTHORED_TEXT: true\n");
            $unicode = FeatureFlagEvaluator::forRepository($repositoryRoot)->evaluate(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);

            assertSame(false, $unicode->effectiveValue);
            assertSame('environment', $unicode->source);
            assertSame(true, $unicode->siteValue);
        });
    }

    public function testEmojiAuthoredTextDependsOnUnicodeAuthoredText(): void
    {
        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\nFORUM_EMOJI_AUTHORED_TEXT: true\nFORUM_UNICODE_AUTHORED_TEXT: false\n");
            $emoji = FeatureFlagEvaluator::forRepository($repositoryRoot)->evaluate(FeatureFlagRegistry::EMOJI_AUTHORED_TEXT);

            assertSame(false, $emoji->effectiveValue);
            assertSame('dependency', $emoji->source);
            assertSame(true, $emoji->siteValue);
        });
    }

    public function testMediaEmbedsInlinePlayerDependsOnMediaEmbedsEnabled(): void
    {
        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\nFORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED: true\nFORUM_MEDIA_EMBEDS_ENABLED: false\n");
            $inlinePlayer = FeatureFlagEvaluator::forRepository($repositoryRoot)->evaluate(FeatureFlagRegistry::MEDIA_EMBEDS_INLINE_PLAYER_ENABLED);

            assertSame(false, $inlinePlayer->effectiveValue);
            assertSame('dependency', $inlinePlayer->source);
            assertSame(true, $inlinePlayer->siteValue);
        });
    }

    public function testAutomaticAgentRepliesDependsOnAgentReplies(): void
    {
        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\n");
            $projectRoot = $this->projectRootWithPrivateConfig(<<<'PHP'
<?php

return [
    'DEDALUS_AGENT_REPLIES_ENABLED' => false,
    'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED' => true,
];
PHP);
            $automatic = FeatureFlagEvaluator::forApplication($repositoryRoot, $projectRoot)
                ->evaluate(FeatureFlagRegistry::DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED);

            assertSame(false, $automatic->effectiveValue);
            assertSame('dependency', $automatic->source);
        });
    }

    public function testAutomaticFastScoringDependsOnFastScoringEnabled(): void
    {
        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\n");
            $projectRoot = $this->projectRootWithPrivateConfig(<<<'PHP'
<?php

return [
    'FAST_SCORING_ENABLED' => false,
    'FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED' => true,
];
PHP);
            $automatic = FeatureFlagEvaluator::forApplication($repositoryRoot, $projectRoot)
                ->evaluate(FeatureFlagRegistry::FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED);

            assertSame(false, $automatic->effectiveValue);
            assertSame('dependency', $automatic->source);
        });
    }

    public function testDependencyParentEnabledIsExposedEvenWhenChildIsAlreadyOff(): void
    {
        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\nFORUM_EMOJI_AUTHORED_TEXT: false\nFORUM_UNICODE_AUTHORED_TEXT: false\n");
            $emoji = FeatureFlagEvaluator::forRepository($repositoryRoot)->evaluate(FeatureFlagRegistry::EMOJI_AUTHORED_TEXT);

            assertSame(false, $emoji->effectiveValue);
            assertSame('site', $emoji->source);
            assertSame(false, $emoji->dependencyParentEnabled);
            assertSame(true, $emoji->isBlockedByDependency());
        });
    }

    public function testLockReasonBranchesBySource(): void
    {
        $this->withEnvironment([
            FeatureFlagRegistry::APP_VERSION_NOTIFICATION => 'false',
        ], function (): void {
            $notification = (new FeatureFlagEvaluator())->evaluate(FeatureFlagRegistry::APP_VERSION_NOTIFICATION);
            assertSame(true, $notification->isLocked());
            assertSame('Set via environment variable; restart to change.', $notification->lockReason());
        });

        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\n");
            $projectRoot = $this->projectRootWithPrivateConfig(<<<'PHP'
<?php

return [
    'DEDALUS_AGENT_REPLIES_ENABLED' => false,
];
PHP);
            $agentReplies = FeatureFlagEvaluator::forApplication($repositoryRoot, $projectRoot)
                ->evaluate(FeatureFlagRegistry::DEDALUS_AGENT_REPLIES_ENABLED);
            assertSame(true, $agentReplies->isLocked());
            assertSame('Set via private config file; restart to change.', $agentReplies->lockReason());

            $recording = FeatureFlagEvaluator::forApplication($repositoryRoot, $projectRoot)
                ->evaluate(FeatureFlagRegistry::LLM_CONVERSATION_RECORDING_ENABLED);
            assertSame(true, $recording->isLocked());
            assertSame('Not configurable from the site.', $recording->lockReason());

            $unicode = FeatureFlagEvaluator::forRepository($repositoryRoot)->evaluate(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);
            assertSame(false, $unicode->isLocked());
            assertNullValue($unicode->lockReason());
        });
    }

    public function testLockReasonIsNullOnSiteErrorSoTheBannerOwnsThatMessage(): void
    {
        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\nFORUM_UNICODE_AUTHORED_TEXT: yes\n");
            $unicode = FeatureFlagEvaluator::forRepository($repositoryRoot)->evaluate(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);

            assertSame(false, $unicode->isLocked());
            assertNullValue($unicode->lockReason());
        });
    }

    public function testRegistryOrganizesFlagsByOperatorFacingGroup(): void
    {
        $registry = new FeatureFlagRegistry();

        $approvedMembers = $registry->get(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY);
        $guestKeypair = $registry->get(FeatureFlagRegistry::AUTOMATIC_GUEST_KEYPAIR_ENABLED);
        $unicode = $registry->get(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);
        $emoji = $registry->get(FeatureFlagRegistry::EMOJI_AUTHORED_TEXT);
        $eventSupport = $registry->get(FeatureFlagRegistry::EVENT_SUPPORT_ENABLED);
        $versionNotification = $registry->get(FeatureFlagRegistry::APP_VERSION_NOTIFICATION);
        $threadDensity = $registry->get(FeatureFlagRegistry::THREAD_DENSITY_TOGGLE_ENABLED);
        $staticDetails = $registry->get(FeatureFlagRegistry::STATIC_DETAIL_PAGES_ENABLED);
        $agentReplies = $registry->get(FeatureFlagRegistry::DEDALUS_AGENT_REPLIES_ENABLED);
        $conversationUi = $registry->get(FeatureFlagRegistry::LLM_CONVERSATION_UI_ENABLED);
        $fastScoring = $registry->get(FeatureFlagRegistry::FAST_SCORING_ENABLED);

        assertSame('ACCESS', $approvedMembers->groupKey());
        assertSame('ACCESS', $guestKeypair->groupKey());
        assertSame('AUTHORING', $unicode->groupKey());
        assertSame('AUTHORING', $emoji->groupKey());
        assertSame('AUTHORING', $eventSupport->groupKey());
        assertSame('EXPERIENCE', $versionNotification->groupKey());
        assertSame('EXPERIENCE', $threadDensity->groupKey());
        assertSame('RENDERING', $staticDetails->groupKey());
        assertSame('DEDALUS', $agentReplies->groupKey());
        assertSame('LLM', $conversationUi->groupKey());
        // FAST_SCORING_ENABLED's key prefix alone would be "FAST" - this
        // confirms the explicit `group` override on the definition, not the
        // prefix fallback, is what's actually used.
        assertSame('FASTMOD', $fastScoring->groupKey());
        assertSame('Access and identity', $registry->groupLabel($approvedMembers->groupKey()));
        assertSame('Authored content', $registry->groupLabel($unicode->groupKey()));
        assertSame('Forum experience', $registry->groupLabel($versionNotification->groupKey()));
        assertSame('Site rendering', $registry->groupLabel($staticDetails->groupKey()));
        assertSame('Agent replies', $registry->groupLabel($agentReplies->groupKey()));
        assertSame('LLM exchanges', $registry->groupLabel($conversationUi->groupKey()));
        assertSame('Fastmod', $registry->groupLabel($fastScoring->groupKey()));
        assertSame('WIDGET', $registry->groupLabel('WIDGET'));
    }

    public function testInvalidRepositoryRecordIsReportedAndFallsBackToDefault(): void
    {
        $this->withEnvironment([], function (): void {
            $repositoryRoot = $this->repositoryWithFeatureFlags("Schema: site-feature-flags-v1\n\nFORUM_UNICODE_AUTHORED_TEXT: yes\n");
            $unicode = FeatureFlagEvaluator::forRepository($repositoryRoot)->evaluate(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT);

            assertSame(false, $unicode->effectiveValue);
            assertSame('invalid-site-value', $unicode->source);
            assertSame('Invalid site feature flag line: FORUM_UNICODE_AUTHORED_TEXT: yes', $unicode->siteError);
        });
    }

    /**
     * @param array<string, string> $values
     * @param callable(): void $callback
     */
    private function withEnvironment(array $values, callable $callback): void
    {
        $keys = [
            FeatureFlagRegistry::APPROVED_MEMBERS_ONLY,
            FeatureFlagRegistry::AUTOMATIC_GUEST_KEYPAIR_ENABLED,
            FeatureFlagRegistry::UNICODE_AUTHORED_TEXT,
            FeatureFlagRegistry::EMOJI_AUTHORED_TEXT,
            FeatureFlagRegistry::EVENT_SUPPORT_ENABLED,
            FeatureFlagRegistry::APP_VERSION_NOTIFICATION,
            FeatureFlagRegistry::THREAD_DENSITY_TOGGLE_ENABLED,
            FeatureFlagRegistry::MEDIA_EMBEDS_ENABLED,
            FeatureFlagRegistry::MEDIA_EMBEDS_INLINE_PLAYER_ENABLED,
            FeatureFlagRegistry::STATIC_DETAIL_PAGES_ENABLED,
            FeatureFlagRegistry::DEDALUS_AGENT_REPLIES_ENABLED,
            FeatureFlagRegistry::DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED,
            FeatureFlagRegistry::AGENT_RESPONSE_REQUESTS_ENABLED,
            FeatureFlagRegistry::LLM_CONVERSATION_RECORDING_ENABLED,
            FeatureFlagRegistry::LLM_CONVERSATION_UI_ENABLED,
            FeatureFlagRegistry::FAST_SCORING_ENABLED,
            FeatureFlagRegistry::FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED,
        ];
        $previous = [];
        foreach ($keys as $key) {
            $previous[$key] = getenv($key);
            putenv($key);
        }

        foreach ($values as $key => $value) {
            putenv($key . '=' . $value);
        }

        try {
            $callback();
        } finally {
            foreach ($keys as $key) {
                if ($previous[$key] === false) {
                    putenv($key);
                } else {
                    putenv($key . '=' . $previous[$key]);
                }
            }
        }
    }

    private function repositoryWithFeatureFlags(string $contents): string
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-feature-flags-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot . '/records/instance', 0777, true);
        file_put_contents($repositoryRoot . '/records/instance/feature-flags.txt', $contents);

        return $repositoryRoot;
    }

    private function projectRootWithPrivateConfig(string $contents): string
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-private-flags-' . bin2hex(random_bytes(6));
        mkdir($root . '/app', 0777, true);
        mkdir($root . '/forum-private', 0777, true);
        file_put_contents($root . '/forum-private/secrets.php', $contents);

        return $root . '/app';
    }
}
