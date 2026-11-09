<?php

declare(strict_types=1);

final class VisitorStatisticsPageTest
{
    public function testRootSessionCanViewStatisticsAndIdentityHintCannot(): void
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';

use ForumRewrite\Application;
use ForumRewrite\Statistics\VisitorStatisticsStore;

$databasePath = sys_get_temp_dir() . '/visitor-statistics-page-' . bin2hex(random_bytes(6)) . '.sqlite3';
$statsPath = sys_get_temp_dir() . '/visitor-statistics-state-' . bin2hex(random_bytes(6)) . '.sqlite3';
$secretsPath = sys_get_temp_dir() . '/visitor-statistics-secrets-' . bin2hex(random_bytes(6)) . '.php';

try {
    file_put_contents($secretsPath, '<?php return ' . var_export(['VISITOR_STATISTICS_DATABASE_PATH' => $statsPath], true) . ';');
    putenv('FORUM_SECRETS_PATH=' . $secretsPath);
    $store = new VisitorStatisticsStore(new PDO('sqlite:' . $statsPath));
    $store->recordVisit(new DateTimeImmutable('now'), 'client-a', 'user-a');

    $_COOKIE = [];
    TestViewerSession::signIn('openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');

    $application = new Application($argv[1], $argv[2], $databasePath);
    ob_start();
    $application->handle('GET', '/threads/');
    ob_end_clean();
    ob_start();
    $application->handle('GET', '/tools/visitor-statistics/');
    $allowed = (string) ob_get_clean();
    if (!str_contains($allowed, '<dt>Eligible</dt>')
        || !str_contains($allowed, '<dt>Authenticated</dt>')
        || !str_contains($allowed, '<dt>Authenticated</dt><dd>2</dd>')
        || !str_contains($allowed, 'onchange="this.form.submit()"')
        || str_contains($allowed, '>Apply</button>')
        || !str_contains($allowed, '<option value="30d" selected>Last 30 days</option>')
        || !str_contains($allowed, 'role="img"')
        || !str_contains($allowed, 'How this is counted')) {
        throw new RuntimeException('Root-authenticated statistics page did not render its summary.');
    }

    ob_start();
    $application->handle('GET', '/tools/visitor-statistics/?period=90d');
    $ninetyDays = (string) ob_get_clean();
    if (!str_contains($ninetyDays, '<option value="90d" selected>Last 90 days</option>')) {
        throw new RuntimeException('Visitor-statistics period selection did not render its selected state.');
    }

    ob_start();
    $application->handle('GET', '/tools/visitor-statistics/?period=invalid');
    $invalidPeriod = (string) ob_get_clean();
    if (!str_contains($invalidPeriod, '<option value="30d" selected>Last 30 days</option>')) {
        throw new RuntimeException('Invalid visitor-statistics period did not fall back safely.');
    }

    TestViewerSession::reset();
    $_COOKIE = ['identity_hint' => 'guest'];
    $application = new Application($argv[1], $argv[2], $databasePath);
    ob_start();
    $application->handle('GET', '/threads/');
    ob_end_clean();
    ob_start();
    $application->handle('GET', '/tools/visitor-statistics/');
    $denied = (string) ob_get_clean();
    if (!str_contains($denied, 'server-authenticated root-approved identity')) {
        throw new RuntimeException('Identity-hint-only request was not denied.');
    }

    // A member approved by someone who chose the username "root" is not root-approved.
    $pdo = new PDO('sqlite:' . $databasePath);
    $spoofed = 'openpgp:' . str_repeat('ab', 20);
    $row = $pdo->query("SELECT * FROM profiles WHERE identity_id = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'")->fetch(PDO::FETCH_ASSOC);
    $row = array_merge($row, ['identity_id' => $spoofed, 'profile_slug' => 'openpgp-' . str_repeat('ab', 20),
        'username' => 'mallory', 'username_token' => 'mallory', 'is_approved' => 1,
        'approved_by_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', 'approved_by_label' => 'root']);
    $pdo->prepare('INSERT INTO profiles (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')
        ->execute(array_values($row));
    $pdo = null;
    $_COOKIE = [];
    TestViewerSession::signIn($spoofed);
    $application = new Application($argv[1], $argv[2], $databasePath);
    ob_start();
    $application->handle('GET', '/tools/visitor-statistics/');
    $spoofedPage = (string) ob_get_clean();
    TestViewerSession::reset();
    if (!str_contains($spoofedPage, 'server-authenticated root-approved identity')) {
        throw new RuntimeException('An approver label of "root" granted root-approved access.');
    }

    $split = (new VisitorStatisticsStore(new PDO('sqlite:' . $statsPath)))
        ->summaryForHours(new DateTimeImmutable('now'), 24)['totals'];
    if ($split['anonymous_visits'] !== 1 || $split['authenticated_visits'] !== 2) {
        throw new RuntimeException('Visitor statistics did not distinguish anonymous and server-authenticated requests.');
    }
} finally {
    TestViewerSession::reset();
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
