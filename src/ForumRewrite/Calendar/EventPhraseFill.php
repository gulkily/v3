<?php

declare(strict_types=1);

namespace ForumRewrite\Calendar;

/**
 * What Fill puts into the composer's Event fields for a phrase. A fixed Date (the
 * calendar day page) is never changed: only Until and Time are filled, and only when
 * they fit that day. An unread phrase leaves every field as it was.
 */
final class EventPhraseFill
{
    public function __construct(private readonly EventPhraseReader $reader = new EventPhraseReader())
    {
    }

    /**
     * @param array{event_date?:string, event_end_date?:string, event_time?:string} $fields
     * @return array{fields: array{event_date:string, event_end_date:string, event_time:string}, status:string, filled:bool}
     */
    public function fill(string $phrase, string $today, array $fields, bool $dateFixed): array
    {
        $current = [
            'event_date' => (string) ($fields['event_date'] ?? ''),
            'event_end_date' => (string) ($fields['event_end_date'] ?? ''),
            'event_time' => (string) ($fields['event_time'] ?? ''),
        ];
        $reading = $this->reader->read($phrase, $today);
        if (!$reading->isRead()) {
            return ['fields' => $current, 'status' => (string) $reading->reason, 'filled' => false];
        }

        return $this->apply($reading, $current, $dateFixed);
    }

    /**
     * @param array{event_date:string, event_end_date:string, event_time:string} $current
     * @return array{fields: array{event_date:string, event_end_date:string, event_time:string}, status:string, filled:bool}
     */
    public function apply(EventPhraseReading $reading, array $current, bool $dateFixed): array
    {
        if (!$dateFixed || $current['event_date'] === '') {
            return [
                'fields' => [
                    'event_date' => (string) $reading->date,
                    'event_end_date' => (string) ($reading->endDate ?? ''),
                    'event_time' => (string) ($reading->time ?? ''),
                ],
                'status' => '',
                'filled' => true,
            ];
        }

        $fixed = $current['event_date'];
        $endDate = $reading->endDate !== null && $reading->endDate > $fixed ? $reading->endDate : '';
        $sameDay = $reading->date === $fixed;

        return [
            'fields' => [
                'event_date' => $fixed,
                'event_end_date' => $endDate !== '' ? $endDate : $current['event_end_date'],
                'event_time' => $reading->time ?? $current['event_time'],
            ],
            'status' => $sameDay ? '' : 'The date stays ' . $fixed . ' on this page.',
            'filled' => true,
        ];
    }
}
