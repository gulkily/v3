<?php

declare(strict_types=1);

use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\PostRecordParser;

require __DIR__ . '/../autoload.php';

final class EventEndDateParserTest
{
    public function testRootWithoutEndDateIsOneDay(): void
    {
        $record = $this->parse($this->root("Event-Date: 2026-11-10\n"));

        eventEndDateAssertSame('2026-11-10', $record->eventDate);
        eventEndDateAssertSame(null, $record->eventEndDate);
    }

    public function testRootWithoutAnyEventHeadersIsUnchanged(): void
    {
        $record = $this->parse($this->root(''));

        eventEndDateAssertSame(null, $record->eventDate);
        eventEndDateAssertSame(null, $record->eventEndDate);
    }

    public function testRootWithSpanParsesBothDays(): void
    {
        $record = $this->parse($this->root("Event-Date: 2026-11-10\nEvent-End-Date: 2026-11-12\n"));

        eventEndDateAssertSame('2026-11-10', $record->eventDate);
        eventEndDateAssertSame('2026-11-12', $record->eventEndDate);
    }

    public function testEndDateEqualToStartIsAccepted(): void
    {
        $record = $this->parse($this->root("Event-Date: 2026-11-10\nEvent-End-Date: 2026-11-10\n"));

        eventEndDateAssertSame('2026-11-10', $record->eventEndDate);
    }

    public function testEndBeforeStartIsRejected(): void
    {
        $this->assertRejected(
            $this->root("Event-Date: 2026-11-10\nEvent-End-Date: 2026-11-09\n"),
            'Event-End-Date must not be before Event-Date.',
        );
    }

    public function testEndWithoutStartIsRejected(): void
    {
        $this->assertRejected($this->root("Event-End-Date: 2026-11-09\n"), 'Event-End-Date requires Event-Date.');
    }

    public function testMalformedEndDateIsRejected(): void
    {
        $this->assertRejected(
            $this->root("Event-Date: 2026-11-10\nEvent-End-Date: 2026-11-31\n"),
            'Event-End-Date must be a valid calendar date.',
        );
        $this->assertRejected(
            $this->root("Event-Date: 2026-11-10\nEvent-End-Date: Nov 12\n"),
            'Event-End-Date must use the format YYYY-MM-DD like 2026-11-01.',
        );
    }

    public function testEndDateOnReplyIsRejected(): void
    {
        $reply = "Post-ID: r1\nCreated-At: 2026-11-04T12:00:00Z\nBoard-Tags: general\nThread-ID: t1\nParent-ID: t1\n"
            . "Event-End-Date: 2026-11-12\n\nreply\n";

        $this->assertRejected($reply, 'Replies must not include typed root header: Event-End-Date');
    }

    public function testStartDateMessagesAreUnchanged(): void
    {
        $this->assertRejected($this->root("Event-Date: 2026-02-30\n"), 'Event-Date must be a valid calendar date.');
    }

    private function root(string $eventHeaders): string
    {
        return "Post-ID: t1\nCreated-At: 2026-11-04T12:00:00Z\nBoard-Tags: general\nSubject: Couch\n"
            . $eventHeaders . "\nhello\n";
    }

    private function parse(string $contents): \ForumRewrite\Canonical\PostRecord
    {
        return (new PostRecordParser())->parse($contents);
    }

    private function assertRejected(string $contents, string $message): void
    {
        try {
            $this->parse($contents);
        } catch (CanonicalRecordParseException $exception) {
            eventEndDateAssertSame($message, $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected the record to be rejected with: ' . $message);
    }
}

function eventEndDateAssertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Failed asserting identical values. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
