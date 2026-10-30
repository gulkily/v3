<?php
// Machine benchmark harness: setup is explicit and excluded from measured operations.
declare(strict_types=1);
[$script, $project, $repository, $database, $mode, $output] = $argv;
$variant = getenv('QDB_BENCH_VARIANT') ?: 'baseline';
$current = dirname(__DIR__, 2);
$overrides = match ($variant) {
    'commit-only' => ['ReadModel/CommitHistoryReader', 'ReadModel/ReadModelBuilder'],
    'evidence-only' => ['Activity/CommitEvidenceLookup', 'Activity/ActivityService', 'ReadModel/ActivityEvidenceIndex'],
    'profile-only' => ['Support/FeatureFlags/FeatureFlagRegistry', 'Support/PrivateConfigSchema', 'Host/StaticArtifactBuilder'],
    default => [],
};
foreach ($overrides as $class) require $current . '/src/ForumRewrite/' . $class . '.php';
require $project . '/autoload.php';
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ActivityEvidenceIndex;
use ForumRewrite\Host\StaticArtifactBuilder;
$builder = new ReadModelBuilder($repository, $database, new CanonicalRecordRepository($repository));
if (str_starts_with($mode, 'prepare')) {
    // Malformed bulk files are Git history only; initialize from the small valid corpus.
    $small = $project . '/tests/fixtures/parity_minimal_v1';
    (new ReadModelBuilder($small, $database, new CanonicalRecordRepository($small)))->rebuild();
}
$pdo = new PDO('sqlite:' . $database, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
if (str_starts_with($mode, 'prepare')) {
    $row = $pdo->query('SELECT * FROM profiles LIMIT 1')->fetch();
    $insert = $pdo->prepare('INSERT INTO profiles (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0,count($row),'?')) . ')');
    $pdo->beginTransaction();
    for ($i = 1; $i < 5215; $i++) {
        $row['identity_id'] = 'fixture:' . $i; $row['profile_slug'] = 'fixture-' . $i;
        $row['username'] = $row['username_token'] = 'fixture-' . $i;
        $insert->execute(array_values($row));
    }
    $pdo->exec('DELETE FROM activity');
    $sha = trim((string) shell_exec('git -C ' . escapeshellarg($repository) . ' rev-parse HEAD'));
    $insert = $pdo->prepare("INSERT INTO activity(created_at,kind,label,board_tags_json,author_label,source_path,source_commit_sha) VALUES ('2026-10-30','post','fixture','[]','guest',?,?)");
    for ($i = 0; $i < 10000; $i++) $insert->execute([sprintf('records/posts/bulk-%05d.txt', $i), $sha]);
    if ($mode === 'prepare-small') {
        exec('git -C ' . escapeshellarg($repository) . ' rev-list --max-count=1000 HEAD', $commits, $status);
        if ($status !== 0 || count($commits) !== 1000) throw new RuntimeException('Missing small-commit corpus');
        $update = $pdo->prepare("UPDATE activity SET source_commit_sha = ?, source_path = 'records/posts/root-001.txt' WHERE id = ?");
        foreach ($pdo->query('SELECT id FROM activity ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $i => $id) $update->execute([$commits[$i % 1000], $id]);
    }
    $pdo->commit(); exit;
}
if ($mode === 'commits') $pdo->exec('DELETE FROM commits');
if ($mode === 'evidence') $pdo->exec('UPDATE activity SET evidence_json=NULL');
if (in_array($mode, ['commits', 'evidence'], true)) $pdo->beginTransaction();
$start = hrtime(true); $usage = getrusage();
if ($mode === 'commits') (new ReflectionMethod($builder, 'indexCommits'))->invoke($builder, $pdo);
elseif ($mode === 'evidence') ActivityEvidenceIndex::refresh($pdo, $repository, $database);
elseif ($mode === 'profiles') {
    // Isolate the exact production profile loop (normal route rendering/writing).
    $static = new StaticArtifactBuilder($project, $repository, $database, $output);
    $enabled = !method_exists($static, 'prebuildProfilePages') || (new ReflectionMethod($static, 'prebuildProfilePages'))->invoke($static);
    if ($enabled) {
    $application = (new ReflectionMethod($static,'application'))->invoke($static);
    $write = new ReflectionMethod($static, 'writeRouteArtifact');
    foreach ($pdo->query('SELECT profile_slug FROM profiles ORDER BY profile_slug')->fetchAll() as $row) {
        $slug = $row['profile_slug'];
        $write->invoke($static, $application, '/profiles/'.$slug, $output.'/profiles/'.$slug.'.html');
        if (http_response_code() === 404) throw new RuntimeException('Benchmark profile disappeared: '.$slug);
    }
    }
} elseif ($mode === 'runtime' || $mode === 'static-hit') {
    $controller = new \ForumRewrite\Host\FrontController($project, $repository, $database, $output, $output . '/public');
    ob_start(); $controller->handle('GET', '/profiles/fixture-1', []); $html = ob_get_clean();
    if ($mode === 'static-hit' && !str_contains($html, 'route-source: static-html')) throw new RuntimeException('Expected static hit');
    if (http_response_code() !== 200 || !str_contains($html, 'fixture-1')) throw new RuntimeException('Missing runtime profile');
} elseif ($mode === 'full') {
    $publisher = new \ForumRewrite\Host\StaticArtifactReleasePublisher($project, $repository, $output);
    $publisher->activate($publisher->build($database));
} else throw new RuntimeException('Unknown mode');
if ($pdo->inTransaction()) $pdo->commit();
$elapsed = (hrtime(true)-$start)/1e9; $after = getrusage();
if ($mode === 'profiles' && (int) $pdo->query('SELECT COUNT(*) FROM profiles')->fetchColumn() !== 5215) throw new RuntimeException('Profile corpus changed during measurement');
if (in_array($mode, ['commits', 'evidence'], true)) {
    $file = fopen($output, 'wb');
    foreach ($pdo->query($mode === 'commits' ? 'SELECT sha,author_name,author_email,committed_at,subject,file_count FROM commits ORDER BY id' : 'SELECT id,evidence_json FROM activity ORDER BY id') as $row) fwrite($file, json_encode($row, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
    fclose($file);
}
echo 'Mode: '.$mode."\nSeconds: ".$elapsed."\nPHP peak bytes: ".memory_get_peak_usage(true)."\n";
foreach (['utime','stime'] as $key) echo $key.': '.(($after['ru_'.$key.'.tv_sec']-$usage['ru_'.$key.'.tv_sec'])+($after['ru_'.$key.'.tv_usec']-$usage['ru_'.$key.'.tv_usec'])/1e6)."\n";
echo 'Max RSS KiB: '.$after['ru_maxrss']."\n";
