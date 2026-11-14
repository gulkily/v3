<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Support\ThreadLabels;

final class ThreadLabelsTest
{
    public function testFromBodyKeepsTheWriteSideRule(): void
    {
        assertSame(['bug', 'ux'], ThreadLabels::fromBody("#bug #ux\n#bug"));
        assertSame(['needs-review'], ThreadLabels::fromBody("> #quoted\nsee #needs-review and #Bug or a#b"));
        assertSame([], ThreadLabels::fromBody('no tags here'));
    }

    public function testIsTagOnlyBodies(): void
    {
        $reply = static fn (string $body, array $extra = []): array => ['body' => $body] + $extra;
        $cases = [
            ['#bug #ux', true],
            ["  #bug\n\n#ux  ", true],
            ["> quote\n#bug", true],
            ['#bug see above', false],
            ["> quote\n#bug\nreally", false],
            ['> quote only', false],
            ['#Bug', false],            // not a valid label, so it is text
            ['#bug #Bug', false],
            ['#bug, #ux', false],
            ['', false],
            ['https://example.com/a.png #bug', false],
        ];
        foreach ($cases as [$body, $expected]) {
            assertSame($expected, ThreadLabels::isTagOnly($reply($body)), 'body: ' . json_encode($body));
        }
        assertSame(false, ThreadLabels::isTagOnly($reply('#bug', ['subject' => 'A title'])));
        assertSame(false, ThreadLabels::isTagOnly($reply('#bug', ['agent_response_json' => '{}'])));
        assertSame(false, ThreadLabels::isTagOnly($reply('#bug', ['retracted_at' => '2026-11-14T00:00:00Z'])));
        assertSame(true, ThreadLabels::isTagOnly($reply('#bug', ['subject' => null, 'retracted_at' => null])));
    }
}
