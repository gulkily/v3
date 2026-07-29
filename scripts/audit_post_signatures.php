<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Security\OpenPgpSignatureVerifier;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);

if (in_array($argv[1] ?? '', ['-h', '--help'], true)) {
    fwrite(STDOUT, usage());
    exit(0);
}

$repositoryRoot = normalizePath($argv[1] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot)));
$repository = new CanonicalRecordRepository($repositoryRoot);
$verifier = new OpenPgpSignatureVerifier();
$counts = [
    'signed_valid' => 0,
    'missing_signature' => 0,
    'invalid_signature' => 0,
    'unknown_author_key' => 0,
    'anonymous_unsigned' => 0,
];
$rows = [];

foreach (postRecordPaths($repositoryRoot) as $relativePath) {
    $contents = (string) file_get_contents($repositoryRoot . '/' . $relativePath);
    try {
        $post = $repository->loadPost($relativePath);
    } catch (Throwable $throwable) {
        $counts['invalid_signature'] += 1;
        $rows[] = ['invalid_signature', postIdFromPath($relativePath), $relativePath, '', 'post record parse failed: ' . $throwable->getMessage()];
        continue;
    }

    $authorIdentityId = $post->authorIdentityId ?? '';
    if ($authorIdentityId === '') {
        $counts['anonymous_unsigned'] += 1;
        $rows[] = ['anonymous_unsigned', $post->postId, $relativePath, '', 'post has no author identity'];
        continue;
    }

    $signaturePath = signaturePath($repositoryRoot, $relativePath);
    if ($signaturePath === null) {
        $counts['missing_signature'] += 1;
        $rows[] = ['missing_signature', $post->postId, $relativePath, $authorIdentityId, 'no adjacent detached signature'];
        continue;
    }

    $key = publicKeyForIdentity($repositoryRoot, $repository, $authorIdentityId);
    if ($key === null) {
        $counts['unknown_author_key'] += 1;
        $rows[] = ['unknown_author_key', $post->postId, $relativePath, $authorIdentityId, 'identity or public key record not found'];
        continue;
    }

    [$publicKey, $expectedFingerprint] = $key;
    $verification = $verifier->verifyDetached(
        $publicKey,
        $contents,
        (string) file_get_contents($repositoryRoot . '/' . $signaturePath),
        $expectedFingerprint
    );

    if ($verification['ok']) {
        $counts['signed_valid'] += 1;
        $rows[] = ['signed_valid', $post->postId, $relativePath, $authorIdentityId, $signaturePath];
        continue;
    }

    $counts['invalid_signature'] += 1;
    $rows[] = ['invalid_signature', $post->postId, $relativePath, $authorIdentityId, $verification['status']];
}

fwrite(STDOUT, "Post Signature Audit\n");
printField('repository_root', $repositoryRoot);
printField('total_posts', (string) count($rows));
foreach ($counts as $status => $count) {
    printField($status, (string) $count);
}

fwrite(STDOUT, "\nstatus\tpost_id\trecord_path\tauthor_identity_id\tdetails\n");
foreach ($rows as $row) {
    fwrite(STDOUT, implode("\t", $row) . "\n");
}

exit(($counts['invalid_signature'] + $counts['unknown_author_key']) > 0 ? 2 : 0);

function usage(): string
{
    return <<<'TEXT'
Usage:
  php scripts/audit_post_signatures.php [repository_root]

Scans canonical post records and reports signed_valid, missing_signature,
invalid_signature, unknown_author_key, and anonymous_unsigned counts.

TEXT;
}

function normalizePath(string $path): string
{
    $real = realpath($path);
    return $real !== false ? $real : rtrim($path, '/');
}

/**
 * @return list<string>
 */
function postRecordPaths(string $repositoryRoot): array
{
    $paths = [];
    $postsRoot = $repositoryRoot . '/records/posts';
    if (!is_dir($postsRoot)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($postsRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if (!$item->isFile() || !str_ends_with($item->getFilename(), '.txt')) {
            continue;
        }

        $paths[] = str_replace('\\', '/', substr($item->getPathname(), strlen($repositoryRoot) + 1));
    }

    sort($paths);
    return $paths;
}

function postIdFromPath(string $relativePath): string
{
    return preg_replace('/\.txt$/', '', basename($relativePath)) ?? basename($relativePath);
}

function signaturePath(string $repositoryRoot, string $relativePath): ?string
{
    foreach ([$relativePath . '.asc', preg_replace('/\.txt$/', '.sig', $relativePath) ?: ''] as $candidate) {
        if ($candidate !== '' && is_file($repositoryRoot . '/' . $candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * @return array{string,string}|null
 */
function publicKeyForIdentity(string $repositoryRoot, CanonicalRecordRepository $repository, string $authorIdentityId): ?array
{
    if (!str_starts_with($authorIdentityId, 'openpgp:')) {
        return null;
    }

    $fingerprintLower = substr($authorIdentityId, strlen('openpgp:'));
    if ($fingerprintLower === '' || preg_match('/^[a-f0-9]+$/', $fingerprintLower) !== 1) {
        return null;
    }

    try {
        $identity = $repository->loadIdentity(CanonicalPathResolver::identity($fingerprintLower));
        $publicKeyPath = CanonicalPathResolver::publicKey($identity->signerFingerprint);
        if (!is_file($repositoryRoot . '/' . $publicKeyPath)) {
            return null;
        }

        $publicKey = $repository->loadPublicKey($publicKeyPath);
    } catch (Throwable) {
        return null;
    }

    return [$publicKey->armoredKey, $identity->signerFingerprint];
}

function printField(string $name, string $value): void
{
    fwrite(STDOUT, $name . ': ' . $value . "\n");
}
