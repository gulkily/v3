<?php

declare(strict_types=1);

namespace ForumRewrite\View;

use ForumRewrite\Security\RetractionAuthority;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\ThemeDirective;
use PDO;

/**
 * A thread's own page theme, derived when read from its posts: the latest `theme:`
 * line from the thread's verified author or a current operator decides, and
 * `theme: none` clears it. A line counts only when its post is unretracted and its
 * attribution is verified, and only when it names a theme this site permits; an
 * unknown name is skipped, so the setting before it stays. Nothing is stored, so a
 * rebuild cannot disagree with the posts.
 */
final class ThreadTheme
{
    public static function forThread(PDO $pdo, string $repositoryRoot, string $threadId): ?string
    {
        if ($threadId === '') {
            return null;
        }
        $root = $pdo->prepare('SELECT author_identity_id, attribution_verified FROM posts WHERE post_id = :post_id');
        $root->execute(['post_id' => $threadId]);
        $rootRow = $root->fetch(PDO::FETCH_ASSOC);
        $root->closeCursor();
        $rootAuthor = is_array($rootRow) && (int) ($rootRow['attribution_verified'] ?? 0) === 1
            ? (string) ($rootRow['author_identity_id'] ?? '')
            : '';

        $stmt = $pdo->prepare(
            "SELECT posts.body, posts.author_identity_id
             FROM posts
             WHERE posts.thread_id = :thread_id
               AND posts.retracted_at IS NULL
               AND posts.attribution_verified = 1
               AND posts.body LIKE '%theme:%'
             ORDER BY posts.created_at ASC, posts.sequence_number ASC"
        );
        $stmt->execute(['thread_id' => $threadId]);
        $permitted = self::permittedNames();
        $theme = null;
        $operators = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $value = ThemeDirective::extract((string) $row['body']);
            if ($value === null || ($value !== ThemeDirective::NONE && !in_array($value, $permitted, true))) {
                continue;
            }
            $author = (string) ($row['author_identity_id'] ?? '');
            if ($author === '') {
                continue;
            }
            if ($author !== $rootAuthor) {
                $operators[$author] ??= RetractionAuthority::currentOperator($repositoryRoot, $author);
                if (!$operators[$author]) {
                    continue;
                }
            }
            $theme = $value === ThemeDirective::NONE ? null : $value;
        }

        return $theme;
    }

    /**
     * @return list<string> explicit themes this site permits
     */
    public static function permittedNames(): array
    {
        return array_values(array_filter(
            array_column(ThemeRegistry::permitted(SiteProfileRegistry::active()['permittedThemes']), 'name'),
            static fn (string $name): bool => ThemeRegistry::isExplicitName($name),
        ));
    }
}
