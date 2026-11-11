<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use DateTimeImmutable;
use DateTimeZone;
use ForumRewrite\Calendar\EventPhraseFill;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

/**
 * GET /api/read_event_phrase: what Fill puts into the composer's Event fields when
 * scripts run. It reads with the poster's own date (sent by the browser, checked to be
 * within a day of the server's UTC date) and writes nothing.
 */
final class EventPhraseApiController
{
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly FeatureFlagEvaluator $featureFlags,
        private readonly ?EventPhraseFill $fill = null,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function read(string $method, array $query): void
    {
        $headers = ['Cache-Control: no-store'];
        if ($method !== 'GET') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Use GET.'], 405, array_merge($headers, ['Allow: GET']));
            return;
        }
        if (!$this->featureFlags->isEnabled(FeatureFlagRegistry::EVENT_WHEN_ENABLED)) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Event phrases are off.'], 404, $headers);
            return;
        }

        $today = self::posterToday((string) ($query['today'] ?? ''));
        $fill = $this->fill ?? EventPhraseFillFactory::forRequest($this->routeServices, (string) ($query['time_zone'] ?? ''));
        $result = $fill->fill(
            (string) ($query['phrase'] ?? ''),
            $today,
            [
                'event_date' => (string) ($query['event_date'] ?? ''),
                'event_end_date' => (string) ($query['event_end_date'] ?? ''),
                'event_time' => (string) ($query['event_time'] ?? ''),
            ],
            (string) ($query['event_date_fixed'] ?? '') === '1'
        );

        $this->routeServices->sendJson([
            'status' => 'ok',
            'filled' => $result['filled'],
            'event_date' => $result['fields']['event_date'],
            'event_end_date' => $result['fields']['event_end_date'],
            'event_time' => $result['fields']['event_time'],
            'message' => $result['status'],
            'today' => $today,
        ], 200, $headers);
    }

    /** The browser's local date when it is plausible (every time zone is within a day of UTC), else the UTC date. */
    public static function posterToday(string $claimed, ?DateTimeImmutable $now = null): string
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $utc = $now->format('Y-m-d');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $claimed, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $claimed) {
            return $utc;
        }
        $days = abs((int) (($date->getTimestamp() - (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->getTimestamp()) / 86400));

        return $days <= 1 ? $claimed : $utc;
    }
}
