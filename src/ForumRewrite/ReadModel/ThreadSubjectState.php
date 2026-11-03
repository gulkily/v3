<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\Canonical\ThreadSubjectRecord;
use PDO;

/**
 * Effective thread title from the root post's own Subject and its thread-subject records.
 * Shared by the full rebuild and the incremental updater so both derive the same state.
 *
 * - Authored records (Author-Identity-ID present) whose source reply is still active
 *   override everything; the latest one wins and names the title's author.
 * - System records (no author) fill only an empty root subject; the earliest wins.
 * - Otherwise the root's own subject stands, with no title author.
 */
final class ThreadSubjectState
{
    /** @param list<ThreadSubjectRecord> $records @return list<ThreadSubjectRecord> */
    public static function sort(array $records): array
    {
        usort($records, static function (ThreadSubjectRecord $left, ThreadSubjectRecord $right): int {
            if ($left->createdAt !== $right->createdAt) {
                return $left->createdAt <=> $right->createdAt;
            }

            return $left->recordId <=> $right->recordId;
        });

        return $records;
    }

    /**
     * @param list<ThreadSubjectRecord> $sortedRecords records for one thread, oldest first
     * @param callable(ThreadSubjectRecord): bool $sourceActive
     * @return array{subject: ?string, author: ?string}
     */
    public static function resolve(?string $rootSubject, array $sortedRecords, callable $sourceActive): array
    {
        $authored = null;
        $system = null;
        foreach ($sortedRecords as $record) {
            if ($record->authorIdentityId === null) {
                $system ??= $record;
                continue;
            }
            if ($sourceActive($record)) {
                $authored = $record;
            }
        }
        if ($authored !== null) {
            return ['subject' => $authored->subject, 'author' => $authored->authorIdentityId];
        }
        if (($rootSubject === null || trim($rootSubject) === '') && $system !== null) {
            return ['subject' => $system->subject, 'author' => null];
        }

        return ['subject' => $rootSubject, 'author' => null];
    }

    /**
     * An authored record stays active while its source reply exists unchanged, is still
     * attributed to the same author, belongs to the same thread and is not retracted or
     * removed. A record without provenance is an independent action and stays active.
     */
    public static function sourceActive(string $repositoryRoot, PDO $pdo, ThreadSubjectRecord $record): bool
    {
        if ($record->sourcePostId === null) {
            return true;
        }
        $stmt = $pdo->prepare('SELECT thread_id, retracted_at FROM posts WHERE post_id = :post_id');
        $stmt->execute(['post_id' => $record->sourcePostId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (!is_array($row) || $row['retracted_at'] !== null || (string) $row['thread_id'] !== $record->threadId) {
            return false;
        }
        foreach (CanonicalPathResolver::postCandidates($record->sourcePostId) as $relative) {
            $path = $repositoryRoot . '/' . $relative;
            if (!is_file($path)) {
                continue;
            }
            $bytes = (string) file_get_contents($path);
            if (!hash_equals($record->sourcePostSha256 ?? '', hash('sha256', $bytes))) {
                return false;
            }
            try {
                $post = (new PostRecordParser())->parse($bytes);
            } catch (CanonicalRecordParseException) {
                return false;
            }

            return $post->postId === $record->sourcePostId && $post->authorIdentityId === $record->authorIdentityId;
        }

        return false;
    }
}
