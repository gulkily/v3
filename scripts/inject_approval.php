<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\PresentationPathResolver;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\LocalRepositoryBootstrap;
use ForumRewrite\Write\LocalWriteService;

$projectRoot = dirname(__DIR__);
$defaultDatabasePath = LocalRepositoryBootstrap::defaultDatabasePath($projectRoot);
$command = $argv[1] ?? '';

try {
    if (!in_array($command, ['seed', 'approve'], true)) {
        throw new RuntimeException('Missing or invalid command.');
    }

    if ($command === 'seed') {
        $identityId = normalizeCliIdentityId(requireCliArgument($argv, 2, 'identity_id'));
        $seedReason = trim((string) ($argv[3] ?? 'initial approved user'));
        $repositoryRoot = $argv[4] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot));
        $databasePath = $argv[5] ?? (getenv('FORUM_DATABASE_PATH') ?: $defaultDatabasePath);

        $writer = new LocalWriteService(
            $repositoryRoot, $databasePath,
            getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: ($projectRoot . '/public'),
            new CanonicalRecordRepository($repositoryRoot),
            additionalArtifactRoots: [getenv('FORUM_STATIC_HTML_ROOT') ?: PresentationPathResolver::staticHtmlRoot($projectRoot, SiteProfileRegistry::active())],
        );
        $writer->seedApprovedIdentity($identityId, $seedReason);
        fwrite(STDOUT, "Seeded approval for {$identityId}\n");
        exit(0);
    }

    $approverIdentityId = normalizeCliIdentityId(requireCliArgument($argv, 2, 'approver_identity_id'));
    $targetIdentityId = normalizeCliIdentityId(requireCliArgument($argv, 3, 'target_identity_id'));
    $repositoryRoot = $argv[4] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot));
    $databasePath = $argv[5] ?? (getenv('FORUM_DATABASE_PATH') ?: $defaultDatabasePath);
    $artifactRoot = $argv[6] ?? (getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: ($projectRoot . '/public'));

    approveExistingUser($repositoryRoot, $databasePath, $artifactRoot, $approverIdentityId, $targetIdentityId);
    fwrite(STDOUT, "Approved {$targetIdentityId} using {$approverIdentityId}\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n\n" . usageText());
    exit(1);
}

/**
 * @return array{identity_id:string,profile_slug:string,bootstrap_post_id:string,bootstrap_thread_id:string}
 */
function loadIdentityTarget(string $repositoryRoot, string $identityId): array
{
    $fingerprint = normalizeIdentityId($identityId);
    $repository = new CanonicalRecordRepository($repositoryRoot);
    $identity = $repository->loadIdentity(CanonicalPathResolver::identity($fingerprint));

    return [
        'identity_id' => $identity->identityId,
        'profile_slug' => $identity->identitySlug(),
        'bootstrap_post_id' => $identity->bootstrapByPost,
        'bootstrap_thread_id' => $identity->bootstrapByThread,
    ];
}

function approveExistingUser(
    string $repositoryRoot,
    string $databasePath,
    string $artifactRoot,
    string $approverIdentityId,
    string $targetIdentityId
): void {
    normalizeIdentityId($approverIdentityId);
    $target = loadIdentityTarget($repositoryRoot, $targetIdentityId);

    $writer = new LocalWriteService(
        $repositoryRoot,
        $databasePath,
        $artifactRoot,
        new CanonicalRecordRepository($repositoryRoot),
    );

    $writer->approveUser([
        'approver_identity_id' => $approverIdentityId,
        'target_identity_id' => $target['identity_id'],
        'target_profile_slug' => $target['profile_slug'],
        'thread_id' => $target['bootstrap_thread_id'],
        'parent_id' => $target['bootstrap_post_id'],
    ]);
}

/**
 * @param array<int, string> $argv
 */
function requireCliArgument(array $argv, int $index, string $name): string
{
    $value = trim((string) ($argv[$index] ?? ''));
    if ($value === '') {
        throw new RuntimeException('Missing required argument: ' . $name . '.');
    }

    return $value;
}

function usageText(): string
{
    return "Usage:\n"
        . "  php scripts/inject_approval.php seed <identity_id> [seed_reason] [repository_root] [database_path]\n"
        . "  php scripts/inject_approval.php approve <approver_identity_id> <target_identity_id> [repository_root] [database_path] [artifact_root]\n";
}

function normalizeIdentityId(string $identityId): string
{
    if (preg_match('/^openpgp[:-]([a-f0-9]{40})$/', $identityId, $matches) !== 1) {
        throw new RuntimeException('Identity ID must use the retained openpgp fingerprint form.');
    }

    return $matches[1];
}

function normalizeCliIdentityId(string $identityId): string
{
    return 'openpgp:' . normalizeIdentityId(strtolower($identityId));
}
