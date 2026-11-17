<?php

declare(strict_types=1);

namespace ForumRewrite\UsageReports;

use ForumRewrite\Tools\ToolsPageSupport;

/**
 * Readable names for usage logs: page kinds ("/threads/*" → "Thread") and
 * control names ("tab:recent" → "Recent", "tool:flags" → "Feature Flags").
 * Anything not listed falls back to its raw form, so a new route shows up raw
 * rather than blank. Tool pages and tool tones come from the Tools registry.
 *
 * The Usage Log page publishes forBrowser() so its script uses the same words.
 */
final class UsageNames
{
    /** Page kinds outside /tools/. */
    public const PAGES = [
        '/' => 'Board',
        '/about' => 'About',
        '/account/key' => 'Account key',
        '/activity' => 'Activity',
        '/agent' => 'Agent Prompts',
        '/approvals/root/*' => 'Root approval',
        '/calendar' => 'Calendar',
        '/calendar/*' => 'Calendar day',
        '/calendar/*/*' => 'Calendar day',
        '/compose/reply' => 'New reply',
        '/compose/thread' => 'New thread',
        '/gallery' => 'Gallery',
        '/guestbook' => 'Guestbook',
        '/hints' => 'Hints',
        '/inbox' => 'Unread',
        '/unread' => 'Unread',
        '/invites' => 'Invites',
        '/latest/*' => 'Latest',
        '/lobby' => 'Lobby',
        '/messages' => 'Messages',
        '/messages/conversation/*' => 'Conversation',
        '/offline' => 'Offline Reading',
        '/posts/*' => 'Post',
        '/profiles/*' => 'Profile',
        '/profiles/*/approve' => 'Approve key',
        '/random' => 'Random',
        '/recent' => 'Recent',
        '/records/*' => 'Record',
        '/releases' => 'Releases',
        '/search' => 'Search',
        '/tags' => 'Tags',
        '/tags/*' => 'Tag',
        '/themes' => 'Themes',
        '/threads' => 'Threads',
        '/threads/*' => 'Thread',
        '/threads/*/forte' => 'Thread in Forte',
        '/top/*' => 'Top',
        '/user/*' => 'Profile',
        '/users' => 'Users',
        '/watch' => 'Watch',
        '/tools/feature-flags/changes/*' => 'Feature flag change',
        '/tools/llm-exchanges/*' => 'LLM exchange',
        '/tools/user-access/accounts/*' => 'User Access account',
        '/tools/user-access/profiles/*' => 'User Access profile',
    ];

    /** Page kinds shown raw on purpose: paths whose segments are content, not route words. */
    public const RAW_BY_DESIGN = ['/docs', '/forte', '/source'];

    /** Navbar sections (data-tab). */
    public const TABS = [
        'board' => 'Board',
        'recent' => 'Recent',
        'profiles' => 'Users',
        'search' => 'Search',
        'about' => 'About',
        'tools' => 'Tools',
        'account' => 'You menu',
        'compose' => 'New post',
        'calendar' => 'Calendar',
        'gallery' => 'Gallery',
        'agent' => 'Agent Prompts',
        'inbox' => 'Unread',
        'messages' => 'Messages',
        'invite' => 'Invite',
        'lobby' => 'Lobby',
    ];

    /** Readable name for a page kind, or the kind itself when there is none. */
    public static function page(string $kind): string
    {
        return self::pages()[$kind] ?? $kind;
    }

    /** Readable name for a recorded control name; free labels pass through. */
    public static function control(string $name): string
    {
        if (str_starts_with($name, 'tab:')) {
            $key = substr($name, 4);

            return self::TABS[$key] ?? self::humanize($key);
        }
        if (str_starts_with($name, 'tool:')) {
            $tone = substr($name, 5);

            return self::toolTones()[$tone] ?? self::humanize($tone);
        }
        if (str_starts_with($name, 'action:')) {
            return self::humanize(substr($name, 7));
        }

        return $name;
    }

    /** True when a page kind has a name or is raw on purpose. */
    public static function covers(string $kind): bool
    {
        if (isset(self::pages()[$kind])) {
            return true;
        }
        foreach (self::RAW_BY_DESIGN as $prefix) {
            if ($kind === $prefix || str_starts_with($kind, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /** @return array{pages: array<string, string>, tabs: array<string, string>, tools: array<string, string>} */
    public static function forBrowser(): array
    {
        return ['pages' => self::pages(), 'tabs' => self::TABS, 'tools' => self::toolTones()];
    }

    /** @return array<string, string> */
    private static function pages(): array
    {
        static $pages = null;
        if ($pages === null) {
            $pages = self::PAGES;
            foreach (ToolsPageSupport::registry() as $tool) {
                $pages[UsagePageKind::fromPath((string) $tool['href'])] ??= (string) $tool['label'];
            }
            $pages['/tools'] = 'Tools';
        }

        return $pages;
    }

    /** @return array<string, string> */
    private static function toolTones(): array
    {
        static $tones = null;
        if ($tones === null) {
            $tones = [];
            foreach (ToolsPageSupport::registry() as $tool) {
                $tones[(string) $tool['tone']] ??= (string) $tool['label'];
            }
        }

        return $tones;
    }

    private static function humanize(string $key): string
    {
        $words = trim(str_replace(['-', '_'], ' ', $key));

        return $words === '' ? $key : ucfirst($words);
    }
}
