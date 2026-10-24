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
