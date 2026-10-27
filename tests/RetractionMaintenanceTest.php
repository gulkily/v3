<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Retraction\ArtifactSweep;

final class RetractionMaintenanceTest
{
    public function testFullTraversalHasNoCursorAndPreservesAssetsAndLinks(): void
    {
        $root = sys_get_temp_dir() . '/retraction-artifacts-' . bin2hex(random_bytes(6));
        mkdir($root . '/threads', 0775, true); mkdir($root . '/assets');
        file_put_contents($root . '/assets/keep.html', 'independent asset');
        for ($i = 0; $i < 1500; ++$i) file_put_contents($root . '/threads/' . $i . '.html', 'stale prose');
        $outside = $root . '-outside'; mkdir($outside); file_put_contents($outside . '/keep.html', 'outside');
        symlink($outside, $root . '/threads/linked');
        $preview = ArtifactSweep::run([$root], time(), true);
        assertSame(1500, $preview['affected']);
        assertSame(true, is_file($root . '/threads/1499.html'));
        assertSame(1500, ArtifactSweep::run([$root], time())['cleaned']);
        assertSame(0, ArtifactSweep::run([$root], time())['cleaned']);
        assertSame('outside', file_get_contents($outside . '/keep.html'));
        assertSame('independent asset', file_get_contents($root . '/assets/keep.html'));
        file_put_contents($root . '/threads/late.html', 'late stale publisher');
        touch($root . '/threads/late.html', time() - 301);
        assertSame(1, ArtifactSweep::run([$root], time() - 300)['cleaned']);
    }
}
