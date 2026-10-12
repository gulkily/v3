<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Operator\OperatorAuthority;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\ReadModel\ReadModelStaleMarker;

final class OperatorAuthorityTest
{
    public function testCanonicalRootsApplyToApprovedAccountKeysAndFailClosedWhenStale(): void
    {
        $dir = sys_get_temp_dir() . '/operator-authority-' . bin2hex(random_bytes(6));
        mkdir($dir);
        $db = $dir . '/read.sqlite';
        $root = __DIR__ . '/fixtures/parity_minimal_v1';
        try {
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            $pdo = (new ReadModelConnection($db))->open();
            $state = OperatorAuthority::load($pdo);
            $seed = array_values(array_filter($state['profiles'], static fn ($p) => $p['approved_by_label'] === 'root'))[0];
            assertSame(true, OperatorAuthority::allows($pdo, $seed));
            $other = $seed;
            $other['identity_id'] = 'openpgp:' . str_repeat('a', 40);
            $other['profile_slug'] = 'second-key';
            $columns = array_keys($other);
            $pdo->prepare('INSERT INTO profiles (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')')->execute(array_values($other));
            $stmt = $pdo->prepare('UPDATE profiles SET username_token = ?, is_approved = 1 WHERE identity_id = ?');
            $stmt->execute([$seed['username_token'], $other['identity_id']]);
            $other['is_approved'] = 1;
            assertSame(true, OperatorAuthority::allows($pdo, $other));
            $pdo->prepare('UPDATE profiles SET is_approved = 0 WHERE identity_id = ?')->execute([$other['identity_id']]);
            assertSame(false, OperatorAuthority::allows($pdo, $other));
            (new ReadModelStaleMarker($db))->mark(['reason' => 'test']);
            assertSame(false, OperatorAuthority::allows($pdo, $seed));
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) { unlink($file); }
            rmdir($dir);
        }
    }
}
