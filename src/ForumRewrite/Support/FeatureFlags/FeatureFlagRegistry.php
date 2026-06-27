<?php

declare(strict_types=1);

namespace ForumRewrite\Support\FeatureFlags;

final class FeatureFlagRegistry
{
    public const UNICODE_AUTHORED_TEXT = 'FORUM_UNICODE_AUTHORED_TEXT';
    public const APP_VERSION_NOTIFICATION = 'FORUM_APP_VERSION_NOTIFICATION';

    /**
     * @return list<FeatureFlagDefinition>
     */
    public function all(): array
    {
        return [
            new FeatureFlagDefinition(
                self::UNICODE_AUTHORED_TEXT,
                'Unicode authored text',
                'Allow visible UTF-8 prose in human-authored post subject and body fields.',
                false,
                self::UNICODE_AUTHORED_TEXT,
                siteMutable: true,
            ),
            new FeatureFlagDefinition(
                self::APP_VERSION_NOTIFICATION,
                'App version notification',
                'Show browser-side app version polling and the reload notification banner.',
                true,
                self::APP_VERSION_NOTIFICATION,
                siteMutable: true,
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
