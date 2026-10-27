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
                $path = $prefix . basename($file);
                $contents = \ForumRewrite\Canonical\RecordLimits::read($file);
                $author = null;
                try {
                    $author = (new GenericTextRecordParser())->parse($contents)->headers['Author-Identity-ID'] ?? null;
                    $status = ReactionSignature::status($root, $path, $contents, $author);
                } catch (\ForumRewrite\Support\OperationLimitException $error) { throw $error; } catch (\RuntimeException) {
                    $status = 'invalid';
                }
                $insert->execute([$path, $author, $status]);
            }
        }
    }
}
