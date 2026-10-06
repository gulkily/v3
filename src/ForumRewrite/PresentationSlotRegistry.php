<?php

declare(strict_types=1);

namespace ForumRewrite;

final class PresentationSlotRegistry
{
    /** @var array<string, array{fallback:string, choices:list<string>}> */
    private const SLOTS = [
        'navigation' => ['fallback' => 'forum', 'choices' => ['forum', 'qdb']],
        'boardCard' => ['fallback' => 'thread', 'choices' => ['thread', 'quote']],
        'compose' => ['fallback' => 'thread', 'choices' => ['thread', 'qdb']],
        'about' => ['fallback' => 'default', 'choices' => ['default', 'chouse']],
        'editorial' => ['fallback' => 'zenmemes', 'choices' => ['zenmemes', 'boston', 'qdb']],
        'brandedStylesheet' => ['fallback' => 'site', 'choices' => ['site', 'chouse', 'qdb']],
    ];

    /** @return array<string, array{fallback:string, choices:list<string>}> */
    public static function all(): array
    {
        return self::SLOTS;
    }

    /** @param array<string, mixed> $profile */
    public static function resolve(array $profile, string $slot): string
    {
        if (!isset(self::SLOTS[$slot])) {
            throw new \InvalidArgumentException('Unknown presentation slot: ' . $slot);
        }

        $selection = $profile['presentationSlots'][$slot] ?? self::SLOTS[$slot]['fallback'];

        return is_string($selection) && in_array($selection, self::SLOTS[$slot]['choices'], true)
            ? $selection
            : self::SLOTS[$slot]['fallback'];
    }

    /** @param array<string, mixed> $selections */
    public static function validate(array $selections, string $profileId): void
    {
        foreach ($selections as $slot => $selection) {
            if (!isset(self::SLOTS[$slot]) || !is_string($selection) || !in_array($selection, self::SLOTS[$slot]['choices'], true)) {
                throw new \RuntimeException("Site profile {$profileId} has an invalid presentation slot selection.");
            }
        }
    }
}
