<?php

declare(strict_types=1);
require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

use ForumRewrite\Activity\ActivityService;
use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelMetadata;

final class ActivityProjectionTest
{
    public function testRebuildStoresCompactRelevantEvidence(): void
    {
        $fixture = new RebuildHistoryFixture(2, 2);
        try {
            $db = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $db, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $service = new ActivityService(static fn (): PDO => $pdo, $fixture->repository, $db);
            foreach ($pdo->query('SELECT * FROM activity')->fetchAll() as $row) {
                assertTrue(is_string($row['evidence_json']));
                assertSame($service->buildIndexedEvidence($row), $row['evidence_json']);
                assertStringNotContains('href', $row['evidence_json']);
                assertStringNotContains('Hello world', $row['evidence_json']);
            }
        } finally { $fixture->remove(); }
    }

    public function testIncrementalReactionEvidenceMatchesRebuildAndRollsBack(): void
    {
        $fixture = new RebuildHistoryFixture(0, 1);
        try {
            $db = $fixture->root . '/model.sqlite';
            $builder = new ReadModelBuilder($fixture->repository, $db, new CanonicalRecordRepository($fixture->repository));
            $builder->rebuild();
            $fixture->write('post-reactions', 1);
            $fixture->git('add', '.');
            $fixture->git('commit', '-qm', 'reaction update');
            $sha = $fixture->git('rev-parse', 'HEAD');
            (new \ForumRewrite\ReadModel\IncrementalReadModelUpdater($db, $fixture->repository))->applyPostReactionWrite('root-001', $sha);
            $pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $before = $pdo->query('SELECT action_key, evidence_json FROM activity ORDER BY action_key')->fetchAll();
            assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM activity WHERE evidence_json IS NULL')->fetchColumn());
            $pdo->beginTransaction();
            $pdo->exec("UPDATE activity SET source_commit_sha = NULL WHERE source_path IS NOT NULL");
            assertTrue((int) $pdo->query('SELECT COUNT(*) FROM activity WHERE evidence_json IS NULL')->fetchColumn() > 0);
            \ForumRewrite\ReadModel\ActivityEvidenceIndex::refresh($pdo, $fixture->repository, $db);
            $pdo->rollBack();
            assertSame($before, $pdo->query('SELECT action_key, evidence_json FROM activity ORDER BY action_key')->fetchAll());
            $builder->rebuild();
            assertSame($before, $pdo->query('SELECT action_key, evidence_json FROM activity ORDER BY action_key')->fetchAll());
            $pdo->exec("INSERT INTO activity(created_at,kind,label,board_tags_json,author_label) VALUES ('now','codex_handoff','test','[]','guest')");
            assertSame('["","",[]]', $pdo->query("SELECT evidence_json FROM activity WHERE kind='codex_handoff'")->fetchColumn());
        } finally { $fixture->remove(); }
    }

    public function testPriorSchemaStartupRebuildsBeforeActivityQuery(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $db = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $db, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $pdo = new PDO('sqlite:' . $db);
            $pdo->exec("DROP TRIGGER activity_evidence_insert; DROP TRIGGER activity_evidence_update; DROP INDEX activity_pending_evidence_idx; ALTER TABLE activity DROP COLUMN evidence_json;
                UPDATE metadata SET value = '31' WHERE key = 'schema_version'");
            $pdo = null;
            ob_start();
            try { (new Application(dirname(__DIR__), $fixture->repository, $db))->handle('GET', '/activity/'); }
            finally { $html = ob_get_clean(); }
            assertStringContains('Activity', $html);
            $pdo = new PDO('sqlite:' . $db);
            assertSame(ReadModelMetadata::SCHEMA_VERSION, $pdo->query("SELECT value FROM metadata WHERE key='schema_version'")->fetchColumn());
            assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM activity WHERE evidence_json IS NULL')->fetchColumn());
        } finally { $fixture->remove(); }
    }
}
