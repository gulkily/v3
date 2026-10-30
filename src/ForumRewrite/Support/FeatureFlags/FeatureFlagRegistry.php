<?php

declare(strict_types=1);

namespace ForumRewrite\Support\FeatureFlags;

final class FeatureFlagRegistry
{
    public const RAILSPIKE_ENABLED = 'FORUM_RAILSPIKE_ENABLED';
    public const COLLAPSE_SAME_AUTHOR_POSTS_ENABLED = 'FORUM_COLLAPSE_SAME_AUTHOR_POSTS_ENABLED';
    public const IMAGE_BACKUP_ENABLED = 'FORUM_IMAGE_BACKUP_ENABLED';
    public const TEXT_BACKUP_ENABLED = 'FORUM_TEXT_BACKUP_ENABLED';
    public const IMAGE_CONTENT_ENABLED = 'FORUM_IMAGE_CONTENT_ENABLED';
    public const PRIVATE_MESSAGES_ENABLED = 'FORUM_PRIVATE_MESSAGES_ENABLED';
    public const UNICODE_AUTHORED_TEXT = 'FORUM_UNICODE_AUTHORED_TEXT';
    public const EMOJI_AUTHORED_TEXT = 'FORUM_EMOJI_AUTHORED_TEXT';
    public const EVENT_SUPPORT_ENABLED = 'FORUM_EVENT_SUPPORT_ENABLED';
    public const APP_VERSION_NOTIFICATION = 'FORUM_APP_VERSION_NOTIFICATION';
    public const THREAD_DENSITY_TOGGLE_ENABLED = 'FORUM_THREAD_DENSITY_TOGGLE_ENABLED';
    public const MEDIA_EMBEDS_ENABLED = 'FORUM_MEDIA_EMBEDS_ENABLED';
    public const MEDIA_EMBEDS_INLINE_PLAYER_ENABLED = 'FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED';
    public const STATIC_PROFILE_PAGES_ENABLED = 'FORUM_STATIC_PROFILE_PAGES_ENABLED';
    public const STATIC_DETAIL_PAGES_ENABLED = 'FORUM_STATIC_DETAIL_PAGES_ENABLED';
    public const DEDALUS_AGENT_REPLIES_ENABLED = 'DEDALUS_AGENT_REPLIES_ENABLED';
    public const DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED = 'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED';
    public const AGENT_RESPONSE_REQUESTS_ENABLED = 'AGENT_RESPONSE_REQUESTS_ENABLED';
    public const LLM_CONVERSATION_RECORDING_ENABLED = 'LLM_CONVERSATION_RECORDING_ENABLED';
    public const LLM_CONVERSATION_UI_ENABLED = 'LLM_CONVERSATION_UI_ENABLED';
    public const APPROVED_MEMBERS_ONLY = 'FORUM_APPROVED_MEMBERS_ONLY';
    public const ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL = 'FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL';
    public const AUTOMATIC_GUEST_KEYPAIR_ENABLED = 'FORUM_AUTOMATIC_GUEST_KEYPAIR_ENABLED';
    public const USERNAME_CHANGES_ENABLED = 'FORUM_USERNAME_CHANGES_ENABLED';
    public const VERIFIED_ATTRIBUTION = 'FORUM_VERIFIED_ATTRIBUTION';
    public const FASTMOD_AUTOMATIC_TAGGING_ENABLED = 'FASTMOD_AUTOMATIC_TAGGING_ENABLED';
    public const FAST_SCORING_ENABLED = 'FAST_SCORING_ENABLED';
    public const FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED = 'FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED';

    /** Stable UI order, independent of definition insertion order. See CONTRIBUTING.md. */
    public const PRESENTATION_ORDER = [
        'ACCESS' => [self::PRIVATE_MESSAGES_ENABLED, self::ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL,
            self::APPROVED_MEMBERS_ONLY, self::AUTOMATIC_GUEST_KEYPAIR_ENABLED, self::USERNAME_CHANGES_ENABLED,
            self::VERIFIED_ATTRIBUTION],
        'AUTHORING' => [self::IMAGE_CONTENT_ENABLED, self::UNICODE_AUTHORED_TEXT,
            self::EMOJI_AUTHORED_TEXT, self::EVENT_SUPPORT_ENABLED],
        'EXPERIENCE' => [self::APP_VERSION_NOTIFICATION, self::THREAD_DENSITY_TOGGLE_ENABLED],
        'RENDERING' => [self::COLLAPSE_SAME_AUTHOR_POSTS_ENABLED, self::STATIC_DETAIL_PAGES_ENABLED, self::STATIC_PROFILE_PAGES_ENABLED,
            self::MEDIA_EMBEDS_ENABLED, self::MEDIA_EMBEDS_INLINE_PLAYER_ENABLED],
        'BACKUP' => [self::IMAGE_BACKUP_ENABLED, self::TEXT_BACKUP_ENABLED],
        'FASTMOD' => [self::FAST_SCORING_ENABLED, self::FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED,
            self::FASTMOD_AUTOMATIC_TAGGING_ENABLED],
        'DEDALUS' => [self::DEDALUS_AGENT_REPLIES_ENABLED, self::DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED,
            self::AGENT_RESPONSE_REQUESTS_ENABLED, self::RAILSPIKE_ENABLED],
        'LLM' => [self::LLM_CONVERSATION_RECORDING_ENABLED, self::LLM_CONVERSATION_UI_ENABLED],
    ];

    /** @param list<FeatureFlagState> $flags @return array<string, list<FeatureFlagState>> */
    public function presentationGroups(array $flags): array
    {
        $groups = [];
        foreach ($flags as $flag) {
            $groups[$flag->definition->groupKey()][$flag->definition->key] = $flag;
        }
        $ordered = [];
        foreach (self::PRESENTATION_ORDER as $group => $keys) {
            if (!isset($groups[$group])) continue;
            $ordered[$group] = [];
            foreach ($keys as $key) {
                if (!isset($groups[$group][$key])) continue;
                $ordered[$group][] = $groups[$group][$key];
                unset($groups[$group][$key]);
            }
            // Keep unexpected entries visible at the end; completeness is enforced by tests.
            array_push($ordered[$group], ...array_values($groups[$group]));
            unset($groups[$group]);
        }
        foreach ($groups as $group => $entries) $ordered[$group] = array_values($entries);
        return $ordered;
    }

    /**
     * @return list<FeatureFlagDefinition>
     */
    public function all(): array
    {
        return [
            new FeatureFlagDefinition(
                self::RAILSPIKE_ENABLED,
                'Railspike development',
                'Enable thread development requests. Disabling pauses queued assessments; stored workflows are retained.',
                false,
                self::RAILSPIKE_ENABLED,
                siteMutable: true,
                group: 'DEDALUS',
            ),
            new FeatureFlagDefinition(
                self::IMAGE_BACKUP_ENABLED,
                'Image backups',
                'Allow image archive downloads, including retained images when attachments are disabled. Stored images are preserved.',
                true,
                self::IMAGE_BACKUP_ENABLED,
                siteMutable: true,
                group: 'BACKUP',
            ),
            new FeatureFlagDefinition(
                self::TEXT_BACKUP_ENABLED,
                'Text backups',
                'Allow repository and SQLite downloads. Disabling also makes SQLite Viewer and query dialogs unavailable; stored content is preserved.',
                true,
                self::TEXT_BACKUP_ENABLED,
                siteMutable: true,
                group: 'BACKUP',
            ),
            new FeatureFlagDefinition(
                self::COLLAPSE_SAME_AUTHOR_POSTS_ENABLED,
                'Collapse posts from same author',
                'Group consecutive posts by the same author within 15 minutes in thread views.',
                true,
                self::COLLAPSE_SAME_AUTHOR_POSTS_ENABLED,
                siteMutable: true,
                group: 'RENDERING',
            ),
            new FeatureFlagDefinition(
                self::IMAGE_CONTENT_ENABLED,
                'Image attachments',
                'Allow approved members to attach JPEG/PNG images. Disabling blocks image delivery and authoring; retained images can still be backed up when image backups are enabled.',
                false,
                self::IMAGE_CONTENT_ENABLED,
                siteMutable: true,
                group: 'AUTHORING',
            ),
            new FeatureFlagDefinition(
                self::PRIVATE_MESSAGES_ENABLED,
                'Private messages',
                'Enable private messaging, including message history, unread status, and history sync. Disabling preserves stored messages. Messages navigation updates here immediately; reload other messaging pages after re-enabling.',
                true,
                self::PRIVATE_MESSAGES_ENABLED,
                siteMutable: true,
                group: 'ACCESS',
            ),
            new FeatureFlagDefinition(
                self::ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL,
                'Allow members to approve keys for other existing accounts',
                'When disabled, members can approve new usernames and their own additional keys. Only root-approved operators can approve keys for other existing usernames. Existing approvals remain valid.',
                true,
                self::ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL,
                siteMutable: true,
                group: 'ACCESS',
            ),
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
                self::USERNAME_CHANGES_ENABLED,
                'Username changes',
                'Let members change the username on their existing key from Account, and list each change in the activity report. Disabling stops new changes and hides their activity; saved names, keys and approvals are unchanged.',
                false,
                self::USERNAME_CHANGES_ENABLED,
                siteMutable: true,
                group: 'ACCESS',
            ),
            new FeatureFlagDefinition(
                self::VERIFIED_ATTRIBUTION,
                'Verified attribution',
                'Unsigned posts and votes made after the cutoff stay visible but are marked unverified and do not change scores, post counts or profiles. Turning this on the first time sets the cutoff to now.',
                false,
                self::VERIFIED_ATTRIBUTION,
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
                self::EVENT_SUPPORT_ENABLED,
                'Event support',
                'Allow threads to include and display optional event date, location, and link details.',
                false,
                self::EVENT_SUPPORT_ENABLED,
                siteMutable: true,
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
                group: 'RENDERING',
            ),
            new FeatureFlagDefinition(
                self::MEDIA_EMBEDS_INLINE_PLAYER_ENABLED,
                'Media embed inline player',
                'Play a recognized YouTube link inline behind a collapsed expando, and show a cached thumbnail/title preview for a recognized Instagram link, instead of Media embed cards\' plain link card. Off by default; requires Media embed cards enabled too.',
                false,
                self::MEDIA_EMBEDS_INLINE_PLAYER_ENABLED,
                siteMutable: true,
                requiresEnabledFlag: self::MEDIA_EMBEDS_ENABLED,
                group: 'RENDERING',
            ),
            new FeatureFlagDefinition(
                self::STATIC_PROFILE_PAGES_ENABLED,
                'Static profile pages',
                'Enable to pre-build profiles for static hosting. Run a full static build after changing this flag.',
                false,
                self::STATIC_PROFILE_PAGES_ENABLED,
                siteMutable: true,
                group: 'RENDERING',
            ),
            new FeatureFlagDefinition(
                self::STATIC_DETAIL_PAGES_ENABLED,
                'Static detail pages',
                'Off by default: individual threads and posts render at runtime. Enable to pre-build detail pages for static hosting. Run a full static build after changing this flag.',
                false,
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
                group: 'DEDALUS',
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
            new FeatureFlagDefinition(
                self::FASTMOD_AUTOMATIC_TAGGING_ENABLED,
                'Automatic Fastmod tagging',
                'Apply tags from eligible Fastmod results.',
                false,
                self::FASTMOD_AUTOMATIC_TAGGING_ENABLED,
                siteMutable: true,
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
        'BACKUP' => 'Backups',
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
