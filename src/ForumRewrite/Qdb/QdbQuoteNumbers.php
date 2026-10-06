<?php

declare(strict_types=1);

namespace ForumRewrite\Qdb;

use PDO;

final class QdbQuoteNumbers
{
    /**
     * @return array{displayNumber: string, permalinkHref: string}
     */
    public static function displayPermalink(string $threadId): array
    {
        $number = self::fromThreadId($threadId);

        return $number === null
            ? ['displayNumber' => $threadId, 'permalinkHref' => '/threads/' . $threadId]
            : ['displayNumber' => $number, 'permalinkHref' => '/' . $number];
    }

    public static function fromThreadId(string $threadId): ?string
    {
        return preg_match('/-qdb-(\d+)$/', $threadId, $matches) === 1 ? $matches[1] : null;
    }

    public static function mint(string $timestamp, int $number): string
    {
        if ($number < 1) {
            throw new \InvalidArgumentException('QDB quote numbers must be positive.');
        }

        return sprintf('thread-%s-qdb-%d', $timestamp, $number);
    }

    public static function nextAvailable(PDO $pdo): int
    {
        $maxNumber = $pdo->query(
            "SELECT MAX(CAST(substr(root_post_id, instr(root_post_id, '-qdb-') + 5) AS INTEGER))
             FROM threads WHERE root_post_id LIKE '%-qdb-%'"
        )->fetchColumn();

        return ((int) $maxNumber) + 1;
    }

    public static function resolve(PDO $pdo, int $number): ?string
    {
        if ($number < 1) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT root_post_id FROM threads
             WHERE CAST(substr(root_post_id, instr(root_post_id, '-qdb-') + 5) AS INTEGER) = :number
               AND root_post_id LIKE '%-qdb-%'"
        );
        $stmt->execute(['number' => $number]);
        $threadId = $stmt->fetchColumn();

        return $threadId === false ? null : (string) $threadId;
    }
}
