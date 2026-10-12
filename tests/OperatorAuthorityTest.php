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
    public function testPublicOperatorPostRoutesResumePersistedSession(): void
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
$dir = sys_get_temp_dir() . '/operator-session-' . bin2hex(random_bytes(6));
mkdir($dir);
$db = $dir . '/read.sqlite';
$sid = 'operator-post-' . bin2hex(random_bytes(8));
try {
    session_id($sid);
    session_start();
    $_SESSION['authenticated_identity_id'] = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    session_write_close();
    foreach (['/api/prepare_operator_action', '/api/finalize_operator_action', '/api/prepare_feature_flag_change', '/api/finalize_feature_flag_change'] as $path) {
        unset($_SESSION);
        session_id($sid);
        $_COOKIE = [session_name() => $sid];
        $app = new ForumRewrite\Application($argv[1], $argv[2], $db);
        ob_start();
        $app->handle('POST', $path);
        $result = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
        if (session_status() !== PHP_SESSION_ACTIVE || ($_SESSION['authenticated_identity_id'] ?? '') !== 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954') {
            throw new RuntimeException('Persisted operator session was not resumed: ' . $path);
        }
        // Fixture writes are disabled, but authorization must reach the writer.
        if (!str_contains($result['error'] ?? '', 'Write APIs are disabled')) {
            throw new RuntimeException('Operator request did not reach the writer: ' . $path . ': ' . json_encode($result));
        }
        session_write_close();
    }
} finally {
    session_id($sid);
    session_start();
    session_destroy();
    foreach (glob($dir . '/*') ?: [] as $file) { unlink($file); }
    rmdir($dir);
}
PHP;
        $command = 'php -r ' . escapeshellarg($script) . ' ' . escapeshellarg(dirname(__DIR__)) . ' ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1');
        exec($command . ' 2>&1', $output, $code);
        assertSame(0, $code, implode("\n", $output));
    }

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
