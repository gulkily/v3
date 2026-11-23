<?php
// Run with ./v3 contacts; all scripts: scripts/README.md.

declare(strict_types=1);

use ForumRewrite\Contacts\PrivateDirectory;
use ForumRewrite\Contacts\ContactBook;

require dirname(__DIR__) . '/autoload.php';

$usage = "Usage: ./v3 contacts [--data-dir=/private/path] [--port=PORT]\n";
$arguments = array_slice($argv, 1);
if (in_array('-h', $arguments, true) || in_array('--help', $arguments, true)) {
    echo $usage;
    exit(0);
}
$options = [];
foreach ($arguments as $argument) {
    if (!preg_match('/^--(data-dir|port)=(.+)$/Ds', $argument, $match)
        || isset($options[$match[1]]) || ($match[1] === 'port' && !preg_match('/^\d{1,5}$/D', $match[2]))) {
        fwrite(STDERR, $usage);
        exit(2);
    }
    $options[$match[1]] = $match[2];
}
$port = (int) ($options['port'] ?? 0);
if ($port > 65535) {
    fwrite(STDERR, $usage);
    exit(2);
}

$project = dirname(__DIR__);
$defaultBase = getenv('XDG_DATA_HOME') ?: ((getenv('HOME') ?: '') . '/.local/share');
if (!isset($options['data-dir']) && (!str_starts_with($defaultBase, '/') || $defaultBase === '/.local/share')) {
    fwrite(STDERR, "Set an absolute XDG_DATA_HOME or pass --data-dir=/private/path.\n");
    exit(1);
}
umask(0077);
try {
    $directory = PrivateDirectory::prepare($options['data-dir'] ?? $defaultBase . '/v3/contacts', $project);
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

$secret = bin2hex(random_bytes(32));
$book = new ContactBook($directory, $secret);
try {
    $book->read();
    $book->clearExpiredPreviews();
} catch (RuntimeException) {
    fwrite(STDERR, "Could not open the Contacts book. Check its permissions or restore a valid private backup.\n");
    exit(1);
}
$nextCleanup = time() + 60;
$environment = getenv();
unset($environment['PHP_CLI_SERVER_WORKERS']);
$environment['V3_CONTACTS_SECRET'] = $secret;
$environment['V3_CONTACTS_DIRECTORY'] = $directory;
$control = getenv('V3_DEV_CONTROL') === '1' ? STDIN : null;
if ($control !== null) {
    stream_set_blocking($control, false);
}

foreach (array_unique([$port, 0]) as $candidate) {
    $server = proc_open([
        PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=0',
        '-d', 'upload_tmp_dir=' . $directory, '-d', 'sys_temp_dir=' . $directory,
        '-d', 'upload_max_filesize=64M', '-d', 'post_max_size=65M',
        '-S', '127.0.0.1:' . $candidate, '-t', $project . '/contacts/public', $project . '/contacts/router.php',
    ], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']], $pipes, $project, $environment);
    if (!is_resource($server)) {
        break;
    }
    stream_set_blocking($pipes[2], false);
    $startup = '';
    $bound = false;
    $stopping = false;
    $deadline = microtime(true) + 3;
    try {
        while (proc_get_status($server)['running']) {
            if (time() >= $nextCleanup) {
                $book->clearExpiredPreviews();
                $nextCleanup = time() + 60;
            }
            if ($control !== null && (fread($control, 1024) !== '' || feof($control))) {
                $stopping = true;
                break;
            }
            $output = fread($pipes[2], 8192);
            if (!$bound) {
                $startup .= $output;
                if (preg_match('~Development Server \((http://127\.0\.0\.1:\d+)\) started~', $startup, $match)) {
                    echo "Open Contacts at {$match[1]}/#{$secret}\n";
                    $bound = true;
                } elseif (microtime(true) >= $deadline) {
                    break;
                }
            }
            usleep(20000);
        }
    } finally {
        proc_terminate($server);
        for ($count = 0; $count < 25 && proc_get_status($server)['running']; $count++) {
            usleep(20000);
        }
        if (proc_get_status($server)['running']) {
            proc_terminate($server, 9);
        }
        fclose($pipes[2]);
        proc_close($server);
        $book->clearPreviews();
    }
    if ($stopping) {
        exit(0);
    }
    if ($bound) {
        break;
    }
}
fwrite(STDERR, "Contacts could not start or the listener stopped. Run ./v3 contacts to retry.\n");
exit(1);
