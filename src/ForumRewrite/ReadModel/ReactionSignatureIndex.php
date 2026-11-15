<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\GenericTextRecordParser;
use ForumRewrite\Security\ReactionSignature;
use PDO;

final class ReactionSignatureIndex
{
    public static function refresh(PDO $pdo, string $root, ?string $family = null): void
    {
        foreach ($family === null ? ['thread-labels', 'post-reactions'] : [$family] as $name) {
            $prefix = 'records/' . $name . '/';
            $delete = $pdo->prepare('DELETE FROM reaction_signatures WHERE source_path LIKE ?');
            $delete->execute([$prefix . '%']);
            $insert = $pdo->prepare('INSERT INTO reaction_signatures (source_path, author_identity_id, signature_status) VALUES (?, ?, ?)');
            foreach (glob($root . '/' . $prefix . '*.txt') ?: [] as $file) {
                $insert->execute(self::row($root, $prefix . basename($file)));
            }
        }
    }

    /**
     * Refreshes only the given record paths, for a write that added them. A path whose
     * file is gone loses its row, as a full refresh would drop it.
     *
     * @param list<string> $paths repository-relative record paths
     */
    public static function refreshPaths(PDO $pdo, string $root, array $paths): void
    {
        $delete = $pdo->prepare('DELETE FROM reaction_signatures WHERE source_path = ?');
        $insert = $pdo->prepare('INSERT INTO reaction_signatures (source_path, author_identity_id, signature_status) VALUES (?, ?, ?)');
        foreach (array_unique($paths) as $path) {
            $delete->execute([$path]);
            if (is_file($root . '/' . $path)) {
                $insert->execute(self::row($root, $path));
            }
        }
    }

    /** @return array{string,?string,string} */
    private static function row(string $root, string $path): array
    {
        $contents = \ForumRewrite\Canonical\RecordLimits::read($root . '/' . $path);
        $author = null;
        try {
            $author = (new GenericTextRecordParser())->parse($contents)->headers['Author-Identity-ID'] ?? null;
            $status = ReactionSignature::status($root, $path, $contents, $author);
        } catch (\ForumRewrite\Support\OperationLimitException $error) { throw $error; } catch (\RuntimeException) {
            $status = 'invalid';
        }

        return [$path, $author, $status];
    }
}
