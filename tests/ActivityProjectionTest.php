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
    private function legacyCount(PDO $pdo, ActivityService $service, string $view): int
    {
        $view = $service->normalizeActivityView($view);
        [$viewWhere, $viewParameters] = (new ReflectionMethod($service, 'activityViewSql'))->invoke($service, $view);
        $stmt = $pdo->prepare(
            'SELECT activity.board_tags_json
             FROM activity
             LEFT JOIN posts ON posts.post_id = activity.post_id
             WHERE 1 = 1
             ' . $viewWhere
        );
        foreach ($viewParameters as $parameter => $value) {
            $stmt->bindValue($parameter, $value);
        }
        $stmt->execute();

        $hasTag = static fn (string $json, string $tag): bool => in_array($tag, \ForumRewrite\ReadModel\ThreadRowSupport::decodeStringList($json), true);
        $count = 0;
        while (($row = $stmt->fetch()) !== false) {
            $boardTagsJson = (string) $row['board_tags_json'];
            $hidden = \ForumRewrite\ReadModel\ThreadRowSupport::isHiddenBootstrapBoardTagsJson($boardTagsJson);
            $matches = match ($view) {
                'all' => true,
                'content' => !$hidden,
                'identity' => $hasTag($boardTagsJson, 'identity'),
                'bootstrap' => $hasTag($boardTagsJson, 'identity') && $hasTag($boardTagsJson, 'internal'),
                'approval' => $hasTag($boardTagsJson, 'identity') && $hasTag($boardTagsJson, 'approval'),
                default => true,
            };
            if ($matches) {
                $count++;
            }
        }

        return $count;
    }

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

    public function testCountersMatchLegacyMembershipAcrossDirectWritesAndVisibility(): void
    {
        $fixture = new RebuildHistoryFixture(1, 1);
        try {
            $db = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $db, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $check = function () use ($pdo, $fixture, $db): void {
                foreach ([false, true] as $names) {
                    $service = new ActivityService(static fn (): PDO => $pdo, $fixture->repository, $db, $names, static fn (): bool => true);
                    foreach (['all','content','identity','bootstrap','approval','security'] as $view) {
                        $stmt = $pdo->prepare('SELECT ' . ($names ? 'total' : 'without_profile_names') . ' FROM activity_counts WHERE view=?');
                        $stmt->execute([$view]);
                        assertSame($this->legacyCount($pdo, $service, $view), (int) $stmt->fetchColumn(), $view);
                        assertSame($this->legacyCount($pdo, $service, $view), $service->countActivityViewTotal($view), $view);
                    }
                }
            };
            $check();
            $insert = $pdo->prepare("INSERT INTO activity(created_at,kind,record_family,label,board_tags_json,author_label,post_id) VALUES ('now','approval',?,'test',?,'guest','root-001')");
            foreach (['[]','["identity","approval"]','["identity","internal"]','["IDENTITY"]','"identity"','broken'] as $tags) {
                $insert->execute(['profile_name', $tags]);
                $insert->execute(['post', $tags]);
            }
            $check();
            $pdo->exec("UPDATE posts SET is_hidden=1 WHERE post_id='root-001'"); $check();
            $pdo->exec("UPDATE posts SET is_hidden=0, retracted_at='now' WHERE post_id='root-001'"); $check();
            $pdo->exec("DELETE FROM posts WHERE post_id='root-001'"); $check();
            $pdo->exec("UPDATE activity SET kind='test',board_tags_json='[]' WHERE kind='approval'"); $check();
            $pdo->exec('DELETE FROM activity WHERE action_key IS NULL'); $check();
            assertSame(0, (int) $pdo->query('SELECT events FROM activity_local_state')->fetchColumn());
        } finally { $fixture->remove(); }
    }

    public function testPreviousSchemaStartupRecoversCountersBeforePaging(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $db = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $db, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $pdo = new PDO('sqlite:' . $db);
            foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='trigger' AND name NOT LIKE 'activity_evidence_%'")->fetchAll(PDO::FETCH_COLUMN) as $name) $pdo->exec('DROP TRIGGER ' . $name);
            $pdo->exec("DROP TABLE activity_counts; DROP TABLE activity_local_state; UPDATE metadata SET value='" . ((int) ReadModelMetadata::SCHEMA_VERSION - 1) . "' WHERE key='schema_version'");
            ob_start();
            try { (new Application(dirname(__DIR__), $fixture->repository, $db))->handle('GET', '/activity/'); }
            finally { ob_end_clean(); }
            assertSame((int) $pdo->query('SELECT COUNT(*) FROM activity')->fetchColumn(), (int) $pdo->query("SELECT total FROM activity_counts WHERE view='all'")->fetchColumn());
        } finally { $fixture->remove(); }
    }

    public function testStandardReadsEvidenceWithoutRepositoryAndReportsMissingProjection(): void
    {
        $fixture = new RebuildHistoryFixture(1, 1);
        try {
            $db = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $db, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $pdo = new PDO('sqlite:' . $db, null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $service = new ActivityService(static fn (): PDO => $pdo, $fixture->repository, $db);
            $expected = $service->fetchActivityPage('all');
            $expectedDetail = $service->fetchActivityDetail($expected['items'][0]['id']);
            $expectedCounts = array_map($service->countActivityViewTotal(...), ['all','content','identity','bootstrap','approval']);
            rename($fixture->repository, $fixture->root . '/offline');
            $freshService = new ActivityService(static fn (): PDO => $pdo, $fixture->repository, $db);
            assertSame($expected, $freshService->fetchActivityPage('all'));
            assertSame($expectedDetail, $freshService->fetchActivityDetail($expected['items'][0]['id']));
            assertSame($expectedCounts, array_map($freshService->countActivityViewTotal(...), ['all','content','identity','bootstrap','approval']));
            assertSame($expectedDetail, $freshService->fetchIndexedActivity('all')['items'][0]);
            $id = $expected['items'][0]['id'];
            $pdo->exec('UPDATE activity SET evidence_json = NULL WHERE id=' . $id);
            $missing = $freshService->fetchActivityPage('all')['items'][0];
            assertSame(true, $missing['evidence_unavailable']);
            assertSame([], $missing['relevant_files']);
            assertSame('', $missing['source_signature_status']);
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
