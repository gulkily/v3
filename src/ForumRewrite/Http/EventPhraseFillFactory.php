<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Calendar\EventPhraseFill;
use ForumRewrite\Calendar\EventPhraseLlmReader;
use ForumRewrite\Calendar\EventPhraseReader;
use ForumRewrite\Calendar\EventPhraseReading;
use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Support\PrivateConfig;

/**
 * Fill for the current request. The LLM fallback is offered only to a signed-in approved
 * member and only when a provider is configured; everyone else gets the built-in reader.
 * The provider is built lazily, so a phrase the built-in reader handles costs nothing.
 */
final class EventPhraseFillFactory
{
    /** @param (\Closure(): ?EventPhraseLlmReader)|null $llmReader for tests; defaults to the private config */
    public static function forRequest(RouteServices $routeServices, string $timeZone = '', ?\Closure $llmReader = null): EventPhraseFill
    {
        if (!self::viewerIsApproved($routeServices)) {
            return new EventPhraseFill();
        }
        $llmReader ??= static fn (): ?EventPhraseLlmReader => EventPhraseLlmReader::fromPrivateConfig(PrivateConfig::load($routeServices->projectRoot()));
        $timeZone = self::validTimeZone($timeZone);

        return new EventPhraseFill(new EventPhraseReader(), static function (string $phrase, string $today) use ($llmReader, $timeZone): EventPhraseReading {
            $reader = $llmReader();

            return $reader === null
                ? EventPhraseReading::unread(EventPhraseReader::UNREAD_DATE)
                : $reader->read($phrase, $today, $timeZone);
        });
    }

    private static function viewerIsApproved(RouteServices $routeServices): bool
    {
        try {
            $identityId = $routeServices->viewerSessions()->identityId();
            if ($identityId === '') {
                return false;
            }
            $profile = ProfileRepository::byIdentityId($routeServices->pdo(), $identityId);

            return $profile !== null && (int) ($profile['is_approved'] ?? 0) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function validTimeZone(string $timeZone): string
    {
        return $timeZone !== '' && in_array($timeZone, \DateTimeZone::listIdentifiers(), true) ? $timeZone : '';
    }
}
