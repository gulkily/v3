<?php

declare(strict_types=1);

// Runs the PHP built-in server behind a readable request log. Started by
// `./v3 start`; see docs/plans/dev_server_output_improvements_plan.md.
//
// On a terminal, the top two lines hold a pinned header (clickable URL, checkout,
// request counts) and requests scroll below it, so the terminal scrollback still
// works. Piped or logged output is plain text with no escape codes.

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Support\DevServerLog;

$address = null;
$mode = DevServerLog::MODE_DEFAULT;
$logFile = null;

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--address=')) {
        $address = substr($argument, strlen('--address='));
    } elseif ($argument === '--verbose') {
        $mode = DevServerLog::MODE_VERBOSE;
    } elseif ($argument === '--quiet') {
        $mode = DevServerLog::MODE_QUIET;
    } elseif (str_starts_with($argument, '--log-file=')) {
        $logFile = substr($argument, strlen('--log-file='));
    } else {
        fwrite(STDERR, "Unknown dev server option: {$argument}\n");
        exit(1);
    }
}

if ($address === null || $address === '') {
    fwrite(STDERR, "Missing --address=host:port\n");
    exit(1);
}

$root = dirname(__DIR__);
$url = 'http://' . $address . '/';
$port = preg_replace('/^.*:(\d+)$/', ':$1', $address) ?? $address;
$checkout = basename($root);
$isTty = stream_isatty(STDOUT);
$color = $isTty && getenv('NO_COLOR') === false;
$canSignal = function_exists('pcntl_async_signals');

$logHandle = $logFile !== null ? fopen($logFile, 'ab') : false;
if ($logFile !== null && $logHandle === false) {
    fwrite(STDERR, "Cannot open log file: {$logFile}\n");
    exit(1);
}

// Turn off the ^C echo while the server runs. Ctrl+C then ends the server with a
// single "stopped" line rather than a stray "^C" on the last log line.
$savedTty = null;
if (stream_isatty(STDIN) && function_exists('shell_exec')) {
    $savedTty = trim((string) shell_exec('stty -g 2>/dev/null'));
    if ($savedTty !== '') {
        shell_exec('stty -echoctl 2>/dev/null');
    }
}
register_shutdown_function(static function () use (&$savedTty): void {
    if ($savedTty !== null && $savedTty !== '') {
        shell_exec('stty ' . escapeshellarg($savedTty) . ' 2>/dev/null');
    }
});

putenv('V3_DEV_LOG=1');
$process = proc_open(
    [PHP_BINARY, '-S', $address, '-t', $root . '/public', $root . '/public/router.php'],
    [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    $root,
);
if (!is_resource($process)) {
    fwrite(STDERR, "Could not start the PHP development server.\n");
    exit(1);
}
$serverErrors = $pipes[2];

$stopping = false;
$resized = false;
if ($canSignal) {
    pcntl_async_signals(true);
    $stop = static function () use (&$stopping): void {
        $stopping = true;
    };
    pcntl_signal(SIGINT, $stop);
    pcntl_signal(SIGTERM, $stop);
    pcntl_signal(SIGWINCH, static function () use (&$resized): void {
        $resized = true;
    });
}

function terminalRows(): int
{
    $size = trim((string) shell_exec('stty size 2>/dev/null'));
    $parts = explode(' ', $size);

    return isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 24;
}

function terminalColumns(): int
{
    $size = trim((string) shell_exec('stty size 2>/dev/null'));
    $parts = explode(' ', $size);

    return isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : 80;
}

// The header lives on rows 1-2 and the scrolling region starts at row 3, so the
// header stays put while requests scroll underneath it.
$headerOn = false;
$drawHeader = null;
$applyLayout = null;
if ($isTty && $canSignal) {
    $drawHeader = static function (int $requests, int $errors) use ($url, $checkout): void {
        $columns = terminalColumns();
        // OSC 8 makes the URL clickable in terminals that support it; others show the text.
        $link = "\e]8;;{$url}\e\\{$url}\e]8;;\e\\";
        $stats = "  |  {$checkout}  |  req {$requests}  5xx {$errors}";
        $stats = substr($stats, 0, max(0, $columns - strlen($url)));
        $rule = str_repeat('-', $columns);

        fwrite(STDOUT, "\e7\e[1;1H\e[2K{$link}{$stats}\e[2;1H\e[2K\e[2m{$rule}\e[0m\e8");
        fflush(STDOUT);
    };
    $applyLayout = static function () use (&$headerOn): void {
        $rows = terminalRows();
        if ($rows < 6) {
            $headerOn = false;
            fwrite(STDOUT, "\e[r");
            return;
        }
        $headerOn = true;
        fwrite(STDOUT, "\e[3;{$rows}r\e[{$rows};1H");
        fflush(STDOUT);
    };
}

// The log file always gets plain text, even when the terminal output is colored.
$emit = static function (string $text, ?string $plainText = null) use (&$logHandle): void {
    fwrite(STDOUT, $text . "\n");
    fflush(STDOUT);
    if ($logHandle !== false) {
        fwrite($logHandle, ($plainText ?? $text) . "\n");
    }
};

if ($applyLayout !== null) {
    fwrite(STDOUT, "\e[2J\e[H");
    $applyLayout();
}
if ($headerOn) {
    $drawHeader(0, 0);
    fwrite(STDOUT, "\e[" . terminalRows() . ";1H");
} else {
    $emit("v3 {$port} listening on {$url}  ({$checkout})");
}

$requestCount = 0;
$errorCount = 0;
$lastPrintedAt = null;

while (!$stopping) {
    if ($resized && $applyLayout !== null) {
        $resized = false;
        $applyLayout();
        $drawHeader($requestCount, $errorCount);
    }

    $readable = [$serverErrors];
    $write = null;
    $except = null;
    $ready = @stream_select($readable, $write, $except, 0, 200000);
    if ($ready === false) {
        continue;
    }
    if ($ready === 0) {
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        continue;
    }

    $line = fgets($serverErrors);
    if ($line === false) {
        break;
    }

    $request = DevServerLog::parseRouterLine($line);
    if ($request === null) {
        if (!DevServerLog::isBuiltInNoise($line)) {
            $emit(rtrim($line, "\r\n"));
        }
        continue;
    }

    $now = time();
    $requestCount++;
    if ($request['status'] >= 500) {
        $errorCount++;
    }

    if (DevServerLog::isVisible($request, $mode)) {
        if (DevServerLog::needsGapBefore($lastPrintedAt, $now)) {
            $emit('');
        }
        $emit(
            DevServerLog::formatLine($request, $port, $now, $color),
            DevServerLog::formatLine($request, $port, $now, false),
        );
        $lastPrintedAt = $now;
    }

    if ($headerOn) {
        $drawHeader($requestCount, $errorCount);
    }
}

if ($canSignal) {
    pcntl_signal(SIGINT, SIG_DFL);
    pcntl_signal(SIGTERM, SIG_DFL);
}
$status = proc_get_status($process);
if ($status['running']) {
    proc_terminate($process, SIGTERM);
}
fclose($serverErrors);
proc_close($process);

if ($headerOn) {
    $rows = terminalRows();
    fwrite(STDOUT, "\e[r\e[{$rows};1H\n");
}
$emit("v3 {$port} stopped");
if ($logHandle !== false) {
    fclose($logHandle);
}
exit(0);
