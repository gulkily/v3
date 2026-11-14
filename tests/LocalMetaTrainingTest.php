<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Scoring\FastScoreContextFactory;
use ForumRewrite\Scoring\LocalMetaFiles;
use ForumRewrite\Scoring\LocalMetaModel;
use ForumRewrite\Scoring\LocalMetaTraining;
use ForumRewrite\Scoring\LocalMetaTrainingRefused;

final class LocalMetaTrainingTest
{
    /** Read model with one root per row: [post_id, subject, body, thread labels]. */
    public static function readModel(array $roots): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE posts (post_id TEXT PRIMARY KEY, thread_id TEXT, subject TEXT, body TEXT, board_tags_json TEXT NOT NULL DEFAULT '[]', post_tags_json TEXT NOT NULL DEFAULT '[]', is_hidden INTEGER NOT NULL DEFAULT 0, retracted_at TEXT NULL)");
        $pdo->exec('CREATE TABLE threads (root_post_id TEXT PRIMARY KEY, thread_labels_json TEXT NOT NULL)');
        $post = $pdo->prepare('INSERT INTO posts (post_id, thread_id, subject, body) VALUES (?, ?, ?, ?)');
        $thread = $pdo->prepare('INSERT INTO threads VALUES (?, ?)');
        foreach ($roots as [$id, $subject, $body, $labels]) {
            $post->execute([$id, $id, $subject, $body]);
            $thread->execute([$id, json_encode($labels)]);
        }
        $pdo->exec("INSERT INTO posts (post_id, thread_id, subject, body) VALUES ('reply-1', 'support-0', NULL, 'bug bug bug')");
        return $pdo;
    }

    /** Score store with stored LLM meta scores: post_id => [subject, body, meta score]. */
    public static function scoreStore(array $scores, array $suppressed = []): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE post_fast_scores (post_id TEXT, content_hash TEXT, rubric_revision TEXT, status TEXT, source TEXT, scores_json TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE fastmod_meta (post_id TEXT, suppressed INTEGER)');
        $hasher = new FastScoreContextFactory(static fn (string $_): ?array => null);
        $insert = $pdo->prepare("INSERT INTO post_fast_scores VALUES (?, ?, 'r1', 'scored', 'llm', ?, '2026-11-01')");
        foreach ($scores as $id => [$subject, $body, $meta]) {
            $insert->execute([$id, $hasher->contentHashForPost(['post_id' => $id, 'subject' => $subject, 'body' => $body]), json_encode(['meta' => $meta, 'moderation' => 0.1])]);
        }
        foreach ($suppressed as $id) $pdo->exec("INSERT INTO fastmod_meta VALUES ('$id', 1)");
        return $pdo;
    }

    /** @return array{0:PDO,1:PDO} 30 authored-meta support posts, 30 LLM-negative ordinary posts, extras. */
    public static function fixture(): array
    {
        $topics = ['button', 'theme', 'reply', 'page', 'login', 'calendar'];
        $hobbies = ['seals at the beach', 'a rap verse', 'protein folding', 'a hike on the cape', 'a podcast episode', 'a book on epistemology'];
        $roots = [];
        $scores = [];
        for ($i = 0; $i < 30; $i++) {
            $roots[] = ["support-$i", 'Bug: the ' . $topics[$i % 6] . ' is broken', 'Feature request, please fix the ' . $topics[($i + 1) % 6] . " on the site. Bug $i.", ['meta']];
            $subject = 'Notes on ' . $hobbies[$i % 6];
            $body = 'Some thoughts about ' . $hobbies[($i + 2) % 6] . " and life, part $i.";
            $roots[] = ["other-$i", $subject, $body, []];
            $scores["other-$i"] = [$subject, $body, 0.02];
        }
        $roots[] = ['llm-yes', 'The page crashes', 'Please fix this bug in the theme menu.', []];
        $scores['llm-yes'] = ['The page crashes', 'Please fix this bug in the theme menu.', 0.97];
        $roots[] = ['unsure', 'Thinking about forum design', 'Maybe a button?', []];
        $scores['unsure'] = ['Thinking about forum design', 'Maybe a button?', 0.5];
        $roots[] = ['stale-score', 'Edited since scoring', 'new text', []];
        $scores['stale-score'] = ['Edited since scoring', 'old text', 0.99];
        $roots[] = ['suppressed', 'Bug in my garden', 'Aphids.', ['meta']];
        $roots[] = ['russian', 'Баг: кнопка не работает', 'Пожалуйста, исправьте.', ['meta']];
        $roots[] = ['untagged', 'A quiet afternoon', 'Tea and a book.', []];
        return [self::readModel($roots), self::scoreStore($scores, ['suppressed'])];
    }

    public function testCollectsLabelsInPrecedenceOrder(): void
    {
        [$read, $scores] = self::fixture();
        ['examples' => $examples, 'counts' => $counts] = (new LocalMetaTraining($read, $scores))->collect(false);
        $byId = array_column($examples, null, 'post_id');
        assertSame('suppressed', $byId['suppressed']['label_source']);
        assertFalse($byId['suppressed']['meta']);
        assertSame('llm_meta', $byId['llm-yes']['label_source']);
        assertSame(30, $counts['authored']);
        assertSame(30, $counts['llm_other']);
        assertSame(1, $counts['cyrillic']);
        assertSame(1, $counts['uncertain']);
        assertSame(2, $counts['unlabeled']); // untagged, and stale-score: its stored score is for older text
        assertFalse(isset($byId['reply-1']));
        assertFalse(isset($byId['russian']));
    }

    public function testWeakNegativesOnlyWhenAsked(): void
    {
        [$read, $scores] = self::fixture();
        ['examples' => $examples, 'counts' => $counts] = (new LocalMetaTraining($read, $scores))->collect(true);
        assertSame(2, $counts['weak_other']);
        assertSame(0, $counts['unlabeled']);
        assertSame('weak_other', array_column($examples, null, 'post_id')['untagged']['label_source']);
    }

    public function testRunReportsHeldOutResultsAndIsDeterministic(): void
    {
        [$read, $scores] = self::fixture();
        $first = (new LocalMetaTraining($read, $scores))->run(false, '2026-11-14T00:00:00Z');
        $second = (new LocalMetaTraining($read, $scores))->run(false, '2026-11-14T00:00:00Z');
        assertSame($first['report'], $second['report']);
        $report = $first['report'];
        assertTrue($report['holdout'] > 0 && $report['holdout'] < 64);
        assertSame($report['threshold'], $first['model']->threshold());
        assertTrue($report['threshold'] !== null);
        assertTrue($report['precision'] >= LocalMetaTraining::TARGET_PRECISION);
        assertSame(31, $first['model']->counts()['meta']);
        assertTrue($first['model']->score('Bug: the login page is broken', 'please fix')['probability'] >= $report['threshold']);
    }

    public function testRefusesTooFewLabelsAndKeepsThePreviousModel(): void
    {
        $dir = sys_get_temp_dir() . '/local-meta-' . bin2hex(random_bytes(5));
        mkdir($dir);
        try {
            $files = new LocalMetaFiles($dir);
            [$read, $scores] = self::fixture();
            (new LocalMetaTraining($read, $scores))->runAndSave($files, false);
            $before = file_get_contents($files->modelPath());
            $few = self::readModel([['a', 'Bug', 'button', ['meta']], ['b', 'Hike', 'cape', []]]);
            $threw = null;
            try { (new LocalMetaTraining($few, null))->runAndSave($files, false); } catch (LocalMetaTrainingRefused $error) { $threw = $error; }
            assertTrue($threw !== null);
            assertStringContains('1 meta, 0 not meta', $threw->getMessage());
            assertStringContains('--weak-negatives', $threw->getMessage());
            assertSame($before, file_get_contents($files->modelPath()));
            assertSame('0600', substr(sprintf('%o', fileperms($files->modelPath())), -4));
            assertStringContains('Precision: ', (string) $files->readReport());
            assertTrue(LocalMetaModel::fromJson($before)->threshold() !== null);
        } finally {
            foreach (glob($dir . '/state/private/*') ?: [] as $file) unlink($file);
            @rmdir($dir . '/state/private'); @rmdir($dir . '/state'); rmdir($dir);
        }
    }

    public function testThresholdIsTheLowestCutReachingTargetPrecision(): void
    {
        $scored = [];
        foreach ([0.99, 0.98, 0.97, 0.96, 0.95, 0.94, 0.93, 0.92, 0.91, 0.90] as $p) $scored[] = ['meta' => true, 'probability' => $p];
        $scored[] = ['meta' => false, 'probability' => 0.89];
        $scored[] = ['meta' => true, 'probability' => 0.5];
        $scored[] = ['meta' => false, 'probability' => 0.1];
        [$threshold, $precision, $recall] = LocalMetaTraining::chooseThreshold($scored);
        assertTrue($threshold > 0.89 && $threshold < 0.90);
        assertSame(1.0, $precision);
        assertSame(10 / 11, $recall);
        assertSame([null, null, null], LocalMetaTraining::chooseThreshold([['meta' => false, 'probability' => 0.9]]));
    }

    public function testCliRefusalExplainsAndExitsTwo(): void
    {
        $dir = sys_get_temp_dir() . '/local-meta-cli-' . bin2hex(random_bytes(5));
        mkdir($dir);
        try {
            $database = $dir . '/read.sqlite3';
            $pdo = new PDO('sqlite:' . $database);
            $pdo->exec("CREATE TABLE posts (post_id TEXT, thread_id TEXT, subject TEXT, body TEXT, board_tags_json TEXT DEFAULT '[]', post_tags_json TEXT DEFAULT '[]', is_hidden INTEGER DEFAULT 0, retracted_at TEXT)");
            $pdo->exec('CREATE TABLE threads (root_post_id TEXT, thread_labels_json TEXT)');
            $pdo = null;
            file_put_contents($dir . '/secrets.php', '<?php return ' . var_export(['FAST_SCORING_DATABASE_PATH' => $dir . '/scores.sqlite3'], true) . ';');
            $cmd = 'FORUM_SECRETS_PATH=' . escapeshellarg($dir . '/secrets.php') . ' php ' . escapeshellarg(dirname(__DIR__) . '/scripts/fast_score.php')
                . ' local-train --database-path=' . escapeshellarg($database) . ' 2>&1';
            exec($cmd, $output, $exit);
            assertSame(2, $exit);
            assertStringContains('Too few labels to train: 0 meta, 0 not meta', implode("\n", $output));
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) unlink($file);
            rmdir($dir);
        }
    }
}
