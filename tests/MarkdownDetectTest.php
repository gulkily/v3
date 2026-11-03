<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

final class MarkdownDetectTest
{
    private function run(string $js): mixed
    {
        $script = "const fs=require('fs');const vm=require('vm');global.window={};global.document=undefined;"
            . "vm.runInThisContext(fs.readFileSync(process.argv[1],'utf8'));" . $js;
        exec(sprintf('node -e %s %s 2>&1', escapeshellarg($script), escapeshellarg(__DIR__ . '/../public/assets/markdown_detect.js')), $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('markdown_detect.js failed: ' . implode("\n", $output));
        }
        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testRecognizesClearMarkdownShapes(): void
    {
        $drafts = ["# Title", "## Notes\nbody", "- one\n- two", "1. first", "> quoted", "```\ncode\n```", "| a | b |", "some **bold** text", "an *emphasis* here", "see [docs](https://example.com)", "run `./v3 deploy`"];
        $result = $this->run('process.stdout.write(JSON.stringify(' . json_encode($drafts) . '.map(window.ForumMarkdownDetect.looksLikeMarkdown)));');
        assertSame(array_fill(0, count($drafts), true), $result);
    }

    public function testLeavesOrdinaryTextAlone(): void
    {
        $drafts = ['', 'just a sentence.', '2 * 3 * 4 = 24', 'a*b*c', '#v3 #changelog', 'https://zenmemes.com/threads/x', "line one\nline two", '-', 'price is $5 - cheap', 'I <3 *', 'email me at a_b@c.de'];
        $result = $this->run('process.stdout.write(JSON.stringify(' . json_encode($drafts) . '.map(window.ForumMarkdownDetect.looksLikeMarkdown)));');
        assertSame(array_fill(0, count($drafts), false), $result);
    }

    public function testManualChoiceSticksAndClearingResetsIt(): void
    {
        $js = <<<'JS'
const listeners = {};
const toggle = { checked: false, dataset: {}, addEventListener(t, f) { listeners['toggle:' + t] = f; } };
const body = { value: '', addEventListener(t, f) { listeners['body:' + t] = f; } };
const form = { querySelector(s) { return s.includes('checkbox') ? toggle : body; }, addEventListener(t, f) { listeners['form:' + t] = f; } };
global.setTimeout = (f) => f();
window.ForumMarkdownDetect.bind(form);
const seen = [];
body.value = '# Heading'; listeners['body:input'](); seen.push(toggle.checked);
toggle.checked = false; listeners['toggle:change'](); body.value = '# Heading\n- list'; listeners['body:input'](); seen.push(toggle.checked);
body.value = '# Again'; listeners['form:click']({ target: { closest: () => ({}) } }); seen.push(toggle.checked);
process.stdout.write(JSON.stringify(seen));
JS;
        assertSame([true, false, true], $this->run($js));
    }
}
