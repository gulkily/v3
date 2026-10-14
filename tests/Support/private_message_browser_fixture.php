<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Messaging\PrivateMessageStore;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Support\LocalRepositoryBootstrap;

// CLI-only fixture generator: all mutable state stays inside a new test directory.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = $argv[1];
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (($input['action'] ?? '') === 'messaging-flag') {
    file_put_contents($root . '/repository/records/instance/feature-flags.txt',
        "Schema: site-feature-flags-v1\n\nFORUM_PRIVATE_MESSAGES_ENABLED: " . ($input['enabled'] ? 'true' : 'false') . "\n");
    exit;
}
if (($input['action'] ?? '') === 'chat') {
    $store = new PrivateMessageStore(new PDO('sqlite:' . $root . '/state/private/messages.sqlite3'));
    foreach ($input['messages'] as $message) {
        $store->storeEnvelope($message['id'], $message['time'], $message['sender'], $message['recipient'] ?? 'alice', 'fixture-sender', $message['envelope']);
    }
    exit;
}
if (($input['action'] ?? '') === 'arrive') {
    $store = new PrivateMessageStore(new PDO('sqlite:' . $root . '/state/private/messages.sqlite3'));
    $store->storeEnvelope('arrival', gmdate('Y-m-d\TH:i:s\Z'), 'user-01', 'alice', 'fixture-sender', $input['envelope']);
    exit;
}
if (($input['action'] ?? '') === 'revoke') {
    (new PDO('sqlite:' . $root . '/read.sqlite3'))->exec("UPDATE profiles SET is_approved = 0 WHERE username_token = 'user-60'");
    exit;
}
if (($input['action'] ?? '') === 'bad-signature') {
    (new PrivateMessageStore(new PDO('sqlite:' . $root . '/state/private/messages.sqlite3')))
        ->storeEnvelope('bad-signature', gmdate('Y-m-d\TH:i:s\Z'), 'user-59', 'alice', 'fixture-sender', $input['envelope']);
    exit;
}
symlink(dirname(__DIR__, 2) . '/templates', $root . '/templates');
symlink(dirname(__DIR__, 2) . '/public', $root . '/public');
mkdir($root . '/state/private', 0700, true);
mkdir($root . '/sessions', 0700);
LocalRepositoryBootstrap::initializeLocalRepository(dirname(__DIR__, 2), $root . '/repository');
// Backup recovery exercises the real members-only publication path, which needs
// a canonical identity as well as the synthetic conversation directory below.
if (isset($input['canonical_identity'])) {
    $profile = $input['canonical_identity'];
    $fingerprint = strtolower($profile['fingerprint']);
    if (!preg_match('/^[a-f0-9]{40}$/D', $fingerprint)) {
        throw new RuntimeException('Invalid fixture fingerprint');
    }
    $upper = strtoupper($fingerprint);
    foreach (['identity', 'public-keys', 'posts', 'approval-seeds'] as $directory) {
        if (!is_dir($root . '/repository/records/' . $directory)) {
            mkdir($root . '/repository/records/' . $directory, 0700, true);
        }
    }
    file_put_contents($root . '/repository/records/posts/backup-fixture.txt', "Post-ID: backup-fixture\nCreated-At: 2026-01-01T00:00:00Z\nBoard-Tags: identity internal\n\nBackup fixture.\n");
    file_put_contents($root . '/repository/records/public-keys/openpgp-' . $upper . '.asc', $profile['publicKey']);
    file_put_contents($root . '/repository/records/identity/identity-openpgp-' . $fingerprint . '.txt', "Post-ID: identity-openpgp-{$fingerprint}\nBoard-Tags: identity\nSubject: identity bootstrap\nUsername: alice\nIdentity-ID: openpgp:{$fingerprint}\nSigner-Fingerprint: {$upper}\nBootstrap-By-Post: backup-fixture\nBootstrap-By-Thread: backup-fixture\n\n" . $profile['publicKey']);
    file_put_contents($root . '/repository/records/approval-seeds/openpgp-' . $fingerprint . '.txt', "Approved-Identity-ID: openpgp:{$fingerprint}\nSeed-Reason: isolated backup browser fixture\n\n");
}
(new ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', new CanonicalRecordRepository($root . '/repository')))->rebuild();
$pdo = new PDO('sqlite:' . $root . '/read.sqlite3');
$add = $pdo->prepare('INSERT INTO profiles (identity_id, profile_slug, username, username_token, fallback_label,
    signer_fingerprint, bootstrap_post_id, bootstrap_thread_id, public_key, is_approved)
    VALUES (:id, :slug, :name, :name, :name, :fingerprint, :slug, :slug, :key, :approved)');
foreach ($input['profiles'] as $profile) {
    $id = 'openpgp:' . strtolower($profile['fingerprint']);
    $slug = 'openpgp-' . strtolower($profile['fingerprint']);
    if (str_starts_with($profile['name'], 'user-') || $profile['name'] === 'pending' || $profile['name'] === 'keyless') {
        $id .= '-' . $profile['name'];
        $slug .= '-' . $profile['name'];
    }
    if (isset($input['canonical_identity']) && $profile['name'] === 'alice') {
        continue;
    }
    $add->execute(['id' => $id, 'slug' => $slug, 'name' => $profile['name'], 'fingerprint' => strtoupper($profile['fingerprint']),
        'key' => $profile['publicKey'], 'approved' => $profile['name'] === 'pending' ? 0 : 1]);
    $pdo->prepare('INSERT INTO username_routes VALUES (?, ?)')->execute([$profile['name'], $id]);
}
$store = new PrivateMessageStore(new PDO('sqlite:' . $root . '/state/private/messages.sqlite3'));
for ($i = 1; $i <= 60; $i++) {
    $counterpart = sprintf('user-%02d', $i);
    $store->storeEnvelope(sprintf('fixture-%02d', $i), '2026-10-09T12:00:00Z',
        $i % 2 ? $counterpart : 'alice', $i % 2 ? 'alice' : $counterpart, 'fixture-sender',
        $i % 2 ? $input['incoming'] : $input['outgoing']);
}
