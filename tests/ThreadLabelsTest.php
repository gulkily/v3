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

    public function testSelfTagsMatchesTheSharedExamples(): void
    {
        $examples = self::selfTagExamples();
        assertTrue(count($examples) >= 15, 'examples file parsed');
        foreach ($examples as $example) {
            assertSame($example['tags'], ThreadLabels::selfTags($example['body']), $example['name']);
        }
    }

    public function testSelfTagsKeepsEveryLabelOnAHashtagLineThatFromBodyFinds(): void
    {
        foreach (["#bug #ux\n#bug", "intro\n#needs-review\nmore", "#meta"] as $body) {
            assertSame(ThreadLabels::fromBody($body), ThreadLabels::selfTags($body), $body);
        }
    }

    /** @return list<array{name:string,tags:list<string>,body:string}> */
    public static function selfTagExamples(): array
    {
        $chunks = explode("\n%%%\n", (string) file_get_contents(__DIR__ . '/fixtures/hashtag_self_tags_examples.txt'));
        array_shift($chunks);
        $examples = [];
        foreach ($chunks as $chunk) {
            if (trim($chunk) === '' || trim($chunk) === '%%%') continue;
            [$head, $body] = array_pad(explode("\n\n", $chunk, 2), 2, '');
            preg_match('/^Name: (.*)$/m', $head, $name);
            preg_match('/^Tags:(.*)$/m', $head, $tags);
            $examples[] = [
                'name' => $name[1] ?? '?',
                'tags' => array_values(array_filter(explode(' ', trim($tags[1] ?? '')), static fn ($t) => $t !== '')),
                'body' => $body,
            ];
        }

        return $examples;
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
