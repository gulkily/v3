<?php

declare(strict_types=1);
namespace ForumRewrite\Canonical;

use RuntimeException;

/** Effective interpretation of independently accepted signed and local receipts. */
final class AcceptedRemoval
{
    public static function all(string $root): array
    {
        $records = LabelRemoval::all($root);
        foreach (AcceptedRetraction::removals($root) as $id => $signed) {
            $records[$id] = isset($records[$id]) ? self::combine($signed, $records[$id]) : $signed;
        }
        return $records;
    }

    public static function read(string $root, string $id): ?array
    {
        $local = LabelRemoval::read($root, $id);
        $signed = AcceptedRetraction::read($root, $id);
        if ($signed !== null) {
            $signed += ['operation' => 'delete', 'labels' => [], 'source_id' => $id];
            return $local === null ? $signed : self::combine($signed, $local);
        }
        return $local;
    }

    public static function combine(array $signed, array $local): array
    {
        if ($signed['source_id'] !== $local['source_id'] || $signed['post'] !== $local['post']) {
            throw new RuntimeException('Conflicting removal structure; inspect accepted receipts before retry.');
        }
        $a = $signed['source_hash'] ?? null; $b = $local['source_hash'] ?? null;
        if ($a !== null && $b !== null && !hash_equals($a, $b)) {
            throw new RuntimeException('Conflicting removal source hashes; inspect accepted receipts before retry.');
        }
        $labels = $signed['labels'];
        foreach ($local['labels'] as $path => $hash) {
            if (isset($labels[$path]) && !hash_equals($labels[$path], $hash)) {
                throw new RuntimeException('Conflicting removal label binding: ' . $path);
            }
            $labels[$path] = $hash;
        }
        ksort($labels);
        // Preserve signed attribution and exact evidence; a repair can never undo deletion.
        return array_replace($signed, ['operation' => 'delete', 'source_hash' => $a ?? $b, 'labels' => $labels]);
    }
}
