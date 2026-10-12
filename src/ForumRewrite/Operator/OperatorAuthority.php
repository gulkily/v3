<?php

declare(strict_types=1);

namespace ForumRewrite\Operator;

use ForumRewrite\Canonical\ApprovalSeedRecordParser;
use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Security\OpenPgpSignatureVerifier;
use PDO;
use RuntimeException;

/** Derived authority; no separately mutable roles or database migration. */
final class OperatorAuthority
{
    private static array $verifiedSignatures = [];
    public static function load(PDO $pdo): array
    {
        $metadata = $pdo->query('SELECT key, value FROM metadata')->fetchAll(PDO::FETCH_KEY_PAIR);
        $root = (string) ($metadata['repository_root'] ?? '');
        if ($root === '' || !is_dir($root)) {
            throw new RuntimeException('Operator authority requires a canonical repository.');
        }
        $database = (string) ($pdo->query('PRAGMA database_list')->fetch(PDO::FETCH_ASSOC)['file'] ?? '');
        if (($database !== '' && (new ReadModelStaleMarker($database))->exists())
            || ($metadata['repository_head'] ?? '') !== ReadModelMetadata::repositoryHead($root)
            || (($metadata['repository_head'] ?? '') === 'no-git'
                && ($metadata['canonical_fingerprint'] ?? '') !== ReadModelMetadata::canonicalFingerprint($root))) {
            throw new RuntimeException('Operator authority is stale. Rebuild derived state before continuing.');
        }
        $profiles = $pdo->query('SELECT * FROM profiles')->fetchAll(PDO::FETCH_ASSOC);
        $profiles = array_column($profiles, null, 'identity_id');
        ksort($profiles);
        $roots = [];
        foreach (glob($root . '/records/approval-seeds/*.txt') ?: [] as $path) {
            $roots[] = (new ApprovalSeedRecordParser())->parse((string) file_get_contents($path))->approvedIdentityId;
        }
        $events = [];
        foreach ($pdo->query("SELECT * FROM posts WHERE board_tags_json LIKE '%\"operator\"%' AND board_tags_json LIKE '%\"identity\"%'")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $relative = CanonicalPathResolver::datedPost($row['post_id'], $row['created_at']);
            if (!is_file($root . '/' . $relative)) {
                $relative = CanonicalPathResolver::post($row['post_id']);
            }
            $contents = @file_get_contents($root . '/' . $relative);
            $signature = @file_get_contents($root . '/' . $relative . '.asc');
            if ($contents === false || $signature === false) {
                throw new RuntimeException('Operator action or signature missing.');
            }
            $post = (new PostRecordParser())->parse($contents);
            $event = OperatorAction::parse($post);
            $profile = $profiles[$post->authorIdentityId] ?? null;
            if ($profile === null || $post->postId !== $row['post_id']) {
                throw new RuntimeException('Operator action signer or record mismatch.');
            }
            $evidence = hash('sha256', json_encode([$profile['public_key'], $contents, $signature, $profile['signer_fingerprint']], JSON_THROW_ON_ERROR));
            if (!isset(self::$verifiedSignatures[$evidence])) {
                $verified = (new OpenPgpSignatureVerifier())->verifyDetached(
                    $profile['public_key'], $contents, $signature, $profile['signer_fingerprint']
                );
                if (!$verified['ok']) {
                    throw new RuntimeException('Operator history signature verification failed.');
                }
                if (count(self::$verifiedSignatures) >= 256) { self::$verifiedSignatures = []; }
                self::$verifiedSignatures[$evidence] = true;
            }
            $events[] = $event;
        }
        sort($roots);
        usort($events, static fn ($a, $b) => $a['sequence'] <=> $b['sequence']);
        $state = OperatorGraph::derive($profiles, $roots, $events);
        $state['profiles'] = $profiles;
        // Snapshot covers all account keys and action history for stale preview detection.
        $state['revision'] = hash('sha256', json_encode([$profiles, $roots, $events], JSON_THROW_ON_ERROR));
        return $state;
    }

    public static function allows(PDO $pdo, ?array $profile): bool
    {
        if ($profile === null || (int) ($profile['is_approved'] ?? 0) !== 1) {
            return false;
        }
        try {
            $state = self::load($pdo);
            $actual = $state['profiles'][$profile['identity_id']] ?? null;
            return $actual !== null && (int) $actual['is_approved'] === 1 && isset($state['accounts'][$actual['username_token']]);
        } catch (\Throwable) {
            return false;
        }
    }
}
