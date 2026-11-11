<?php

declare(strict_types=1);

use ForumRewrite\Calendar\EventPhraseFill;
use ForumRewrite\Calendar\EventPhraseLlmReader;
use ForumRewrite\Calendar\EventPhraseReader;
use ForumRewrite\Calendar\EventPhraseReading;
use ForumRewrite\Llm\StructuredChatProvider;

require __DIR__ . '/../autoload.php';

/** The LLM fallback: answers are checked, failures change nothing, and it runs only when the built-in reader cannot read the day. */
final class EventPhraseLlmReaderTest
{
    private const TODAY = '2026-11-10';

    public function testReadAnswerFillsFields(): void
    {
        $reader = new EventPhraseLlmReader($this->provider(['status' => 'read', 'date' => '2026-11-27', 'end_date' => '2026-11-29', 'time' => '']));
        $reading = $reader->read('the weekend after Thanksgiving', self::TODAY, 'America/New_York');
        eventPhraseLlmAssertSame(['2026-11-27', '2026-11-29', null, 'llm'], [$reading->date, $reading->endDate, $reading->time, $reading->source]);
    }

    public function testRequestCarriesTodayTimeZoneAndPhrase(): void
    {
        $provider = $this->provider(['status' => 'unclear', 'date' => '', 'end_date' => '', 'time' => '']);
        (new EventPhraseLlmReader($provider))->read('after the holidays', self::TODAY, 'Asia/Tokyo');
        $user = $provider->messages[1]['content'];
        eventPhraseLlmAssertSame("Today: Tuesday, 2026-11-10\nTime zone: Asia/Tokyo\nPhrase: after the holidays", $user);
        eventPhraseLlmAssertSame('event_phrase', $provider->schemaName);
    }

    public function testUnclearAndMalformedAnswersReadNothing(): void
    {
        foreach ([
            ['status' => 'unclear', 'date' => '', 'end_date' => '', 'time' => ''],
            ['status' => 'read', 'date' => 'Nov 27', 'end_date' => '', 'time' => ''],
            ['status' => 'read', 'date' => '2026-02-30', 'end_date' => '', 'time' => ''],
            ['status' => 'read', 'date' => '2026-11-27', 'end_date' => '2026-11-20', 'time' => ''],
            ['status' => 'read', 'date' => '2026-11-27', 'end_date' => 'soon', 'time' => ''],
            ['status' => 'read', 'date' => '2026-11-27', 'end_date' => '', 'time' => '7pm'],
            ['status' => 'read', 'date' => '2026-11-27', 'end_date' => '', 'time' => '24:00'],
            ['status' => 'read', 'date' => '2035-01-01', 'end_date' => '', 'time' => ''],
            ['status' => 'read', 'date' => '2019-01-01', 'end_date' => '', 'time' => ''],
            [],
        ] as $answer) {
            $reading = (new EventPhraseLlmReader($this->provider($answer)))->read('something', self::TODAY);
            eventPhraseLlmAssertSame([null, EventPhraseReader::UNREAD_DATE], [$reading->date, $reading->reason], json_encode($answer));
        }
    }

    public function testProviderFailureReadsNothing(): void
    {
        $failing = new class implements StructuredChatProvider {
            public function completeStructuredChat(string $schemaName, array $messages, array $jsonSchema, array $options = []): array
            {
                throw new RuntimeException('timeout');
            }
        };
        $reading = (new EventPhraseLlmReader($failing))->read('something', self::TODAY);
        eventPhraseLlmAssertSame([null, 'Could not read that right now.'], [$reading->date, $reading->reason]);

        $long = (new EventPhraseLlmReader($this->provider(['status' => 'read', 'date' => '2026-11-27', 'end_date' => '', 'time' => ''])))->read(str_repeat('x', 121), self::TODAY);
        eventPhraseLlmAssertSame(null, $long->date);
    }

    public function testFallbackRunsOnlyWhenTheDayIsUnread(): void
    {
        $calls = [];
        $fill = new EventPhraseFill(new EventPhraseReader(), static function (string $phrase, string $today) use (&$calls): EventPhraseReading {
            $calls[] = $phrase;
            return EventPhraseReading::read('2026-11-27', null, '18:00', 'llm');
        });
        $fill->fill('tomorrow', self::TODAY, [], false);
        $fill->fill('at 7pm', self::TODAY, [], false);
        $fill->fill('Friday at 25:00', self::TODAY, [], false);
        $result = $fill->fill(' the day after Thanksgiving ', self::TODAY, [], false);
        eventPhraseLlmAssertSame(['the day after Thanksgiving'], $calls);
        eventPhraseLlmAssertSame(['event_date' => '2026-11-27', 'event_end_date' => '', 'event_time' => '18:00'], $result['fields']);
    }

    /** @param array<string, mixed> $decoded */
    private function provider(array $decoded): StructuredChatProvider
    {
        return new class ($decoded) implements StructuredChatProvider {
            /** @var list<array{role:string, content:string}> */
            public array $messages = [];
            public string $schemaName = '';

            /** @param array<string, mixed> $decoded */
            public function __construct(private readonly array $decoded)
            {
            }

            public function completeStructuredChat(string $schemaName, array $messages, array $jsonSchema, array $options = []): array
            {
                $this->schemaName = $schemaName;
                $this->messages = $messages;

                return ['provider' => 'test', 'provider_model' => 'test', 'provider_request_id' => null, 'decoded' => $this->decoded, 'raw_response' => []];
            }
        };
    }
}

function eventPhraseLlmAssertSame(mixed $expected, mixed $actual, string $context = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($context !== '' ? $context . ': ' : '') . 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
