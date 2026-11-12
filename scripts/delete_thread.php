<?php

declare(strict_types=1);

// Run with ./v3 delete-thread; all scripts: scripts/README.md.
// Deletes a thread root and every reply in it, one delete-record removal per post.

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\Support\CommandProgress;
use ForumRewrite\Support\DeploymentSettings;
use ForumRewrite\Support\LocalRepositoryBootstrap;
use ForumRewrite\Write\SourceLabelRemoval;

$projectRoot = dirname(__DIR__);
$flags = ['--json', '--inspect', '-q', '--quiet'];
$json = in_array('--json', $argv, true);
$inspect = in_array('--inspect', $argv, true);
$quiet = in_array('-q', $argv, true) || in_array('--quiet', $argv, true);
$argv = array_values(array_filter($argv, static fn (string $argument): bool => !in_array($argument, $flags, true)));
$progress = new CommandProgress(!$quiet && !$json);
$threadId = (string) ($argv[1] ?? '');

if ($threadId === '' || in_array($threadId, ['-h', '--help'], true)) {
    fwrite(STDERR, usage());
    exit($threadId === '' ? 1 : 0);
}
if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $threadId) !== 1) {
    fwrite(STDERR, "thread ID must contain only ASCII letters, numbers, dot, underscore, colon, or hyphen.\n");
    exit(1);
}

$repositoryRoot = normalizePath($argv[2] ?? (DeploymentSettings::value('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot)));
$databasePath = normalizePath($argv[3] ?? (DeploymentSettings::value('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')));
$artifactRoot = $argv[4] ?? (DeploymentSettings::value('FORUM_PUBLIC_ARTIFACT_ROOT') ?: null);
if ($artifactRoot === null || $artifactRoot === '') {
    $defaultStatic = \ForumRewrite\PresentationPathResolver::staticHtmlRoot($projectRoot, \ForumRewrite\SiteProfileRegistry::active());
    $artifactRoot = (string) (DeploymentSettings::value('FORUM_STATIC_HTML_ROOT') ?: $defaultStatic);
}
$artifactRoot = normalizePath($artifactRoot);

try {
    $progress->step('Reading thread ' . $threadId);
    $root = LabelRemoval::source($repositoryRoot, $threadId);
    $rootPost = (new PostRecordParser())->parse(withCreatedAt($root[1]));
    if ($rootPost->threadId !== null && $rootPost->threadId !== $threadId) {
        throw new RuntimeException("{$threadId} is a reply in thread {$rootPost->threadId}; pass the thread ID, or use ./v3 delete-record for one reply.");
    }

    $progress->step('Finding replies');
    $replies = replyIds($repositoryRoot, $threadId, $databasePath);
    $targets = [];
    foreach ($replies as $id) $targets[] = $id;
    // The root goes last: replies vanish first, so no reader sees a thread of placeholders.
    if (!rootAlreadyDeleted($repositoryRoot, $threadId)) $targets[] = $threadId;

    if ($inspect) {
        $result = ['thread_id' => $threadId, 'replies' => $replies, 'root_pending' => in_array($threadId, $targets, true), 'posts_to_delete' => count($targets)];
        if ($json) fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n");
        else {
            fwrite(STDOUT, 'Thread deletion inspection: ' . $threadId . "\n");
            fwrite(STDOUT, 'posts_to_delete: ' . count($targets) . "\n");
            foreach ($targets as $id) fwrite(STDOUT, '  ' . $id . ($id === $threadId ? ' (root)' : '') . "\n");
        }
        if (!$json) $progress->finish('Inspected ' . $threadId);
        exit(0);
    }

    \ForumRewrite\Host\HighTrafficSafety::beforeWrite($repositoryRoot, $databasePath, 'records/post-retractions/maintenance.txt');
    $deleted = $targets;
    $unresolved = [];
    $images = [];
    if ($targets !== []) {
        $batch = SourceLabelRemoval::runMany($repositoryRoot, $databasePath, $targets, $artifactRoot,
            'Delete thread ' . $threadId . ' (' . count($targets) . ' post(s))', $progress->reporter());
        $unresolved = $batch['unresolved_labels'];
        $images = $batch['removed_images'];
    }

    $result = ['status' => 'ok', 'thread_id' => $threadId, 'deleted' => $deleted, 'commit' => $batch['commit'] ?? null, 'removed_images' => $images, 'unresolved_labels' => $unresolved];
    if ($json) fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n");
    else {
        fwrite(STDOUT, 'Thread deleted: ' . $threadId . ' (' . count($deleted) . " post(s))\n");
        if ($images !== []) fwrite(STDOUT, 'removed_images: ' . count($images) . "\n");
        if ($unresolved !== []) {
            fwrite(STDOUT, 'unresolved_labels: ' . json_encode($unresolved) . "\n");
            fwrite(STDOUT, "Legacy labels remain unresolved; inspect them with ./v3 delete-record <post-id> --inspect.\n");
        }
        fwrite(STDOUT, "Posts stay in Git history until ./v3 purge-history run.\n");
    }
    if (!$json) $progress->finish('Deleted thread ' . $threadId);
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    fwrite(STDERR, "Rerun the same command to finish; posts already deleted are skipped.\n");
    exit(1);
}

/**
 * Live replies in the thread, newest first. Reads the read model when it is current at
 * HEAD; otherwise git grep over the checkout, which stays fast on large repositories.
 *
 * @return list<string>
 */
function replyIds(string $repositoryRoot, string $threadId, string $databasePath): array
{
    $fromReadModel = readModelReplyIds($repositoryRoot, $threadId, $databasePath);
    if ($fromReadModel !== null) return $fromReadModel;
    $command = sprintf('git -C %s grep -l -E -e %s -- records/posts 2>/dev/null',
        escapeshellarg($repositoryRoot), escapeshellarg('^Thread-ID: ' . str_replace('.', '\\.', $threadId) . '$'));
    exec($command, $paths, $status);
    if ($status > 1) throw new RuntimeException('Cannot search records/posts for thread replies.');
    $parser = new PostRecordParser();
    $replies = [];
    foreach ($paths as $path) {
        $bytes = (string) file_get_contents($repositoryRoot . '/' . $path);
        try { $post = $parser->parse(withCreatedAt($bytes)); }
        catch (Throwable) { continue; }
        // The header line must belong to the post's own headers, not to text in its body.
        if ($post->threadId !== $threadId || $post->postId === $threadId) continue;
        $replies[$post->postId] = $post->createdAt;
    }
    uksort($replies, static fn (string $a, string $b): int => [$replies[$b], $b] <=> [$replies[$a], $a]);

    return array_keys($replies);
}

/** @return list<string>|null null when the read model is missing or behind HEAD */
function readModelReplyIds(string $repositoryRoot, string $threadId, string $databasePath): ?array
{
    if (!is_file($databasePath) || (new \ForumRewrite\ReadModel\ReadModelStaleMarker($databasePath))->exists()) return null;
    try {
        $pdo = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $metadata = $pdo->query('SELECT key, value FROM metadata')->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!\ForumRewrite\ReadModel\ReadModelMetadata::hasExpectedSchemaIdentity($metadata)
            || ($metadata['repository_head'] ?? null) !== \ForumRewrite\ReadModel\ReadModelMetadata::repositoryHead($repositoryRoot)) return null;
        $select = $pdo->prepare('SELECT post_id FROM posts WHERE thread_id = ? AND post_id != ? ORDER BY created_at DESC, post_id DESC');
        $select->execute([$threadId, $threadId]);
        $ids = [];
        foreach ($select->fetchAll(PDO::FETCH_COLUMN) as $id) {
            // Rows for already-deleted posts stay as placeholders; only live source files count.
            foreach (\ForumRewrite\Canonical\CanonicalPathResolver::postCandidates((string) $id) as $path) {
                if (is_file($repositoryRoot . '/' . $path)) { $ids[] = (string) $id; break; }
            }
        }
        return $ids;
    } catch (PDOException) {
        return null;
    }
}

function rootAlreadyDeleted(string $repositoryRoot, string $threadId): bool
{
    foreach (\ForumRewrite\Canonical\CanonicalPathResolver::postCandidates($threadId) as $path) {
        if (is_file($repositoryRoot . '/' . $path)) return false;
    }
    return (LabelRemoval::read($repositoryRoot, $threadId)['operation'] ?? null) === 'delete';
}

/** Legacy posts may lack Created-At; the order only needs a sortable placeholder. */
function withCreatedAt(string $bytes): string
{
    return preg_match('/^Created-At:/m', strstr($bytes, "\n\n", true) ?: $bytes) === 1
        ? $bytes
        : (string) preg_replace('/\n/', "\nCreated-At: 1970-01-01T00:00:00Z\n", $bytes, 1);
}

function normalizePath(string $path): string
{
    if ($path === '') return $path;
    if (!str_starts_with($path, '/')) $path = getcwd() . '/' . $path;
    $resolved = realpath($path);
    return $resolved !== false ? $resolved : rtrim($path, '/');
}

function usage(): string
{
    return <<<TEXT
Usage:
  ./v3 delete-thread <thread-id> [repository_root] [database_path] [artifact_root] [--inspect] [--json] [-q]

Deletes a thread: every reply and the root post, in one commit. Each post gets
the same removal receipt as ./v3 delete-record, with source-owned labels removed
and pages refreshed. Images the posts attached are deleted too, with their
thumbnails, unless another post still uses the same image.

Options:
  --inspect     List the posts that would be deleted; change nothing.
  --json        Structured output; no step lines.
  -q, --quiet   Hide step lines; keep errors and the final line.

If the command stops partway, run it again: posts already deleted are skipped.
Deleted posts remain in Git history until ./v3 purge-history run.

TEXT;
}
