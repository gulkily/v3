<?php

declare(strict_types=1);

// Standalone: run only after the launcher checks PHP compatibility, before autoload/bootstrap.
$mode = $argv[1] ?? 'report';
if (!in_array($mode, ['startup', 'report', 'facts'], true)) {
    fwrite(STDERR, "Unknown local dependency report mode.\n");
    exit(1);
}
$gitReady = ($argv[2] ?? '1') === '1';
$platform = $argv[3] ?? 'generic';
$output = $mode === 'report' ? STDOUT : STDERR;
$detailOutput = $mode === 'report' ? STDERR : $output;
$color = function_exists('stream_isatty') && stream_isatty($output) && getenv('NO_COLOR') === false;
$paint = static fn (string $text, string $code): string => $color ? "\033[{$code}m{$text}\033[0m" : $text;
$recovery = [];
$startupFailures = [];
$startupWarnings = [];
$failed = false;
$identified = false;
$identify = static function () use ($detailOutput, &$identified, $paint): void {
    if (!$identified) {
        fwrite($detailOutput, 'Selected PHP CLI: ' . PHP_BINARY . ' (PHP ' . PHP_VERSION . ")\n"
            . 'CLI configuration: ' . (php_ini_loaded_file() ?: 'no php.ini loaded') . '; inspect '
            . $paint('php --ini', '1;36') . ' and ' . $paint('php -m', '1;36') . " in this terminal.\n");
        $identified = true;
    }
};
$result = static function (bool $available, string $name, string $guidance = '') use ($mode, $output, &$failed, $identify, &$recovery, $paint, &$startupFailures): void {
    if (!$available) {
        $failed = true;
        if ($mode === 'report') {
            fwrite($output, '  ' . $paint("✗ FAIL: {$name}", '31') . "\n");
            $recovery[] = [$name, $guidance];
        } elseif ($mode === 'startup') {
            $startupFailures[] = [$name, $guidance];
        }
    } elseif ($mode === 'report') {
        fwrite($output, '  ' . $paint("✓ OK: {$name}", '32') . "\n");
    }
};
$skip = static function (string $name, string $reason) use ($mode, $output, $paint): void {
    if ($mode === 'report') {
        fwrite($output, '  ' . $paint("? SKIP: {$name} ({$reason})", '90') . "\n");
    }
};
$packageGuidance = array_combine(['common', 'sqlite3', 'mbstring', 'intl'], array_pad(array_slice($argv, 4, 4), 4, ''));
$extensionGuidance = static function (string $extension, string $package) use ($packageGuidance): string {
    return $packageGuidance[$package] ?: "Inspect php --ini and php -m for the selected CLI. Check PHPRC, PHP_INI_SCAN_DIR and disabled modules; use that PHP installation's instructions for {$extension}. No package mapping was verified.";
};
$pdoReady = class_exists(PDO::class, false);
$result($pdoReady, 'PDO extension', $extensionGuidance('PDO', 'common'));
$sqliteReady = $pdoReady && in_array('sqlite', PDO::getAvailableDrivers(), true);
if ($pdoReady) {
    $result($sqliteReady, 'PDO SQLite driver', $extensionGuidance('PDO SQLite', 'sqlite3') . ' The sqlite3 executable is not a replacement.');
} else {
    $skip('PDO SQLite driver', 'requires PDO');
}
if ($sqliteReady) {
    try {
        $database = new PDO('sqlite::memory:');
        $database->query('SELECT 1')->fetchColumn();
        $result(true, 'In-memory SQLite opening');
    } catch (Throwable $error) {
        $result(false, 'In-memory SQLite opening failed', 'Check the selected CLI SQLite installation and host restrictions. No application database was opened; this is separate from missing, unwritable, or corrupt application databases.');
    }
} else {
    $skip('In-memory SQLite opening', 'requires PDO SQLite');
}

$mbstringReady = extension_loaded('mbstring');
$result($mbstringReady, 'mbstring extension', $extensionGuidance('mbstring', 'mbstring'));
if ($mbstringReady) {
    $missing = array_filter(['mb_check_encoding', 'mb_convert_encoding', 'mb_ord', 'mb_strtolower'], static fn (string $name): bool => !function_exists($name));
    $result($missing === [], 'mbstring functions' . ($missing === [] ? '' : ' unavailable: ' . implode(', ', $missing)), 'Check disable_functions and host policy for the selected PHP CLI; enable the required functions or use another supported environment.');
} else {
    $skip('mbstring functions', 'requires mbstring');
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
} else {
    $skip('PHP child process execution', 'requires server process functions');
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
    } else {
        $skip('Git execution from PHP', 'requires usable Git and PHP shell execution');
    }
} else {
    $skip('PHP shell execution', 'requires exec and escapeshellarg');
    $skip('Git execution from PHP', 'requires PHP shell execution');
}

$optional = static function (bool $available, string $name, string $guidance) use ($mode, $output, $identify, &$recovery, $paint, &$startupWarnings): void {
    if (!$available) {
        if ($mode === 'report') {
            fwrite($output, '  ' . $paint("! WARN: Optional {$name} unavailable", '33') . "\n");
            $recovery[] = ['Optional ' . $name, $guidance];
        } elseif ($mode === 'startup') {
            $startupWarnings[] = [$name, $guidance];
        }
    } elseif ($mode === 'report') {
        fwrite($output, '  ' . $paint("✓ OK: Optional {$name}", '32') . "\n");
    }
};
$optional(class_exists(Normalizer::class, false), 'intl normalization', 'Unicode remains usable, but combining marks are rejected without normalization support. ' . $extensionGuidance('intl', 'intl'));
$signalFunctions = ['pcntl_async_signals', 'pcntl_signal'];
$signalsReady = array_filter($signalFunctions, static fn (string $name): bool => !function_exists($name)) === [];
foreach (['SIGINT', 'SIGTERM', 'SIGWINCH', 'SIG_DFL'] as $constant) {
    $signalsReady = $signalsReady && defined($constant);
}
$optional($signalsReady, 'PCNTL signal support', 'Use plain logging and launcher-managed shutdown; enable PCNTL for the selected CLI only for terminal enhancements.');
$optional(function_exists('shell_exec'), 'terminal shell execution (shell_exec)', 'Terminal enhancements are disabled; plain logging and shutdown remain available. Review host policy only if these enhancements are wanted.');

if ($mode === 'report') {
    fwrite($detailOutput, $paint('Runtime details', '1') . "\n");
    $identify();
    if ($recovery !== []) {
        fwrite($detailOutput, "\n" . $paint('Recovery guidance', '1') . "\n");
        foreach ($recovery as [$name, $guidance]) {
            // Put install commands on their own line, emphasized only on a terminal.
            $guidance = preg_replace_callback('/ (sudo apt-get install [a-z0-9.\-]+) /',
                static fn (array $match): string => "\n  " . $paint($match[1], '1;36') . "\n  ", $guidance);
            fwrite($detailOutput, $paint($name, '1') . ":\n{$guidance}\n\n");
        }
    }
}

if ($mode === 'startup' && ($startupFailures !== [] || $startupWarnings !== [])) {
    if ($failed) {
        fwrite($output, $paint('Cannot start v3: required PHP capabilities are unavailable.', '31') . "\n");
    }
    $packages = [];
    foreach ($startupFailures as [$name, $guidance]) {
        fwrite($output, '  ' . $paint("✗ FAIL: {$name}", '31') . "\n");
        if (preg_match('/sudo apt-get install (php8\.[13]-[a-z0-9]+)/', $guidance, $match)) {
            $packages[] = $match[1];

        } else {
            fwrite($output, "    {$guidance}\n");
        }
    }
    foreach ($startupWarnings as [$name, $guidance]) {
        $impact = match ($name) {
            'intl normalization' => 'combining marks are rejected',
            'PCNTL signal support' => 'plain logging and launcher-managed shutdown remain available',
            default => 'terminal enhancements are disabled; plain logging remains available',
        };
        fwrite($output, '  ' . $paint("! WARN: Optional {$name} unavailable", '33') . " ({$impact}).\n");
    }
    fwrite($output, "\nSelected PHP CLI: " . PHP_BINARY . ' (PHP ' . PHP_VERSION . ")\n"
        . 'CLI configuration: ' . (php_ini_loaded_file() ?: 'no php.ini loaded') . "\n");
    if ($packages !== []) {
        fwrite($output, "\nInstall required extensions (verified Ubuntu distribution packages):\n  "
            . $paint('sudo apt-get install ' . implode(' ', array_unique($packages)), '1;36') . "\n");
    }
    $optionalInstall = false;
    foreach ($startupWarnings as [$name, $guidance]) {
        if (preg_match('/sudo apt-get install (php8\.[13]-[a-z0-9]+)/', $guidance, $match)) {
            $optionalInstall = true;
            fwrite($output, "\nOptional Unicode normalization:\n  " . $paint('sudo apt-get install ' . $match[1], '1;36') . "\n");
        } elseif ($name === 'intl normalization') {
            fwrite($output, "Optional Unicode normalization: {$guidance}\n");
        }
    }
    if ($packages !== [] || $optionalInstall) {
        fwrite($output, "Run install commands manually; administrator privileges required (omit sudo as root).\n");
    }
    fwrite($output, "\nDetails: " . $paint('./v3 check-dependencies', '1;36') . "\n");
    if ($failed) {
        fwrite($output, "After setup, retry your ./v3 start command with the same options.\n");
    }
}

// Stable machine facts share the very same capability probes as human reports.
if ($mode === 'facts') {
    foreach ([
        'version' => PHP_VERSION_ID, 'binary' => PHP_BINARY,
        'pdo' => (int) $pdoReady, 'sqlite' => (int) $sqliteReady,
        'mbstring' => (int) $mbstringReady,
        'intl' => (int) class_exists(Normalizer::class, false),
        'ready' => (int) !$failed,
    ] as $key => $value) {
        fwrite(STDOUT, $key . "\t" . $value . "\n");
    }
}

exit($failed ? 1 : 0);
