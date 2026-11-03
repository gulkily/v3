<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/** The search match rule shared by QDB and board search: case-insensitive literal containment. */
final class LiteralTextMatch
{
    public static function matches(string $text, string $term): bool
    {
        return stripos($text, $term) !== false;
    }

    /** Registers `$name(text)` on this connection, true when text contains the term. */
    public static function register(PDO $pdo, string $name, string $term): void
    {
        $pdo->sqliteCreateFunction($name, static fn ($text): bool => self::matches((string) $text, $term), 1);
    }
}
