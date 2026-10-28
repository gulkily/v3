<?php
/** Run against a disposable standard_activity_pagination_fixture directory. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/autoload.php';
$root = $argv[1] ?? throw new RuntimeException('Pass a disposable fixture directory.');
$database = $root . '/read.sqlite3';
$pdo = new PDO('sqlite:' . $database, null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
foreach (['standard', 'forte', 'detail'] as $mode) {
    $samples = [];
    for ($i = 0; $i < 20; $i++) {
        $service = new \ForumRewrite\Activity\ActivityService(static fn (): PDO => $pdo, $root . '/repository', $database);
        $start = hrtime(true);
        if ($mode === 'standard') $service->fetchActivityPage('all');
        else {
            $rows = $service->fetchActivityRows('all', 'date', 'desc', null, 20);
            if ($mode === 'forte') foreach (['all','content','identity','bootstrap','approval'] as $view) $service->countActivityViewTotal($view);
            if ($rows['items'] !== []) $service->fetchActivityDetail((int) $rows['items'][0]['id']);
        }
        $samples[] = (hrtime(true) - $start) / 1e6;
    }
    sort($samples);
    printf("%s p95-ms: %.3f\n", $mode, $samples[18]);
}
printf("Database-bytes: %d\nActivity-events: %d\n", filesize($database), $pdo->query('SELECT COUNT(*) FROM activity')->fetchColumn());
