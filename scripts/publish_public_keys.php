<?php
// Run with ./v3 publish-keys --help.
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Support\DeploymentSettings;
use ForumRewrite\Support\PublicKeyPublisher;
use ForumRewrite\Support\PublicKeyPublishDestination;

try {
    $options = []; $destination = null; $keys = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (in_array($arg, ['--help', '-h'], true)) {
            echo <<<'TEXT'
Usage:
  ./v3 publish-keys <name|hostname|url> [--dry-run] [--key=FINGERPRINT ...]
  Options: --repository-root=PATH --timeout=SECONDS

Publishes eligible missing public keys. --dry-run checks without registering.
--key optionally limits either operation to full fingerprints; repeat for more keys.
Hostnames use HTTPS; known names include zenmemes. Use http:// for HTTP.
Source: FORUM_REPOSITORY_ROOT, otherwise this checkout's state/local_repository.
--repository-root chooses another source; Repository shows its resolved path.
--timeout bounds each request (1–120 seconds; default 10).
Existing keys are skipped. Name conflicts and retired keys are refused.
Rerun after a partial/unknown result; confirmed registrations are skipped.
Private keys, approvals and message history stay local.

Example: ./v3 publish-keys zenmemes.com
Preview: ./v3 publish-keys zenmemes.com --dry-run
TEXT;
            echo "\n";
            exit(0);
        }
        if (preg_match('/^--(?:plan|publish)(?:=|$)/D', $arg)) {
            throw new RuntimeException('Review files are no longer used. Use --dry-run to preview, or omit it to publish.');
        }
        if ($arg === '--dry-run') {
            if (isset($options['dry-run'])) throw new RuntimeException('Repeated option: dry-run');
            $options['dry-run'] = true; continue;
        }
        if (str_starts_with($arg, '--key=') && strlen($arg) > 6) { $keys[] = substr($arg, 6); continue; }
        if (preg_match('/^--(repository-root|timeout)=(.+)$/D', $arg, $match)) {
            if (isset($options[$match[1]])) throw new RuntimeException('Repeated option: ' . $match[1]);
            $options[$match[1]] = $match[2];
        } elseif (!str_starts_with($arg, '-') && $destination === null) $destination = $arg;
        else throw new RuntimeException('Unknown or repeated argument. Run ./v3 publish-keys --help.');
    }
    if ($destination === null) throw new RuntimeException('Name an explicit destination. Run ./v3 publish-keys --help.');
    if (isset($options['timeout']) && !preg_match('/^[1-9][0-9]{0,2}$/D', $options['timeout'])) throw new RuntimeException('Invalid timeout.');
    $client = new PublicKeyPublishDestination($destination, (int) ($options['timeout'] ?? 10));
    $publisher = new PublicKeyPublisher($options['repository-root'] ?? (DeploymentSettings::value('FORUM_REPOSITORY_ROOT') ?: dirname(__DIR__) . '/state/local_repository'));
    echo 'Repository: ' . $publisher->root . "\nDestination: " . $client->url . "\nMode: " . (isset($options['dry-run']) ? 'preview' : 'publish') . "\n";
    $rows = isset($options['dry-run']) ? $publisher->preview($client, $keys) : $publisher->publish($client, $keys);
    $failed = false;
    foreach ($rows as $row) {
        echo "\nFingerprint: {$row['fingerprint']}\nName: {$row['name']}\nState: {$row['state']}\n";
        if ($row['registration_name'] !== $row['name']) echo 'Registration-Name: ' . $row['registration_name'] . "\n";
        if (($row['destination_name'] ?? '') !== '' && $row['destination_name'] !== $row['name']) echo 'Destination-Name: ' . $row['destination_name'] . "\n";
        if ($row['detail'] !== '') echo 'Detail: ' . preg_replace('/[\p{C}]/u', ' ', $row['detail']) . "\n";
        if (in_array($row['state'], ['refused', 'unknown'], true)) $failed = true;
    }
    echo "\nIdentities: " . count($rows) . "\n";
    exit($failed ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Error: ' . preg_replace('/[\p{C}]/u', ' ', $error->getMessage()) . "\n");
    exit(1);
}
