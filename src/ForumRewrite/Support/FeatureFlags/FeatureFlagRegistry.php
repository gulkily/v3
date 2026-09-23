<?php

declare(strict_types=1);

namespace ForumRewrite\Support\FeatureFlags;

final class FeatureFlagRegistry
{
    public const UNICODE_AUTHORED_TEXT = 'FORUM_UNICODE_AUTHORED_TEXT';
    public const EMOJI_AUTHORED_TEXT = 'FORUM_EMOJI_AUTHORED_TEXT';
    public const APP_VERSION_NOTIFICATION = 'FORUM_APP_VERSION_NOTIFICATION';
    public const THREAD_DENSITY_TOGGLE_ENABLED = 'FORUM_THREAD_DENSITY_TOGGLE_ENABLED';
    public const DEDALUS_AGENT_REPLIES_ENABLED = 'DEDALUS_AGENT_REPLIES_ENABLED';
    public const DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED = 'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED';
    public const LLM_CONVERSATION_RECORDING_ENABLED = 'LLM_CONVERSATION_RECORDING_ENABLED';
    public const LLM_CONVERSATION_UI_ENABLED = 'LLM_CONVERSATION_UI_ENABLED';
    public const APPROVED_MEMBERS_ONLY = 'FORUM_APPROVED_MEMBERS_ONLY';

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
            ),
            new FeatureFlagDefinition(
                self::UNICODE_AUTHORED_TEXT,
                'Unicode authored text',
                'Allow visible UTF-8 prose in human-authored post subject and body fields.',
                false,
                self::UNICODE_AUTHORED_TEXT,
                siteMutable: true,
            ),
            new FeatureFlagDefinition(
                self::EMOJI_AUTHORED_TEXT,
                'Emoji authored text',
                'Allow emoji in human-authored post subject and body fields when Unicode authored text is also enabled.',
                false,
                self::EMOJI_AUTHORED_TEXT,
                siteMutable: true,
                requiresEnabledFlag: self::UNICODE_AUTHORED_TEXT,
            ),
            new FeatureFlagDefinition(
                self::APP_VERSION_NOTIFICATION,
                'App version notification',
                'Show browser-side app version polling and the reload notification banner.',
                true,
                self::APP_VERSION_NOTIFICATION,
                siteMutable: true,
            ),
            new FeatureFlagDefinition(
                self::THREAD_DENSITY_TOGGLE_ENABLED,
                'Thread density toggle',
                'Show the Comfortable/Compact thread density menu in the board/tag header. Off by default: the menu has known bugs and crowds the header on mobile.',
                false,
                self::THREAD_DENSITY_TOGGLE_ENABLED,
                siteMutable: true,
            ),
            new FeatureFlagDefinition(
                self::DEDALUS_AGENT_REPLIES_ENABLED,
                'Agent replies',
                'Allow post analysis to generate and publish suggested agent replies when reply gates pass.',
                true,
                self::DEDALUS_AGENT_REPLIES_ENABLED,
                'private',
            ),
            new FeatureFlagDefinition(
                self::DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED,
                'Automatic agent replies',
                'Allow eligible post pages to trigger agent reply work automatically.',
                true,
                self::DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED,
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
}
