<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
use ForumRewrite\Retraction\RetractionSweep;
use ForumRewrite\Llm\LlmExchangeRecorder;
use ForumRewrite\Llm\ExchangeDependencies;

final class RetractionSweepTest
{
    public function fixture(): array
    {
        $root = sys_get_temp_dir() . '/sweep-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0700, true);
        mkdir($root . '/records/post-retractions');
        file_put_contents($root . '/records/posts/target.txt', "Post-ID: target\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\nOriginal prose\n");
        file_put_contents($root . '/records/post-retractions/post-retraction-target.txt', "Record-ID: post-retraction-target\nCreated-At: 2026-10-14T12:00:00Z\nPost-ID: target\nOperation: retract\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\n");
        $pdo = new PDO('sqlite:' . $root . '/exchanges.sqlite3');
        $recorder = new LlmExchangeRecorder($pdo);
        foreach ([['target'], ['other', 'target'], ['other'], null] as $ids) {
            $ctx = ['post_id' => $ids[0] ?? 'unknown', 'arbitrary' => 'SECRET-CONTEXT'];
            if ($ids !== null) $ctx['exchange_dependencies'] = ExchangeDependencies::manifest($ids);
            $recorder->record($ctx, ['body' => 'SECRET-REQUEST'], ['body' => 'SECRET-RESPONSE'], 'completed', 1, ['message' => 'SECRET-ERROR']);
        }
        return [$root, $pdo, new RetractionSweep($root, $root . '/exchanges.sqlite3', $root . '/state.json')];
    }

    public function testPreviewClassifiesWithoutMutation(): void
    {
        [$root, $pdo, $sweep] = $this->fixture();
        $before = hash_file('sha256', $root . '/exchanges.sqlite3');
        $result = $sweep->preview();
        assertSame(2, $result['affected']);
        assertSame(1, $result['uncertain']);
        assertSame(4, $result['examined']);
        assertSame($before, hash_file('sha256', $root . '/exchanges.sqlite3'));
        assertSame(false, file_exists($root . '/state.json'));
        assertSame(true, $sweep->preview(1)['remaining']);
    }
}
