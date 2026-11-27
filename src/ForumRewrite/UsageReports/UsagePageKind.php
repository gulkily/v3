<?php

declare(strict_types=1);

namespace ForumRewrite\UsageReports;

/**
 * Turns a request path into a page kind for usage logs: route words stay, and
 * every other segment (thread and post IDs, usernames, tags, dates, docs paths)
 * becomes `*`. The query string is dropped. `/threads/abc123/` → `/threads/*`.
 *
 * The browser recorder (public/assets/usage_log.js) runs the same rule with
 * WORDS published by layout.php, so link destinations and page views agree.
 */
final class UsagePageKind
{
    /** Fixed path words from the router and Tools routes. Anything else is redacted. */
    public const WORDS = [
        'about', 'account', 'key', 'activity', 'agent', 'approvals', 'root', 'approve',
        'backup', 'backlog', 'start', 'calendar', 'compose', 'reply', 'thread', 'docs', 'forte', 'users', 'user',
        'profiles', 'replies', 'gallery', 'guestbook', 'support', 'hints', 'inbox', 'instance', 'invites',
        'latest', 'lobby', 'messages', 'conversation', 'sent', 'offline', 'reader', 'pages',
        'posts', 'random', 'recent', 'records', 'releases', 'search', 'source', 'current', 'blob',
        'commits', 'staged', 'tags', 'themes', 'threads', 'top', 'unread', 'pending', 'watch',
        'tools', 'bookmarklets', 'codebase', 'deploy', 'fastmod', 'feature-flags', 'changes',
        'llm-exchanges', 'operations', 'outbox', 'preferences', 'sqlite', 'user-access', 'accounts',
        'visitor-statistics', 'usage-log', 'usage-reports', 'upload', 'history',
    ];

    public static function fromPath(string $path): string
    {
        $path = (string) (parse_url($path, PHP_URL_PATH) ?? '');
        $words = array_flip(self::WORDS);
        $kept = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '') {
                continue;
            }
            $kept[] = isset($words[$segment]) ? $segment : '*';
        }

        return '/' . implode('/', $kept);
    }
}
