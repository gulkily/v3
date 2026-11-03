<?php

declare(strict_types=1);

use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\PostFormatRecordParser;
use ForumRewrite\ReadModel\IncrementalReadModelUpdater;
use ForumRewrite\ReadModel\ReadModelBuilder;

require __DIR__ . '/../autoload.php';

final class PostFormatRecordTest
{
    private const ACTOR = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public static function record(string $postId, string $createdAt, string $format, string $hex = 'a1b2c3d4'): string
    {
        $stamp = preg_replace('/\D/', '', $createdAt);
        return "Record-ID: post-format-{$postId}-{$stamp}-{$hex}\nCreated-At: {$createdAt}\nPost-ID: {$postId}\nBody-Format: {$format}\nActor-Identity-ID: " . self::ACTOR . "\n\n";
    }

    public function testParsesTheCanonicalRecordAndRejectsVariants(): void
    {
        $parser = new PostFormatRecordParser();
        $record = $parser->parse(self::record('root-001', '2026-11-03T18:00:00Z', 'markdown'));
        assertSame('post-format-root-001-20261103180000-a1b2c3d4', $record->recordId);
        assertSame('markdown', $record->bodyFormat);
        assertSame(self::ACTOR, $record->actorIdentityId);

        $bad = [
            str_replace('Body-Format: markdown', 'Body-Format: html', self::record('root-001', '2026-11-03T18:00:00Z', 'markdown')),
            str_replace('20261103180000', '20261103180001', self::record('root-001', '2026-11-03T18:00:00Z', 'markdown')),
            self::record('root-001', '2026-11-03T18:00:00Z', 'markdown') . 'body',
            str_replace("Post-ID: root-001\nBody-Format: markdown", "Body-Format: markdown\nPost-ID: root-001", self::record('root-001', '2026-11-03T18:00:00Z', 'markdown')),
            str_replace(self::ACTOR, 'openpgp:XYZ', self::record('root-001', '2026-11-03T18:00:00Z', 'markdown')),
        ];
        foreach ($bad as $contents) {
            try {
                $parser->parse($contents);
                throw new RuntimeException('Accepted an invalid post-format record: ' . $contents);
            } catch (CanonicalRecordParseException) {
            }
        }
    }

    public function testRebuildAndIncrementalAgreeOnTheLatestFormat(): void
    {
        [$root, $rebuildDb, $incrementalDb] = $this->environment();
        $repository = new CanonicalRecordRepository($root);
        (new ReadModelBuilder($root, $incrementalDb, $repository))->rebuild();
        $updater = new IncrementalReadModelUpdater($incrementalDb, $root);

        // root-001 has no Body-Format header: switch it to Markdown, back to plain, then Markdown again.
        foreach ([['2026-11-03T18:00:00Z', 'markdown', 'aaaaaaa1'], ['2026-11-03T18:01:00Z', 'plain', 'aaaaaaa2'], ['2026-11-03T18:02:00Z', 'markdown', 'aaaaaaa3']] as [$at, $format, $hex]) {
            $this->write($root, self::record('root-001', $at, $format, $hex));
            $updater->applyPostFormatWrite('root-001', 'commit');
        }
        $this->write($root, self::record('reply-001', '2026-11-03T18:03:00Z', 'markdown', 'bbbbbbb1'));
        $updater->applyPostFormatWrite('reply-001', 'commit');
        (new ReadModelBuilder($root, $rebuildDb, new CanonicalRecordRepository($root)))->rebuild();

        foreach ([$rebuildDb, $incrementalDb] as $db) {
            $pdo = new PDO('sqlite:' . $db);
            $formats = $pdo->query("SELECT post_id, body_format FROM posts WHERE post_id IN ('root-001','reply-001') ORDER BY post_id")->fetchAll(PDO::FETCH_KEY_PAIR);
            assertSame(['reply-001' => 'markdown', 'root-001' => 'markdown'], $formats);
        }
        $rows = static fn (string $db): array => (new PDO('sqlite:' . $db))->query('SELECT post_id, body_format FROM posts ORDER BY post_id')->fetchAll(PDO::FETCH_KEY_PAIR);
        assertSame($rows($rebuildDb), $rows($incrementalDb));
        $preview = static fn (string $db): array => (new PDO('sqlite:' . $db))->query('SELECT root_post_id, body_preview FROM threads ORDER BY root_post_id')->fetchAll(PDO::FETCH_KEY_PAIR);
        assertSame($preview($rebuildDb), $preview($incrementalDb));
    }

    public function testInvalidAndUnknownRecordsAreIgnored(): void
    {
        [$root, $db] = $this->environment();
        $this->write($root, self::record('missing-post', '2026-11-03T18:00:00Z', 'markdown'));
        @mkdir($root . '/records/post-formats', 0777, true);
        file_put_contents($root . '/records/post-formats/post-format-root-001-20261103180000-zzzzzzzz.txt', 'garbage');
        (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
        $format = (new PDO('sqlite:' . $db))->query("SELECT body_format FROM posts WHERE post_id = 'root-001'")->fetchColumn();
        assertSame(null, $format);
    }

    private function write(string $root, string $contents): void
    {
        $record = (new PostFormatRecordParser())->parse($contents);
        @mkdir($root . '/records/post-formats', 0777, true);
        file_put_contents($root . '/records/post-formats/' . $record->recordId . '.txt', $contents);
    }

    /** @return array{string, string, string} */
    private function environment(): array
    {
        $root = sys_get_temp_dir() . '/post-format-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        exec('cp -r ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1') . '/. ' . escapeshellarg($root));
        exec('cd ' . escapeshellarg($root) . ' && git init -q && git add -A && git -c user.name=t -c user.email=t@t commit -qm init');
        return [$root, $root . '.rebuild.sqlite3', $root . '.incremental.sqlite3'];
    }
}
