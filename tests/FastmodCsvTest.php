<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Scoring\FastPostScorer;
use ForumRewrite\Scoring\FastScoreResult;
use ForumRewrite\Scoring\SqliteFastScoreStore;
use ForumRewrite\Llm\TextChatProvider;

final class FastmodCsvTest
{
    public function testOneCallReturnsIdentifiedScoresAndNoSyntheticModerationValue(): void
    {
        $provider = new class implements TextChatProvider {
            public int $calls = 0;
            public function completeTextChat(array $messages, array $options = []): array
            {
                $this->calls++;
                return ['response_text' => " 0.9, 0.2\n"];
            }
        };
        $result = (new FastPostScorer($provider, 'prompt', ['meta', 'custom'], 'rev'))->score([]);
        assertSame(1, $provider->calls);
        assertSame(['meta' => 0.9, 'custom' => 0.2], $result['scores']);
        assertSame(null, $result['probability']);
        assertSame('rev', $result['rubric_revision']);
    }

    public function testMalformedOutputNeverProducesPartialResults(): void
    {
        foreach (['', '0.5', '0.2,0.3,0.4', '0.2,NaN', '0.2,1e999', '0.2,-1', '0.2,1.1', '0.2,', '```0.2,0.3```', '0.2,0.3 explanation'] as $text) {
            try {
                FastPostScorer::parseCsv($text, ['meta', 'moderation']);
                throw new LogicException('Malformed response accepted: ' . $text);
            } catch (InvalidArgumentException) {
                assertSame(true, true);
            }
        }
        assertSame(['meta' => 0.0, 'moderation' => 1.0], FastPostScorer::parseCsv('0, 1', ['meta', 'moderation']));
    }

    public function testPrivateStoreMigrationPreservesLegacyAndIdentifiedScores(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE post_fast_scores (post_id TEXT NOT NULL, content_hash TEXT NOT NULL, rubric_revision TEXT NOT NULL, status TEXT NOT NULL, probability REAL NULL, source TEXT NOT NULL, signals_json TEXT NOT NULL, failure_code TEXT NULL, failure_message TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, PRIMARY KEY (post_id, content_hash, rubric_revision))');
        $pdo->exec("INSERT INTO post_fast_scores VALUES ('old','hash','oldrev','scored',0.7,'llm','[]',NULL,NULL,'2020','2020')");
        $store = new SqliteFastScoreStore($pdo);
        $old = $store->find('old', 'hash', 'oldrev');
        assertSame(true, $old['legacy']);
        assertSame(['moderation' => 0.7], $old['scores']);
        $new = $store->save('new', 'hash', 'rev', FastScoreResult::scoredSet(['meta' => 0.8], 'llm'));
        assertSame(false, $new['legacy']);
        assertSame(['meta' => 0.8], $new['scores']);
        assertSame(null, $new['probability']);
        new SqliteFastScoreStore($pdo);
        assertSame($new, $store->find('new', 'hash', 'rev'));
    }
}
