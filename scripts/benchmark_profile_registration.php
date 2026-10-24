<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\Write\LocalWriteService;

// Deliberately separate from the production CLI: every write goes into a new
// workspace, never the supplied repository/database. No network or task worker.
$home = null;
try {
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--help') {
            echo "Usage: php scripts/benchmark_profile_registration.php --repository-root=PATH --database-path=PATH --output-dir=NEW_PATH [--site=qdb] [--rebuild-copy]\n";
            echo "Runs fresh signed registration and historical identity linking in separate clones. Writes report.json; preserves source inputs.\n";
            exit(0);
        }
        if ($argument === '--rebuild-copy') {
            $options['rebuild-copy'] = true;
        } elseif (preg_match('/^--(repository-root|database-path|output-dir|site)=(.+)$/D', $argument, $match)) {
            $options[$match[1]] = $match[2];
        } else {
            throw new RuntimeException('Unknown or empty option: ' . $argument);
        }
    }
    foreach (['repository-root', 'database-path', 'output-dir'] as $required) {
        if (!isset($options[$required])) {
            throw new RuntimeException('Missing --' . $required . '; see --help.');
        }
    }
    $sourceRepository = realpath($options['repository-root']);
    $sourceDatabase = realpath($options['database-path']);
    if ($sourceRepository === false || !is_dir($sourceRepository . '/.git') || $sourceDatabase === false || !is_file($sourceDatabase)) {
        throw new RuntimeException('A local Git repository and existing SQLite database are required.');
    }
    foreach (['-wal', '-journal'] as $suffix) {
        if (is_file($sourceDatabase . $suffix)) {
            throw new RuntimeException('Use a consistent standalone database snapshot without active SQLite sidecars.');
        }
    }
    $output = $options['output-dir'];
    if (file_exists($output) || is_link($output)) {
        throw new RuntimeException('Output directory must not exist; use a new run directory.');
    }
    $parent = realpath(dirname($output));
    if ($parent === false || $parent === $sourceRepository || str_starts_with($parent . '/', $sourceRepository . '/')) {
        throw new RuntimeException('Output parent must exist and be outside the source repository.');
    }
    if (!mkdir($output, 0700)) {
        throw new RuntimeException('Cannot create output directory.');
    }
    $output = realpath($output);
    putenv('FORUM_SITE_ID=' . ($options['site'] ?? 'qdb'));
    $sourceHash = hash_file('sha256', $sourceDatabase);
    $source = new PDO('sqlite:file:' . $sourceDatabase . '?mode=ro');
    $source->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    if ($source->query('PRAGMA quick_check')->fetchColumn() !== 'ok') {
        throw new RuntimeException('Source database failed quick_check.');
    }
    $metadata = ReadModelMetadata::readMetadata($source);
    $sourceHead = trim(benchmarkCommand(['git', '-C', $sourceRepository, 'rev-parse', 'HEAD'], $output));
    if (($metadata['repository_head'] ?? null) !== $sourceHead) {
        throw new RuntimeException('Source database and canonical repository heads do not match.');
    }
    if (trim(benchmarkCommand(['git', '-C', $sourceRepository, 'status', '--porcelain', '--untracked-files=all'], $output)) !== '') {
        throw new RuntimeException('Source repository must be clean, including untracked canonical evidence.');
    }
    $report = [
        'started_at' => gmdate('c'),
        'application_head' => trim(benchmarkCommand(['git', '-C', dirname(__DIR__), 'rev-parse', 'HEAD'], $output)),
        'application_changes' => trim(benchmarkCommand(['git', '-C', dirname(__DIR__), 'status', '--porcelain', '--', 'src', 'scripts/benchmark_profile_registration.php'], $output)),
        'php_version' => PHP_VERSION,
        'sqlite_version' => $source->query('SELECT sqlite_version()')->fetchColumn(),
        'site' => $options['site'] ?? 'qdb',
        'source_head' => $sourceHead,
        'source_database_sha256' => $sourceHash,
        'source_metadata' => $metadata,
        'source_counts' => benchmarkCounts($source),
        'rebuild_copy' => $options['rebuild-copy'] ?? false,
        'timing_scope' => 'LocalWriteService on isolated clones; setup/key generation excluded. Fresh total includes local signing; server timings reported separately. No HTTP, cron, or host-load simulation.',
        'cases' => [],
    ];
    $source = null;
    file_put_contents($output . '/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    foreach (['fresh_signed', 'historical_link'] as $case) {
        fwrite(STDERR, 'Preparing ' . $case . "...\n");
        $caseRoot = $output . '/' . $case;
        mkdir($caseRoot, 0700);
        $repository = $caseRoot . '/repository';
        benchmarkCommand(['git', '-c', 'core.hooksPath=/dev/null', 'clone', '--quiet', '--no-local', '--no-hardlinks', '--', $sourceRepository, $repository], $caseRoot);
        foreach (['user.name' => 'Registration benchmark', 'user.email' => 'benchmark@example.invalid', 'commit.gpgsign' => 'false', 'core.hooksPath' => '/dev/null'] as $key => $value) {
            benchmarkCommand(['git', '-C', $repository, 'config', $key, $value], $caseRoot);
        }
        $database = $caseRoot . '/read_model.sqlite3';
        if (!copy($sourceDatabase, $database)) {
            throw new RuntimeException('Cannot copy source database into workspace.');
        }
        $pdo = new PDO('sqlite:' . $database);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->prepare("UPDATE metadata SET value = ? WHERE key = 'repository_root'")->execute([$repository]);
        $pdo = null;
        if ($options['rebuild-copy'] ?? false) {
            // Rebuild caches/allocator pages must not inflate measured registration memory.
            benchmarkCommand([PHP_BINARY, '-r',
                'require $argv[1]; (new ForumRewrite\\ReadModel\\ReadModelBuilder($argv[2], $argv[3], new ForumRewrite\\Canonical\\CanonicalRecordRepository($argv[2]), "benchmark_setup"))->rebuild();',
                dirname(__DIR__) . '/autoload.php', $repository, $database], $caseRoot);
        }
        mkdir($caseRoot . '/artifacts', 0700);
        $home = $caseRoot . '/gnupg';
        mkdir($home, 0700);
        $gpg = ['gpg', '--homedir', $home, '--batch', '--pinentry-mode', 'loopback', '--passphrase', ''];
        benchmarkCommand([...$gpg, '--quick-generate-key', 'registration-benchmark <benchmark@example.invalid>', 'ed25519', 'sign', '0'], $caseRoot);
        $publicKey = benchmarkCommand([...$gpg, '--armor', '--export'], $caseRoot);
        $service = new LocalWriteService($repository, $database, $caseRoot . '/artifacts', new CanonicalRecordRepository($repository));
        $pdo = new PDO('sqlite:' . $database);
        $before = benchmarkCounts($pdo);
        $anchor = $pdo->query("SELECT post_id FROM posts WHERE author_identity_id IS NULL AND parent_id IS NULL ORDER BY sequence_number LIMIT 1")->fetchColumn();
        $pdo = null;
        fwrite(STDERR, 'Running ' . $case . "...\n");
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $started = hrtime(true);
        if ($case === 'fresh_signed') {
            $prepared = $service->prepareIdentityBootstrap(['public_key' => $publicKey]);
            file_put_contents($caseRoot . '/canonical.txt', $prepared['canonical_record']);
            benchmarkCommand([...$gpg, '--armor', '--detach-sign', '--output', $caseRoot . '/signature.asc', $caseRoot . '/canonical.txt'], $caseRoot);
            $result = $service->createIdentityBootstrap([
                'prepare_token' => $prepared['prepare_token'],
                'canonical_record' => $prepared['canonical_record'],
                'detached_signature' => file_get_contents($caseRoot . '/signature.asc'),
            ]);
        } else {
            if ($anchor === false) {
                throw new RuntimeException('Historical case requires an existing anonymous root post.');
            }
            $result = $service->linkIdentity(['public_key' => $publicKey, 'bootstrap_post_id' => $anchor]);
        }
        $milliseconds = (hrtime(true) - $started) / 1000000;
        $pdo = new PDO('sqlite:' . $database);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $after = benchmarkCounts($pdo);
        $afterMetadata = ReadModelMetadata::readMetadata($pdo);
        if ($after['profiles'] !== $before['profiles'] + 1 || $afterMetadata['repository_head'] !== $result['commit_sha']
            || $pdo->query('PRAGMA quick_check')->fetchColumn() !== 'ok') {
            throw new RuntimeException('Registration postconditions failed.');
        }
        $report['cases'][$case] = [
            'wall_ms' => $milliseconds,
            'timings_ms' => $result['timings'],
            'identity_update_metrics' => $result['identity_update_metrics'] ?? [],
            'preparation_timings_ms' => $case === 'fresh_signed' ? $prepared['timings'] : null,
            'counts_before' => $before, 'counts_after' => $after,
            'rebuild_reason' => $afterMetadata['rebuild_reason'],
            'process_peak_memory_bytes' => memory_get_peak_usage(true),
            'quick_check' => 'ok',
        ];
        file_put_contents($output . '/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        fwrite(STDERR, sprintf("%s: %.1f ms; %s\n", $case, $milliseconds, $afterMetadata['rebuild_reason']));
        benchmarkCommand(['gpgconf', '--homedir', $home, '--kill', 'all'], $caseRoot);
        // Generated test secrets are never retained in the benchmark fixture.
        ForumRewrite\Images\ImageBackup::removeTree($home);
        $home = null;
    }
    if (hash_file('sha256', $sourceDatabase) !== $sourceHash
        || trim(benchmarkCommand(['git', '-C', $sourceRepository, 'rev-parse', 'HEAD'], $output)) !== $sourceHead
        || trim(benchmarkCommand(['git', '-C', $sourceRepository, 'status', '--porcelain', '--untracked-files=all'], $output)) !== '') {
        throw new RuntimeException('Source inputs changed during the benchmark.');
    }
    $report['source_inputs_unchanged'] = true;
    $report['finished_at'] = gmdate('c');
    file_put_contents($output . '/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    echo 'Report: ' . $output . "/report.json\n";
} catch (Throwable $error) {
    if ($home !== null && is_dir($home)) {
        try {
            benchmarkCommand(['gpgconf', '--homedir', $home, '--kill', 'all'], dirname($home));
            ForumRewrite\Images\ImageBackup::removeTree($home);
        } catch (Throwable) {
            fwrite(STDERR, 'Cleanup required for generated test key directory: ' . $home . "\n");
        }
    }
    fwrite(STDERR, 'Benchmark failed: ' . $error->getMessage() . "\n");
    exit(1);
}

/** @param list<string> $arguments */
function benchmarkCommand(array $arguments, string $work): string
{
    $stdout = $work . '/command.stdout';
    $stderr = $work . '/command.stderr';
    $process = proc_open($arguments, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']], $pipes);
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Command failed (' . $arguments[0] . '): ' . (is_file($stderr) ? file_get_contents($stderr) : 'could not start'));
    }
    return file_get_contents($stdout);
}

/** @return array<string,int> */
function benchmarkCounts(PDO $pdo): array
{
    $counts = [];
    foreach (['posts', 'threads', 'profiles', 'activity'] as $table) {
        $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
    return $counts;
}
