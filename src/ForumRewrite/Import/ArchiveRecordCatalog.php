<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\LegacyPostTimestamp;
use Throwable;

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
            || preg_match('#^records/post-timestamps/[A-Za-z0-9][A-Za-z0-9._-]*\.json$#', $relativePath) === 1
            || preg_match('#^records/thread-subjects/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/thread-labels/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
            || preg_match('#^records/post-reactions/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#', $relativePath) === 1
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

    public static function identityKey(string $repositoryRoot, string $relativePath): ?string
    {
        if (self::isSignaturePath($relativePath) && !self::isRecordPath($relativePath)) {
            $suffix = str_ends_with($relativePath, '.sig') ? '.sig' : '.asc';
            $recordPath = substr($relativePath, 0, -strlen($suffix));
            $recordKey = self::identityKey($repositoryRoot, $recordPath);

            return $recordKey !== null ? 'signature:' . $recordKey . ':' . $suffix : 'path:' . $relativePath;
        }

        $repository = new CanonicalRecordRepository($repositoryRoot);

        try {
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

            if ($relativePath === 'records/instance/public.txt') {
                return 'instance:public';
            }

            if ($relativePath === 'records/instance/feature-flags.txt') {
                return 'instance:feature-flags';
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
