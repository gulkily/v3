<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Host\HighTrafficPublication;
use ForumRewrite\Retraction\PublicationGuard;

final class HighTrafficPublicationTest
{
    public function testAbsentTargetsAreScopedAndReappearanceInvalidatesHtmlContext(): void
    {
        $root = $this->fixture();
        $context = HighTrafficPublication::capture($root);
        assertSame(2, count($context['state']['absent']));
        HighTrafficPublication::validate($context);
        $failed = false;
        try { PublicationGuard::generation($root); } catch (RuntimeException) { $failed = true; }
        assertTrue($failed, 'Strict callers must still reject absent targets.');
        $id = array_key_first($context['state']['absent']);
        file_put_contents($root . '/records/posts/' . $id . '.txt', "Post-ID: $id\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\n\nReappeared\n");
        $this->rejects(fn () => HighTrafficPublication::validate($context));
        assertSame(1, count(HighTrafficPublication::capture($root)['state']['absent']));
    }

    public function testChangedEvidenceAndUnrelatedSourceChangesInvalidateContext(): void
    {
        $root = $this->fixture();
        $context = HighTrafficPublication::capture($root);
        file_put_contents($root . '/records/posts/unrelated.txt', "Post-ID: unrelated\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\n\nNew\n");
        $this->rejects(fn () => HighTrafficPublication::validate($context));
        $context = HighTrafficPublication::capture($root);
        $path = glob($root . '/records/post-retractions/*.txt')[0];
        file_put_contents($path, 'malformed');
        $this->rejects(fn () => HighTrafficPublication::validate($context));
    }

    public function testUnsafeEvidenceNeverBecomesConfirmedAbsence(): void
    {
        $root = $this->fixture();
        $path = glob($root . '/records/post-retractions/*.txt')[0];
        unlink($path);
        symlink('/nonexistent-evidence', $path);
        $this->rejects(fn () => HighTrafficPublication::capture($root));
    }

    public function testDisposableInputRemovesStaleRootAndGeneratedEffectsButRetainsReplies(): void
    {
        $root = $this->fixture();
        $target = 'reply-20260526032118-654b2b08';
        $post = "Post-ID: $target\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nSubject: STALE-ROOT-SECRET\n\nSTALE-BODY-SECRET\n";
        $targetPath = $root . '/records/posts/' . $target . '.txt';
        file_put_contents($targetPath, $post);
        file_put_contents($root . '/records/posts/survivor.txt', "Post-ID: survivor\nCreated-At: 2026-10-14T11:10:00Z\nBoard-Tags: general\nThread-ID: $target\nParent-ID: $target\n\nIndependent surviving reply\n");
        $meta = json_encode(['type' => 'scripture_oriented_analysis', 'source_post_id' => $target, 'source_hash' => str_repeat('a', 64)]);
        file_put_contents($root . '/records/posts/generated.txt', "Post-ID: generated\nCreated-At: 2026-10-14T11:05:00Z\nBoard-Tags: general\nThread-ID: $target\nParent-ID: $target\nAgent-Response: $meta\n\nGENERATED-SECRET\n");
        mkdir($root . '/records/thread-labels');
        foreach (['owned' => 'pinned', 'independent' => 'like'] as $id => $tag) {
            $source = $id === 'owned' ? "Source-Post-ID: $target\nSource-Post-SHA256: " . hash('sha256', $post) . "\n" : '';
            file_put_contents($root . '/records/thread-labels/' . $id . '.txt', "Record-ID: $id\nCreated-At: 2026-10-14T11:00:00Z\nThread-ID: $target\nOperation: add\nLabels: $tag\n$source\n");
        }
        $live = $root . '/live/read.sqlite3';
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $live, new \ForumRewrite\Canonical\CanonicalRecordRepository($root)))->rebuild();
        $hash = hash_file('sha256', $live);
        unlink($targetPath);
        $context = HighTrafficPublication::capture($root);
        $view = \ForumRewrite\Host\HighTrafficRenderInput::prepare($context, $live, $root . '/candidate');
        assertSame($hash, hash_file('sha256', $live));
        $pdo = new PDO('sqlite:' . $view);
        assertSame(0, (int) $pdo->query("SELECT count(*) FROM posts WHERE body LIKE '%SECRET%' OR subject LIKE '%SECRET%'")->fetchColumn());
        assertSame(1, (int) $pdo->query("SELECT count(*) FROM posts WHERE post_id='survivor'")->fetchColumn());
        assertSame('["like"]', $pdo->query('SELECT thread_labels_json FROM threads')->fetchColumn());
        $assets = $root . '/rendered-assets';
        foreach (['/', '/threads/' . $target . '/'] as $route) {
            $html = (new \ForumRewrite\Host\StaticArtifactBuilder(dirname(__DIR__), $root, $view, $assets))->renderHighTrafficRoute($route);
            assertSame(false, str_contains($html, 'SECRET'));
            assertStringContains($route === '/' ? '</head>' : 'Independent surviving reply', $html);
        }
        assertSame($hash, hash_file('sha256', $live));
    }

    private function rejects(callable $callback): void
    {
        $failed = false;
        try { $callback(); } catch (RuntimeException) { $failed = true; }
        assertTrue($failed, 'Unsafe or changed input must reject publication.');
    }

    public function testNormalHomeAndCliRefreshRecoverWithBothAbsentProductionTargets(): void
    {
        $root = $this->fixture();
        file_put_contents($root . '/records/posts/visible.txt', "Post-ID: visible\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nSubject: Surviving thread\n\nSurviving body\n");
        $project = dirname(__DIR__);
        $database = $root . '/live.sqlite3';
        mkdir($root . '/public');
        $cache = new \ForumRewrite\Host\HighTrafficCache($project, $root, $database, $root . '/public');
        $cache->initialize();
        symlink($cache->root . '/pages', $root . '/public/.high-traffic');
        $cache->atomic($cache->root . '/state.json', json_encode(['enabled' => true, 'generation' => 'test']));
        try {
            http_response_code(200);
            ob_start();
            (new \ForumRewrite\Host\FrontController($project, $root, $database, $root . '/static', $root . '/public'))->handle('GET', '/', []);
            $html = ob_get_clean();
            assertSame(200, http_response_code());
            assertStringContains('v3-generated-at', $html);
            assertSame($html, file_get_contents($cache->path('/')));
            assertSame(false, is_file($database), 'Rendering must not rebuild the live model.');
            foreach (['/about/', '/threads/', '/threads/visible/'] as $route) {
                assertStringContains('v3-generated-at', (string) $cache->generate($route));
            }
            $proc = proc_open([PHP_BINARY, $project . '/scripts/high_traffic.php', 'refresh', '/', '--json'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null,
                array_merge(getenv(), ['FORUM_REPOSITORY_ROOT' => $root, 'FORUM_DATABASE_PATH' => $database, 'FORUM_PUBLIC_ARTIFACT_ROOT' => $root . '/public']));
            fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            assertSame(0, proc_close($proc), $err);
            assertSame('/', json_decode($out, true)['refreshed']);
            assertSame([], glob($cache->root . '/.render-*'));
            assertSame([], glob($cache->root . '/assets/*.sqlite3'), 'Private render models must never enter public assets.');
        } finally {
            \ForumRewrite\Host\HighTrafficCache::removeTemporaryTree($cache->root);
        }
    }

    public function testParentRejectsReappearanceAndModeChangeAfterWorkerRenderingAndCanRetry(): void
    {
        $root = $this->fixture();
        file_put_contents($root . '/records/posts/visible.txt', "Post-ID: visible\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nSubject: Visible thread\n\nIndependent body\n");
        mkdir($root . '/public');
        $cache = new \ForumRewrite\Host\HighTrafficCache(dirname(__DIR__), $root, $root . '/live.sqlite3', $root . '/public');
        $cache->initialize();
        symlink($cache->root . '/pages', $root . '/public/.high-traffic');
        $cache->atomic($cache->root . '/state.json', json_encode(['enabled' => true, 'generation' => 'test']));
        $oldPhp = getenv('FORUM_PHP_CLI');
        try {
            $previous = $cache->generate('/threads/visible/');
            $target = 'reply-20260526032118-654b2b08';
            $path = $root . '/records/posts/' . $target . '.txt';
            $bytes = "Post-ID: $target\nCreated-At: 2026-10-14T11:30:00Z\nBoard-Tags: general\nThread-ID: visible\nParent-ID: visible\n\nReappeared independent source\n";
            $evidence = glob($root . '/records/post-retractions/*.txt')[0];
            $originalEvidence = file_get_contents($evidence);
            $mutations = [
                'file_put_contents(' . var_export($path, true) . ', ' . var_export($bytes, true) . ');',
                'unlink(' . var_export($root . '/public/.high-traffic', true) . '); file_put_contents(' . var_export($cache->root . '/state.json', true) . ', ' . var_export(json_encode(['enabled' => false, 'generation' => 'changed']), true) . ');',
                'file_put_contents(' . var_export($evidence, true) . ', "malformed");',
                'exit(1);',
            ];
            foreach ($mutations as $index => $mutation) {
                $wrapper = $root . '/php-wrapper';
                file_put_contents($wrapper, "#!/usr/bin/env php\n<?php\n"
                    . '$proc=proc_open(array_merge([PHP_BINARY],array_slice($argv,1)),[STDIN,STDOUT,STDERR],$pipes);'
                    . '$code=proc_close($proc); if ($code===0) {' . $mutation . '} exit($code);');
                chmod($wrapper, 0755);
                putenv('FORUM_PHP_CLI=' . $wrapper);
                $this->rejects(fn () => $cache->generate('/threads/visible/', true));
                assertSame($previous, file_get_contents($cache->path('/threads/visible/')));
                assertSame([], glob($cache->root . '/.render-*'));
                putenv($oldPhp === false ? 'FORUM_PHP_CLI' : 'FORUM_PHP_CLI=' . $oldPhp);
                if ($index === 0) {
                    $previous = $cache->generate('/threads/visible/', true);
                    assertStringContains('Reappeared independent source', $previous);
                }
                if ($index === 1) {
                    symlink($cache->root . '/pages', $root . '/public/.high-traffic');
                    $cache->atomic($cache->root . '/state.json', json_encode(['enabled' => true, 'generation' => 'retry']));
                }
                if ($index === 2) file_put_contents($evidence, $originalEvidence);
            }
            assertStringContains('Reappeared independent source', (string) $cache->generate('/threads/visible/', true));
        } finally {
            putenv($oldPhp === false ? 'FORUM_PHP_CLI' : 'FORUM_PHP_CLI=' . $oldPhp);
            \ForumRewrite\Host\HighTrafficCache::removeTemporaryTree($cache->root);
        }
    }

    public function testTrackedMissingEvidenceAndUnsafeSourcePathRejectHtmlAdmission(): void
    {
        $root = $this->fixture();
        exec('git -C ' . escapeshellarg($root) . ' init -q');
        exec('git -C ' . escapeshellarg($root) . ' add records');
        exec('git -C ' . escapeshellarg($root) . ' -c user.name=Fixture -c user.email=fixture@example.invalid commit -qm Fixture');
        $context = HighTrafficPublication::capture($root);
        $path = glob($root . '/records/post-retractions/*.txt')[0];
        $bytes = file_get_contents($path);
        unlink($path);
        $this->rejects(fn () => HighTrafficPublication::validate($context));
        file_put_contents($path, $bytes);
        HighTrafficPublication::validate($context);
        $id = array_key_first($context['state']['absent']);
        symlink('/nonexistent-source', $root . '/records/posts/' . $id . '.txt');
        $this->rejects(fn () => HighTrafficPublication::capture($root));
    }

    public function fixture(): string
    {
        $root = sys_get_temp_dir() . '/v3-html-publication-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0755, true);
        mkdir($root . '/records/post-retractions');
        mkdir($root . '/records/instance');
        copy(__DIR__ . '/fixtures/parity_minimal_v1/records/instance/public.txt', $root . '/records/instance/public.txt');
        foreach (['reply-20260526032118-654b2b08', 'reply-20260526032816-a1447d49'] as $id) {
            file_put_contents($root . '/records/post-retractions/post-retraction-' . $id . '.txt',
                "Record-ID: post-retraction-$id\nCreated-At: 2026-10-14T12:00:00Z\nPost-ID: $id\nOperation: retract\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\n");
        }
        return $root;
    }
}
