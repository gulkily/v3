<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

if (in_array('--help', array_slice($argv, 1), true) || in_array('-h', array_slice($argv, 1), true)) {
    printUsage();
    exit(0);
}

$logPath = '/var/log/forum-agent-replies.log';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--log=')) {
        $logPath = substr($arg, strlen('--log='));
        continue;
    }

    fwrite(STDERR, "Unknown argument: {$arg}\n\n");
    printUsage();
    exit(2);
}

$appRoot = realpath($projectRoot) ?: $projectRoot;
$cronLine = '* * * * * cd ' . shellEscapeForCron($appRoot)
    . ' && php scripts/run_agent_reply_requests.php --quiet --limit=10 >> '
    . shellEscapeForCron($logPath) . ' 2>&1';

fwrite(STDOUT, "Agent reply request cron reference\n");
fwrite(STDOUT, "\nInstall:\n");
fwrite(STDOUT, "  crontab -e\n");
fwrite(STDOUT, "  " . $cronLine . "\n");
fwrite(STDOUT, "\nCheck before/after:\n");
fwrite(STDOUT, "  ./v3 private-config view\n");
fwrite(STDOUT, "  ./v3 agent-reply test\n");
fwrite(STDOUT, "  ./v3 agent-reply test-local\n");
fwrite(STDOUT, "  php scripts/run_agent_reply_requests.php --dry-run\n");
fwrite(STDOUT, "  tail -f " . shellEscapeForCron($logPath) . "\n");
fwrite(STDOUT, "\nNotes:\n");
fwrite(STDOUT, "  Run cron as the same user that can write the repository, SQLite database, public artifacts, and state/private/agent-reply.\n");
fwrite(STDOUT, "  The worker exits cleanly if a previous run is still active.\n");

function shellEscapeForCron(string $value): string
{
    return escapeshellarg($value);
}

function printUsage(): void
{
    fwrite(STDOUT, <<<'TEXT'
Usage:
  php scripts/agent_reply_cron_reference.php [--log=/var/log/forum-agent-replies.log]
  ./v3 agent-reply cron [--log=/var/log/forum-agent-replies.log]

Prints a concise reference for installing the queued agent reply request cron job.
It does not install or modify the crontab.

TEXT);
}
