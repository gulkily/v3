<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';
require_once __DIR__ . '/ThreadLabelsTest.php';

/** public/assets/hashtag_tags.js must give the same tags as ThreadLabels::selfTags. */
final class HashtagTagsTest
{
    private function run(string $js): mixed
    {
        $script = "const fs=require('fs');const vm=require('vm');global.window={};global.document=undefined;"
            . "vm.runInThisContext(fs.readFileSync(process.argv[1],'utf8'));" . $js;
        exec(sprintf('node -e %s %s 2>&1', escapeshellarg($script), escapeshellarg(__DIR__ . '/../public/assets/hashtag_tags.js')), $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('hashtag_tags.js failed: ' . implode("\n", $output));
        }
        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testMatchesTheSharedExamples(): void
    {
        $examples = ThreadLabelsTest::selfTagExamples();
        $bodies = array_map(static fn (array $example): string => $example['body'], $examples);
        $result = $this->run('process.stdout.write(JSON.stringify(' . json_encode($bodies) . '.map(window.ForumHashtagTags.selfTags)));');
        foreach ($examples as $index => $example) {
            assertSame($example['tags'], $result[$index], $example['name']);
        }
    }

    public function testLineFollowsTheDraftAndHidesWhenEmpty(): void
    {
        $js = <<<'JS'
const listeners = {};
const list = { textContent: '' };
const line = { hidden: false, dataset: {}, querySelector() { return list; } };
const body = { value: 'gallery view #meta', addEventListener(t, f) { listeners['body:' + t] = f; } };
const form = { querySelector(s) { return s.includes('textarea') ? body : line; }, addEventListener(t, f) { listeners['form:' + t] = f; } };
global.setTimeout = (f) => f();
window.ForumHashtagTags.bind(form);
const seen = [[list.textContent, line.hidden]];
body.value = 'I like #zen posts.'; listeners['body:input'](); seen.push([list.textContent, line.hidden]);
body.value = 'Text\n\n#issue #meta'; listeners['form:click']({ target: { closest: () => ({}) } }); seen.push([list.textContent, line.hidden]);
process.stdout.write(JSON.stringify(seen));
JS;
        assertSame([['#meta', false], ['', true], ['#issue #meta', false]], $this->run($js));
    }
}
