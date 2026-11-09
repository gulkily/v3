<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

final class SourceViewerTest
{
    /** @return mixed */
    private function evaluate(string $expression)
    {
        $script = <<<'NODE'
const fs = require("fs");
global.window = { location: { href: "https://forum.test/forte/activity/", origin: "https://forum.test" } };
global.document = { addEventListener() {} };
eval(fs.readFileSync(process.argv[1], "utf8"));
const viewer = window.ForumSourceViewer;
console.log(JSON.stringify(EXPRESSION));
NODE;
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg(str_replace('EXPRESSION', $expression, $script)),
            escapeshellarg(__DIR__ . '/../public/assets/source_viewer.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('source viewer helper failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testParsesRecordFieldsAndBody(): void
    {
        $parsed = $this->evaluate('viewer.parseFields("Post-ID: root-001\nAuthor-Identity-ID: openpgp:1fc2\nBody-Format: plain\n\nFirst line.\nSecond line.\n")');
        assertSame([
            ['key' => 'Post-ID', 'value' => 'root-001', 'items' => []],
            ['key' => 'Author-Identity-ID', 'value' => 'openpgp:1fc2', 'items' => []],
            ['key' => 'Body-Format', 'value' => 'plain', 'items' => []],
        ], $parsed['fields']);
        assertSame("First line.\nSecond line.", $parsed['body']);
    }

    public function testCollectsCommitFileListUnderEmptyField(): void
    {
        $parsed = $this->evaluate('viewer.parseFields("Commit: abc\nSubject: Init\nFiles:\nadded README.md\nmodified records/posts/a.txt\n")');
        assertSame(['added README.md', 'modified records/posts/a.txt'], $parsed['fields'][2]['items']);
        assertSame('', $parsed['body']);
    }

    public function testFreeTextIsNotTreatedAsRecord(): void
    {
        assertSame(null, $this->evaluate('viewer.parseFields("just some text\nmore")'));
        assertSame(null, $this->evaluate('viewer.parseFields("Key: value\nnot a field")'));
    }

    public function testRecognizesOnlySameOriginSourceLinks(): void
    {
        assertSame([true, true, true, false, false, false], $this->evaluate('[
            "/source/current/records/posts/root-001.txt",
            "/source/blob/2a8401e22c30/records/x.txt.asc",
            "https://forum.test/source/commits/2a8401e22c30e58a",
            "/source/commits/not-a-sha",
            "https://elsewhere.test/source/current/a.txt",
            "/forte?selected=root-001"
        ].map(viewer.isSourceHref)'));
        assertSame(
            ['kind' => 'file', 'commit' => '2a8401e22c30', 'path' => 'records/public keys/k.asc', 'name' => 'k.asc'],
            $this->evaluate('viewer.describe("/source/blob/2a8401e22c30/records/public%20keys/k.asc")'),
        );
    }
}
