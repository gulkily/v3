<?php
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Support\DeploymentSettings;
use ForumRewrite\Support\PublicKeyPublisher;
use ForumRewrite\Support\PublicKeyPublishDestination;

try {
    $options = []; $destination = null;
    foreach (array_slice($argv, 1) as $arg) {
        if (in_array($arg, ['--help', '-h'], true)) {
            echo "Usage: ./v3 publish-keys DESTINATION [--repository-root=PATH] [--timeout=SECONDS]\n\nPreview public-key identities without publishing. Destination must be an explicit HTTP(S) URL.\n";
            exit(0);
        }
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
    $rows = $publisher->preview($client);
    echo 'Destination: ' . $client->url . "\nMode: preview\n";
    $failed = false;
    foreach ($rows as $row) {
        echo "\nFingerprint: {$row['fingerprint']}\nName: {$row['name']}\nRegistration-Name: {$row['registration_name']}\nState: {$row['state']}\n";
        if ($row['detail'] !== '') echo 'Detail: ' . preg_replace('/[\p{C}]/u', ' ', $row['detail']) . "\n";
        if (in_array($row['state'], ['refused', 'unknown'], true)) $failed = true;
    }
    echo "\nIdentities: " . count($rows) . "\n";
    exit($failed ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Error: ' . preg_replace('/[\p{C}]/u', ' ', $error->getMessage()) . "\n");
    exit(1);
}
