<?php

declare(strict_types=1);

// Standalone: run only after the launcher checks PHP compatibility, before autoload/bootstrap.
$mode = $argv[1] ?? 'report';
if (!in_array($mode, ['startup', 'report'], true)) {
    fwrite(STDERR, "Unknown local dependency report mode.\n");
    exit(1);
}
$gitReady = ($argv[2] ?? '1') === '1';
$platform = $argv[3] ?? 'generic';
$output = $mode === 'report' ? STDOUT : STDERR;
$failed = false;
$identified = false;
$identify = static function () use ($output, &$identified): void {
    if (!$identified) {
        fwrite($output, 'Selected PHP CLI: ' . PHP_BINARY . ' (PHP ' . PHP_VERSION . ")\n"
            . 'CLI configuration: ' . (php_ini_loaded_file() ?: 'no php.ini loaded') . "; inspect php --ini and php -m in this terminal.\n");
        $identified = true;
    }
};
$result = static function (bool $available, string $name, string $guidance = '') use ($mode, $output, &$failed, $identify): void {
    if (!$available) {
        $failed = true;
        $identify();
        fwrite($output, "FAIL: {$name}. {$guidance}\n");
    } elseif ($mode === 'report') {
        fwrite($output, "OK: {$name}\n");
    }
};
$extensionGuidance = static function (string $extension, string $package) use ($platform): string {
    $guidance = "Install/enable {$extension} for the selected PHP CLI, then retry the same command with the same options.";
    if ($platform === 'ubuntu-24.04' && PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 3) {
        $guidance .= " Ubuntu 24.04/APT with PHP 8.3: sudo apt-get install php8.3-{$package} (manual, administrator privileges; omit sudo in a root shell).";
    }
    return $guidance;
};
if ($mode === 'report') {
    $identify();
}

$pdoReady = class_exists(PDO::class, false);
$result($pdoReady, 'PDO extension', $extensionGuidance('PDO', 'common'));
$sqliteReady = $pdoReady && in_array('sqlite', PDO::getAvailableDrivers(), true);
if ($pdoReady) {
    $result($sqliteReady, 'PDO SQLite driver', $extensionGuidance('PDO SQLite', 'sqlite3') . ' The sqlite3 executable is not a replacement.');
} elseif ($mode === 'report') {
    fwrite($output, "SKIP: PDO SQLite opening requires PDO.\n");
}
if ($sqliteReady) {
    try {
        $database = new PDO('sqlite::memory:');
        $database->query('SELECT 1')->fetchColumn();
        $result(true, 'In-memory SQLite opening');
    } catch (Throwable $error) {
        $result(false, 'In-memory SQLite opening failed', 'Check the selected CLI SQLite installation and host restrictions. No application database was opened; this is separate from missing, unwritable, or corrupt application databases.');
    }
}

$mbstringReady = extension_loaded('mbstring');
$result($mbstringReady, 'mbstring extension', $extensionGuidance('mbstring', 'mbstring'));
if ($mbstringReady) {
    $missing = array_filter(['mb_check_encoding', 'mb_convert_encoding', 'mb_ord', 'mb_strtolower'], static fn (string $name): bool => !function_exists($name));
    $result($missing === [], 'mbstring functions' . ($missing === [] ? '' : ' unavailable: ' . implode(', ', $missing)), 'Check disable_functions and host policy for the selected PHP CLI; enable the required functions or use another supported environment.');
}

$processFunctions = ['proc_open', 'proc_get_status', 'proc_terminate', 'proc_close', 'stream_select'];
$missing = array_filter($processFunctions, static fn (string $name): bool => !function_exists($name));
$processReady = $missing === [];
$restrictionGuidance = 'Check disable_functions and host execution restrictions for the selected PHP CLI; adjust host configuration or use another supported environment. Installing an executable does not enable disabled PHP functions.';
$result($processReady, 'Server process functions' . ($processReady ? '' : ' unavailable: ' . implode(', ', $missing)), $restrictionGuidance);
if ($processReady) {
    $process = @proc_open([PHP_BINARY, '-r', 'echo "v3-process-check";'], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    $available = false;
    if (is_resource($process)) {
        $text = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $available = proc_close($process) === 0 && $text === 'v3-process-check';
    }
    $result($available, 'PHP child process execution', 'Check PHP_BINARY executable permissions and host process restrictions. ' . $restrictionGuidance);
}

$execReady = function_exists('exec') && function_exists('escapeshellarg');
$result($execReady, 'Local Git shell functions (exec, escapeshellarg)', $restrictionGuidance);
if ($execReady) {
    $lines = [];
    $exitCode = 1;
    @exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('echo "v3-exec-check";') . ' 2>/dev/null', $lines, $exitCode);
    $shellReady = $exitCode === 0 && implode("\n", $lines) === 'v3-exec-check';
    $result($shellReady, 'PHP shell execution', 'Check the host shell and PHP_BINARY permissions. ' . $restrictionGuidance);
    if ($shellReady && $gitReady) {
        $lines = [];
        @exec('git --version 2>/dev/null', $lines, $exitCode);
        $result($exitCode === 0 && str_starts_with(implode("\n", $lines), 'git version '), 'Git execution from PHP', 'Check PATH, executable permissions, and PHP host execution policy. No checkout or remote credentials were tested.');
    }
}

exit($failed ? 1 : 0);
