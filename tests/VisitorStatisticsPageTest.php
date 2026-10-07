<?php

declare(strict_types=1);

final class VisitorStatisticsPageTest
{
    public function testRootSessionCanViewStatisticsAndIdentityHintCannot(): void
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Statistics\VisitorStatisticsStore;

$databasePath = sys_get_temp_dir() . '/visitor-statistics-page-' . bin2hex(random_bytes(6)) . '.sqlite3';
$statsPath = sys_get_temp_dir() . '/visitor-statistics-state-' . bin2hex(random_bytes(6)) . '.sqlite3';
$secretsPath = sys_get_temp_dir() . '/visitor-statistics-secrets-' . bin2hex(random_bytes(6)) . '.php';
$sessionId = 'visitor-statistics-' . bin2hex(random_bytes(8));

try {
    file_put_contents($secretsPath, '<?php return ' . var_export(['VISITOR_STATISTICS_DATABASE_PATH' => $statsPath], true) . ';');
    putenv('FORUM_SECRETS_PATH=' . $secretsPath);
    $store = new VisitorStatisticsStore(new PDO('sqlite:' . $statsPath));
    $store->recordVisit(new DateTimeImmutable('now'), 'client-a', 'user-a');

    session_id($sessionId);
    session_start();
    $_SESSION['authenticated_identity_id'] = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    session_write_close();
    session_id($sessionId);
    $_COOKIE = [session_name() => $sessionId];

    $application = new Application($argv[1], $argv[2], $databasePath);
    ob_start();
    $application->handle('GET', '/threads/');
    ob_end_clean();
    ob_start();
    $application->handle('GET', '/tools/visitor-statistics/');
    $allowed = (string) ob_get_clean();
    if (!str_contains($allowed, 'Last 30 days') || !str_contains($allowed, 'Estimated clients') || !str_contains($allowed, '>2</td>')) {
        throw new RuntimeException('Root-authenticated statistics page did not render its summary.');
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_id('');
    unset($_SESSION);
    $_COOKIE = ['identity_hint' => 'guest'];
    $application = new Application($argv[1], $argv[2], $databasePath);
    ob_start();
    $application->handle('GET', '/tools/visitor-statistics/');
    $denied = (string) ob_get_clean();
    if (!str_contains($denied, 'server-authenticated root-approved identity')) {
        throw new RuntimeException('Identity-hint-only request was not denied.');
    }
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    putenv('FORUM_SECRETS_PATH');
    @unlink($databasePath);
    @unlink($statsPath);
    @unlink($secretsPath);
}
PHP;
        $command = sprintf(
            'php -r %s %s %s',
            escapeshellarg($script),
            escapeshellarg(dirname(__DIR__)),
            escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'),
        );
        exec($command . ' 2>&1', $output, $exitCode);

        assertSame(0, $exitCode, implode("\n", $output));
    }
}
