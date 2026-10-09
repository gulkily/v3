<?php

declare(strict_types=1);

namespace ForumRewrite\Support\FeatureFlags;

final class FeatureFlagRegistry
{
    public const UNICODE_AUTHORED_TEXT = 'FORUM_UNICODE_AUTHORED_TEXT';
    public const EMOJI_AUTHORED_TEXT = 'FORUM_EMOJI_AUTHORED_TEXT';
    public const APP_VERSION_NOTIFICATION = 'FORUM_APP_VERSION_NOTIFICATION';
    public const THREAD_DENSITY_TOGGLE_ENABLED = 'FORUM_THREAD_DENSITY_TOGGLE_ENABLED';
    public const MEDIA_EMBEDS_ENABLED = 'FORUM_MEDIA_EMBEDS_ENABLED';
    public const STATIC_DETAIL_PAGES_ENABLED = 'FORUM_STATIC_DETAIL_PAGES_ENABLED';
    public const DEDALUS_AGENT_REPLIES_ENABLED = 'DEDALUS_AGENT_REPLIES_ENABLED';
    public const DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED = 'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED';
    public const AGENT_RESPONSE_REQUESTS_ENABLED = 'AGENT_RESPONSE_REQUESTS_ENABLED';
    public const LLM_CONVERSATION_RECORDING_ENABLED = 'LLM_CONVERSATION_RECORDING_ENABLED';
    public const LLM_CONVERSATION_UI_ENABLED = 'LLM_CONVERSATION_UI_ENABLED';
    public const APPROVED_MEMBERS_ONLY = 'FORUM_APPROVED_MEMBERS_ONLY';
    public const AUTOMATIC_GUEST_KEYPAIR_ENABLED = 'FORUM_AUTOMATIC_GUEST_KEYPAIR_ENABLED';
    public const FAST_SCORING_ENABLED = 'FAST_SCORING_ENABLED';
    public const FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED = 'FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED';

    /**
     * @return list<FeatureFlagDefinition>
     */
    public function all(): array
    {
        return [
            new FeatureFlagDefinition(
                self::APPROVED_MEMBERS_ONLY,
                'Approved members only',
                'Restrict the site to approved members and provide a lobby for unapproved users.',
                false,
                self::APPROVED_MEMBERS_ONLY,
                siteMutable: true,
                group: 'ACCESS',
            ),
            new FeatureFlagDefinition(
                self::AUTOMATIC_GUEST_KEYPAIR_ENABLED,
                'Automatic guest keypair',
                'Prepare a browser-local guest keypair for new visitors before their first signed action. Each browser controls whether its public key is published immediately or on first use.',
                false,
                self::AUTOMATIC_GUEST_KEYPAIR_ENABLED,
                siteMutable: true,
                group: 'ACCESS',
            ),
            new FeatureFlagDefinition(
                self::UNICODE_AUTHORED_TEXT,
                'Unicode authored text',
                'Allow visible UTF-8 prose in human-authored post subject and body fields.',
                false,
                self::UNICODE_AUTHORED_TEXT,
                siteMutable: true,
                group: 'AUTHORING',
            ),
            new FeatureFlagDefinition(
                self::EMOJI_AUTHORED_TEXT,
                'Emoji authored text',
                'Allow emoji in human-authored post subject and body fields when Unicode authored text is also enabled.',
                false,
                self::EMOJI_AUTHORED_TEXT,
                siteMutable: true,
                requiresEnabledFlag: self::UNICODE_AUTHORED_TEXT,
                group: 'AUTHORING',
            ),
            new FeatureFlagDefinition(
                self::APP_VERSION_NOTIFICATION,
                'App version notification',
                'Show browser-side app version polling and the reload notification banner.',
                true,
                self::APP_VERSION_NOTIFICATION,
                siteMutable: true,
                group: 'EXPERIENCE',
            ),
            new FeatureFlagDefinition(
                self::THREAD_DENSITY_TOGGLE_ENABLED,
                'Thread density toggle',
                'Show the Comfortable/Compact thread density menu in the board/tag header. Off by default: the menu has known bugs and crowds the header on mobile.',
                false,
                self::THREAD_DENSITY_TOGGLE_ENABLED,
                siteMutable: true,
                group: 'EXPERIENCE',
            ),
            new FeatureFlagDefinition(
                self::MEDIA_EMBEDS_ENABLED,
                'Media embed cards',
                'Render a small local card for a recognized YouTube/Instagram URL in a post body, instead of a bare link. Off by default; enable per site once ready.',
                false,
                self::MEDIA_EMBEDS_ENABLED,
                siteMutable: true,
            ),
            new FeatureFlagDefinition(
                self::STATIC_DETAIL_PAGES_ENABLED,
                'Static detail pages',
                'Pre-build individual thread and post pages during a full static release. Disable this on a large QDB instance to keep shared listing pages static while individual quotes render dynamically.',
                true,
                self::STATIC_DETAIL_PAGES_ENABLED,
                siteMutable: true,
                group: 'RENDERING',
            ),
            new FeatureFlagDefinition(
                self::DEDALUS_AGENT_REPLIES_ENABLED,
                'Legacy suggested agent replies',
                'Allow the legacy post-analysis suggested-response path to generate and publish agent replies when reply gates pass.',
                true,
                self::DEDALUS_AGENT_REPLIES_ENABLED,
                'private',
            ),
            new FeatureFlagDefinition(
                self::DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED,
                'Legacy automatic agent replies',
                'Allow eligible post pages and analysis requests to publish legacy suggested agent replies automatically. Does not affect reader-requested response modes.',
                true,
                self::DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED,
                'private',
                requiresEnabledFlag: self::DEDALUS_AGENT_REPLIES_ENABLED,
            ),
            new FeatureFlagDefinition(
                self::AGENT_RESPONSE_REQUESTS_ENABLED,
                'Request agent responses',
                'Allow approved readers to request a selected agent response for a post.',
                true,
                self::AGENT_RESPONSE_REQUESTS_ENABLED,
                'private',
            ),
            new FeatureFlagDefinition(
                self::LLM_CONVERSATION_RECORDING_ENABLED,
                'LLM conversation recording',
                'Record exact LLM prompts and responses in the private exchange database.',
                true,
                self::LLM_CONVERSATION_RECORDING_ENABLED,
                'private',
            ),
            new FeatureFlagDefinition(
                self::LLM_CONVERSATION_UI_ENABLED,
                'LLM conversation UI',
                'Make recorded LLM prompts and responses available in the approved-user/operator web UI.',
                true,
                self::LLM_CONVERSATION_UI_ENABLED,
                'private',
            ),
            new FeatureFlagDefinition(
                self::FAST_SCORING_ENABLED,
                'Fast scoring',
                'Allow published posts to be scored for moderation signal via the fast/cheap Fastmod scoring pipeline.',
                false,
                self::FAST_SCORING_ENABLED,
                'private',
                group: 'FASTMOD',
            ),
            new FeatureFlagDefinition(
                self::FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED,
                'Automatic fast scoring',
                'Automatically enqueue eligible published posts for fast scoring.',
                false,
                self::FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED,
                'private',
                requiresEnabledFlag: self::FAST_SCORING_ENABLED,
                group: 'FASTMOD',
            ),
        ];
    }

    public function get(string $key): ?FeatureFlagDefinition
    {
        foreach ($this->all() as $definition) {
            if ($definition->key === $key) {
                return $definition;
            }
        }

        return null;
    }

    private const GROUP_LABELS = [
        'ACCESS' => 'Access and identity',
        'AUTHORING' => 'Authored content',
        'EXPERIENCE' => 'Forum experience',
        'RENDERING' => 'Site rendering',
        'DEDALUS' => 'Agent replies',
        'LLM' => 'LLM exchanges',
        'FASTMOD' => 'Fastmod',
    ];

    public function groupLabel(string $groupKey): string
    {
        return self::GROUP_LABELS[$groupKey] ?? $groupKey;
    }
}
