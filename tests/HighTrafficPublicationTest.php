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
        foreach (['reply-20260526032118-654b2b08', 'reply-20260526032816-a1447d49'] as $id) {
            file_put_contents($root . '/records/post-retractions/post-retraction-' . $id . '.txt',
                "Record-ID: post-retraction-$id\nCreated-At: 2026-10-14T12:00:00Z\nPost-ID: $id\nOperation: retract\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\n");
        }
        return $root;
    }
}
