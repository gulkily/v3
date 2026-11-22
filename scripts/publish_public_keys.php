<?php
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Support\DeploymentSettings;
use ForumRewrite\Support\PublicKeyPublisher;
use ForumRewrite\Support\PublicKeyPublishDestination;

try {
    $options = []; $destination = null; $keys = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (in_array($arg, ['--help', '-h'], true)) {
            echo "Usage:\n  ./v3 publish-keys DESTINATION [--plan=FILE] [--repository-root=PATH] [--timeout=SECONDS]\n  ./v3 publish-keys DESTINATION --publish=FILE --key=FINGERPRINT [--key=FINGERPRINT ...]\n\nDefault: preview only. --plan saves a new Key: value review file outside the canonical repository.\n--publish uses that reviewed source and destination; select full fingerprints with --key.\nExisting identities are skipped, never updated. Names and retirements must agree.\nAfter a partial/uncertain result, resolve the reported issue and rerun the same selection.\nEach selected key is sent once, then verified by read-back. Private keys, approvals and message history stay local.\n";
            exit(0);
        }
        if (str_starts_with($arg, '--key=') && strlen($arg) > 6) { $keys[] = substr($arg, 6); continue; }
        if (preg_match('/^--(repository-root|timeout|plan|publish)=(.+)$/D', $arg, $match)) {
            if (isset($options[$match[1]])) throw new RuntimeException('Repeated option: ' . $match[1]);
            $options[$match[1]] = $match[2];
        } elseif (!str_starts_with($arg, '-') && $destination === null) $destination = $arg;
        else throw new RuntimeException('Unknown or repeated argument. Run ./v3 publish-keys --help.');
    }
    if ($destination === null) throw new RuntimeException('Name an explicit destination. Run ./v3 publish-keys --help.');
    if ((isset($options['plan']) && isset($options['publish'])) || ($keys !== [] && !isset($options['publish']))) throw new RuntimeException('Use --key only with --publish; --plan and --publish are separate operations.');
    if (isset($options['timeout']) && !preg_match('/^[1-9][0-9]{0,2}$/D', $options['timeout'])) throw new RuntimeException('Invalid timeout.');
    $client = new PublicKeyPublishDestination($destination, (int) ($options['timeout'] ?? 10));
    $publisher = new PublicKeyPublisher($options['repository-root'] ?? (DeploymentSettings::value('FORUM_REPOSITORY_ROOT') ?: dirname(__DIR__) . '/state/local_repository'));
    $rows = isset($options['publish']) ? $publisher->publish($options['publish'], $keys, $client) : $publisher->preview($client);
    if (isset($options['plan'])) $publisher->writePlan($options['plan'], $client, $rows);
    echo 'Destination: ' . $client->url . "\nMode: " . (isset($options['publish']) ? 'publish' : 'preview') . "\n";
    if (isset($options['plan'])) echo 'Plan: ' . $options['plan'] . "\n";
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
