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
