<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\LocalRepositoryBootstrap;
use ForumRewrite\Write\LocalWriteService;

$projectRoot = dirname(__DIR__);
$defaultRepositoryRoot = LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot);
$command = $argv[1] ?? '';

if (!in_array($command, ['seed', 'approve'], true)) {
    fwrite(
        STDERR,
        "Usage:\n"
        . "  php scripts/inject_approval.php seed <identity_id> [seed_reason] [repository_root] [database_path]\n"
        . "  php scripts/inject_approval.php approve <approver_identity_id> <target_identity_id> [repository_root] [database_path] [artifact_root]\n"
    );
    exit(1);
}

if ($command === 'seed') {
    $identityId = trim((string) ($argv[2] ?? ''));
    $seedReason = trim((string) ($argv[3] ?? 'initial approved user'));
    $repositoryRoot = $argv[4] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: $defaultRepositoryRoot);
    $databasePath = $argv[5] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3'));

    seedApprovedIdentity($repositoryRoot, $databasePath, $identityId, $seedReason);
    fwrite(STDOUT, "Seeded approval for {$identityId}\n");
    exit(0);
}

$approverIdentityId = trim((string) ($argv[2] ?? ''));
$targetIdentityId = trim((string) ($argv[3] ?? ''));
$repositoryRoot = $argv[4] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: $defaultRepositoryRoot);
$databasePath = $argv[5] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3'));
$artifactRoot = $argv[6] ?? (getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: ($projectRoot . '/public'));

approveExistingUser($repositoryRoot, $databasePath, $artifactRoot, $approverIdentityId, $targetIdentityId);
fwrite(STDOUT, "Approved {$targetIdentityId} using {$approverIdentityId}\n");

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

function seedApprovedIdentity(string $repositoryRoot, string $databasePath, string $identityId, string $seedReason): void
{
    $fingerprint = normalizeIdentityId($identityId);
    $relativePath = CanonicalPathResolver::approvalSeed($fingerprint);
    $path = $repositoryRoot . '/' . $relativePath;

    if (is_file($path)) {
        throw new RuntimeException('Approval seed already exists for ' . $identityId . '.');
    }

    $contents = "Approved-Identity-ID: {$identityId}\n"
        . "Seed-Reason: {$seedReason}\n"
        . "\nSeed this identity as approved.\n";

    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('Unable to write approval seed file.');
    }

    if (is_dir($repositoryRoot . '/.git')) {
        runGitCommand($repositoryRoot, ['add', '--', $relativePath], 'Unable to stage approval seed');
        runGitCommand(
            $repositoryRoot,
            ['-c', 'user.name=Forum Rewrite', '-c', 'user.email=forum-rewrite@example.invalid', 'commit', '-m', 'Seed approval ' . $identityId],
            'Unable to commit approval seed'
        );
    }

    rebuildReadModel($repositoryRoot, $databasePath);
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

function rebuildReadModel(string $repositoryRoot, string $databasePath): void
{
    (new ExecutionLock(dirname($databasePath) . '/forum-rewrite.lock'))->withExclusiveLock(
        static function () use ($repositoryRoot, $databasePath): void {
            $builder = new ReadModelBuilder(
                $repositoryRoot,
                $databasePath,
                new CanonicalRecordRepository($repositoryRoot),
                'manual_approval_injection',
            );
            $builder->rebuild();
        }
    );
}

/**
 * @param list<string> $args
 */
function runGitCommand(string $repositoryRoot, array $args, string $failureMessage): string
{
    $command = 'git';
    foreach ($args as $arg) {
        $command .= ' ' . escapeshellarg($arg);
    }

    $output = [];
    $exitCode = 0;
    exec('cd ' . escapeshellarg($repositoryRoot) . ' && ' . $command . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException($failureMessage . ': ' . implode("\n", $output));
    }

    return implode("\n", $output);
}

function normalizeIdentityId(string $identityId): string
{
    if (preg_match('/^openpgp:([a-f0-9]{40})$/', $identityId, $matches) !== 1) {
        throw new RuntimeException('Identity ID must use the retained openpgp lowercase fingerprint form.');
    }

    return $matches[1];
}
