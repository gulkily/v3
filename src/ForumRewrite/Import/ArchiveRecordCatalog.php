<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\LegacyPostTimestamp;

final class ArchiveRecordCatalog
{
    public static function isRecordPath(string $relativePath): bool
    {
        if (!str_starts_with($relativePath, 'records/')) {
            return false;
        }

        return $relativePath === 'records/instance/public.txt'
            || $relativePath === 'records/instance/feature-flags.txt'
            || preg_match('#^records/posts/(?:\d{4}/\d{2}/\d{2}/)?[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/approval-acceptances/[A-Za-z0-9][A-Za-z0-9._-]*\.(?:txt|json)$#', $relativePath) === 1
            || preg_match('#^records/post-removals/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/post-timestamps/[A-Za-z0-9][A-Za-z0-9._-]*\.json$#', $relativePath) === 1
            || preg_match('#^records/thread-subjects/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/thread-labels/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/post-reactions/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/post-retractions/post-retraction-[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/post-retraction-acceptances/post-retraction-[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/identity/identity-openpgp-[A-Fa-f0-9]{40}\.txt$#', $relativePath) === 1
            || preg_match('#^records/approval-seeds/openpgp-[A-Fa-f0-9]{40}\.txt$#', $relativePath) === 1
            || preg_match('#^records/public-keys/openpgp-[A-Fa-f0-9]{40}\.asc$#', $relativePath) === 1;
    }

    public static function isSignaturePath(string $relativePath): bool
    {
        foreach (['.asc', '.sig'] as $suffix) {
            if (str_ends_with($relativePath, $suffix)) {
                return self::isRecordPath(substr($relativePath, 0, -strlen($suffix)));
            }
        }

        return false;
    }

    /** Identity claimed by a supported filename, including an unreadable/rejected record. */
    public static function pathIdentityKey(string $path): ?string
    {
        if (self::isSignaturePath($path) && !self::isRecordPath($path)) {
            $parent = self::pathIdentityKey(substr($path, 0, -4));
            return $parent === null ? null : 'signature:' . $parent . ':' . substr($path, -4);
        }
        if (!self::isRecordPath($path)) { return null; }
        $family = explode('/', $path)[1];
        $name = pathinfo($path, PATHINFO_FILENAME);
        return match ($family) {
            'posts' => 'post:' . $name,
            'post-removals' => 'post-removal:' . $name,
            'post-retraction-acceptances' => 'post-retraction-acceptance:' . $name,
            'approval-acceptances' => 'approval-acceptance:' . $name,
            'post-timestamps' => 'post-timestamp:' . $name,
            'identity' => 'identity:openpgp:' . strtolower(substr($name, strlen('identity-openpgp-'))),
            'public-keys' => 'public-key:openpgp:' . strtolower(substr($name, strlen('openpgp-'))),
            'approval-seeds' => 'approval-seed:openpgp:' . strtolower(substr($name, strlen('openpgp-'))),
            'thread-subjects' => 'thread-subject:' . $name,
            'thread-labels' => 'thread-label:' . $name,
            'post-reactions' => 'post-reaction:' . $name,
            'post-retractions' => 'post-retraction:' . $name,
            'instance' => 'instance:' . $name,
        };
    }

    public static function identityKey(string $repositoryRoot, string $relativePath): ?string
    {
        if (!is_file($repositoryRoot . '/' . $relativePath) || is_link($repositoryRoot . '/' . $relativePath)) {
            return null;
        }
        if (self::isSignaturePath($relativePath) && !self::isRecordPath($relativePath)) {
            $suffix = str_ends_with($relativePath, '.sig') ? '.sig' : '.asc';
            $recordPath = substr($relativePath, 0, -strlen($suffix));
            $recordKey = self::identityKey($repositoryRoot, $recordPath);

            return $recordKey !== null ? 'signature:' . $recordKey . ':' . $suffix : 'path:' . $relativePath;
        }

        $repository = new CanonicalRecordRepository($repositoryRoot);

        try {
            if (str_starts_with($relativePath, 'records/post-removals/')) {
                $id = basename($relativePath, '.txt');
                return \ForumRewrite\Canonical\LabelRemoval::read($repositoryRoot, $id) === null ? null : 'post-removal:' . $id;
            }
            if (str_starts_with($relativePath, 'records/approval-acceptances/')) {
                $id = pathinfo($relativePath, PATHINFO_FILENAME);
                $post = $repository->loadPost(\ForumRewrite\Canonical\CanonicalPathResolver::post($id));
                $postPath = is_file($repositoryRoot . '/records/posts/' . $id . '.txt')
                    ? 'records/posts/' . $id . '.txt'
                    : \ForumRewrite\Canonical\CanonicalPathResolver::datedPost($id, $post->createdAt);
                $receipt = \ForumRewrite\Security\ApprovalAcceptance::read($repositoryRoot, $postPath);
                return $receipt === null ? null : 'approval-acceptance:' . $receipt['post_id'];
            }
            if (str_starts_with($relativePath, 'records/post-timestamps/')) {
                return 'post-timestamp:' . LegacyPostTimestamp::read($repositoryRoot . '/' . $relativePath)['post_id'];
            }
            if (str_starts_with($relativePath, 'records/posts/')) {
                return 'post:' . $repository->loadPost($relativePath)->postId;
            }

            if (str_starts_with($relativePath, 'records/identity/')) {
                return 'identity:' . strtolower($repository->loadIdentity($relativePath)->identityId);
            }

            if (str_starts_with($relativePath, 'records/public-keys/')) {
                $fileName = basename($relativePath);
                return preg_match('/^openpgp-([A-Fa-f0-9]{40})\.asc$/', $fileName, $matches) === 1
                    ? 'public-key:openpgp:' . strtolower($matches[1])
                    : null;
            }

            if (str_starts_with($relativePath, 'records/approval-seeds/')) {
                return 'approval-seed:' . strtolower($repository->loadApprovalSeed($relativePath)->approvedIdentityId);
            }

            if (str_starts_with($relativePath, 'records/thread-subjects/')) {
                return 'thread-subject:' . $repository->loadThreadSubject($relativePath)->recordId;
            }

            if (str_starts_with($relativePath, 'records/thread-labels/')) {
                return 'thread-label:' . $repository->loadThreadLabel($relativePath)->recordId;
            }

            if (str_starts_with($relativePath, 'records/post-reactions/')) {
                return 'post-reaction:' . $repository->loadPostReaction($relativePath)->recordId;
            }

            if (str_starts_with($relativePath, 'records/post-retraction-acceptances/')) {
                $id = substr(basename($relativePath, '.txt'), strlen('post-retraction-'));
                return \ForumRewrite\Canonical\AcceptedRetraction::read($repositoryRoot, $id) === null ? null : 'post-retraction-acceptance:post-retraction-' . $id;
            }
            if (str_starts_with($relativePath, 'records/post-retractions/')) {
                return 'post-retraction:' . $repository->loadPostRetraction($relativePath)->recordId;
            }

            if ($relativePath === 'records/instance/public.txt') {
                return 'instance:public';
            }

            if ($relativePath === 'records/instance/feature-flags.txt') {
                return 'instance:feature-flags';
            }
        } catch (\ForumRewrite\Support\OperationLimitException|\ForumRewrite\Canonical\RecordReadException|\ForumRewrite\Canonical\RecordSizeException $error) { throw $error; } catch (\RuntimeException|\JsonException) {
            return null;
        }

        return null;
    }
}
