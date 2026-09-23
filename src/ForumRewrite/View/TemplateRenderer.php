<?php

declare(strict_types=1);

namespace ForumRewrite\View;

use ForumRewrite\Host\AssetFingerprint;
use ForumRewrite\SiteConfig;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\ThreadTitle;
use RuntimeException;

final class TemplateRenderer
{
    private const THREAD_DENSITY_TOGGLE_PAGE_TEMPLATES = ['board.php', 'tag.php'];
    private const CRITICAL_CSS_END_MARKER = '/* critical-css-end */';
    private ?string $criticalCss = null;

    public function __construct(
        private readonly string $templateRoot,
        private readonly string $appVersion = 'unknown',
        private readonly FeatureFlagEvaluator $featureFlags = new FeatureFlagEvaluator(),
    ) {
    }

    /**
     * @param array<string, mixed> $pageData
     * @param string[] $scriptPaths
     */
    public function renderPageTemplate(
        string $pageTemplate,
        array $pageData,
        string $title,
        string $activeSection,
        array $scriptPaths = [],
        string $routeSource = 'php-fallback',
        bool $publicAuthenticationResume = false,
    ): string {
        $content = $this->renderFile('pages/' . $pageTemplate, $pageData);
        $showThreadDensityToggle = in_array($pageTemplate, self::THREAD_DENSITY_TOGGLE_PAGE_TEMPLATES, true);

        $viewerProfile = is_array($pageData['viewerProfile'] ?? null) ? $pageData['viewerProfile'] : null;

        return $this->renderLayout(
            $title,
            $content,
            $activeSection,
            $scriptPaths,
            $routeSource,
            $showThreadDensityToggle,
            $viewerProfile,
            $publicAuthenticationResume,
        );
    }

    /**
     * @param string[] $scriptPaths
     */
    public function renderLayout(
        string $title,
        string $content,
        string $activeSection,
        array $scriptPaths = [],
        string $routeSource = 'php-fallback',
        bool $showThreadDensityToggle = false,
        ?array $viewerProfile = null,
        bool $publicAuthenticationResume = false,
    ): string {
        if ($this->featureFlags->isEnabled(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY)
            && $viewerProfile !== null
            && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1
            && (($viewerProfile['_members_only_access'] ?? true) === true)
        ) {
            $scriptPaths = array_merge($scriptPaths, [
                '/assets/openpgp_loader.js',
                '/assets/browser_signing.js',
                '/assets/private_site_auth.js',
                '/assets/auth_navigation.js',
            ]);
        }

        if ($publicAuthenticationResume) {
            $scriptPaths = array_merge($scriptPaths, [
                '/assets/openpgp_loader.js',
                '/assets/browser_signing.js',
                '/assets/private_site_auth.js',
            ]);
        }

        $scriptPaths = array_values(array_unique($scriptPaths));
        $assetScriptPaths = [];
        foreach ($scriptPaths as $scriptPath) {
            $assetScriptPaths[] = $this->assetPath($scriptPath);
        }

        $themeStylesheetPaths = [];
        foreach (ThemeRegistry::stylesheetPaths() as $name => $path) {
            $themeStylesheetPaths[$name] = $this->assetPath($path);
        }
        $defaultTheme = SiteProfileRegistry::active()['defaultTheme'];
        $themeHint = $this->themeHint();
        $initialTheme = $themeHint
            ?? (ThemeRegistry::isExplicitName($defaultTheme) ? $defaultTheme : 'light');

        return $this->renderFile('layout.php', [
            'title' => $title,
            'content' => $content,
            'activeSection' => $activeSection,
            'scriptPaths' => $assetScriptPaths,
            'routeSource' => $routeSource,
            'showThreadDensityToggle' => $showThreadDensityToggle,
            'siteName' => SiteConfig::siteName(),
            'appVersion' => $this->appVersion,
            'appVersionNotificationEnabled' => $this->featureFlags->isEnabled(FeatureFlagRegistry::APP_VERSION_NOTIFICATION),
            'siteCssPath' => $this->assetPath('/assets/site.css'),
            'criticalCss' => $this->criticalCss(),
            'themeToggleScriptPath' => $this->assetPath('/assets/theme_toggle.js'),
            'threadDensityToggleScriptPath' => $this->assetPath('/assets/thread_density_toggle.js'),
            'composeDraftClearScriptPath' => $this->assetPath('/assets/compose_draft_clear.js'),
            'inviteNavigationScriptPath' => $this->assetPath('/assets/invite_navigation.js'),
            'versionCheckScriptPath' => $this->assetPath('/assets/version_check.js'),
            'themes' => ThemeRegistry::all(),
            'explicitThemeNames' => ThemeRegistry::explicitNames(),
            'defaultTheme' => $defaultTheme,
            'themeStylesheetPaths' => $themeStylesheetPaths,
            'initialThemeStylesheetPath' => $themeStylesheetPaths[$initialTheme],
            'approvedMembersOnlyEnabled' => $this->featureFlags->isEnabled(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY),
            'publicAuthenticationResume' => $publicAuthenticationResume,
            'navItems' => $this->navItems($viewerProfile),
        ]);
    }

    private function criticalCss(): string
    {
        if ($this->criticalCss !== null) {
            return $this->criticalCss;
        }

        $stylesheetPath = dirname($this->templateRoot) . '/public/assets/site.css';
        $stylesheet = file_get_contents($stylesheetPath);
        if ($stylesheet === false) {
            throw new RuntimeException('Unable to read critical stylesheet source.');
        }

        $endOffset = strpos($stylesheet, self::CRITICAL_CSS_END_MARKER);
        if ($endOffset === false) {
            throw new RuntimeException('Critical stylesheet marker is missing.');
        }

        $this->criticalCss = substr($stylesheet, 0, $endOffset);

        return $this->criticalCss;
    }

    private function themeHint(): ?string
    {
        $hint = (string) ($_COOKIE[ThemeRegistry::THEME_HINT_COOKIE] ?? '');

        return ThemeRegistry::isExplicitName($hint) ? $hint : null;
    }

    /**
     * @param array<string, mixed>|null $viewerProfile
     * @return list<array{href:string,label:string,section:string}>
     */
    private function navItems(?array $viewerProfile): array
    {
        if ($this->featureFlags->isEnabled(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY)
            && ($viewerProfile === null
                || ((int) ($viewerProfile['is_approved'] ?? 0)) !== 1
                || (($viewerProfile['_members_only_access'] ?? true) !== true))
        ) {
            $items = [
                ['href' => '/lobby/', 'label' => 'Lobby', 'section' => 'lobby'],
            ];
            $profileSlug = trim((string) ($viewerProfile['profile_slug'] ?? ''));
            if ($profileSlug !== '') {
                $items[] = [
                    'href' => '/profiles/' . rawurlencode($profileSlug),
                    'label' => 'Profile',
                    'section' => 'profiles',
                ];
            }
            $items[] = ['href' => '/account/key/', 'label' => 'Account', 'section' => 'account'];

            return $items;
        }

        $items = [
            ['href' => '/', 'label' => 'Board', 'section' => 'board'],
            ['href' => '/about/', 'label' => 'About', 'section' => 'about'],
            ['href' => '/users/', 'label' => 'Users', 'section' => 'profiles'],
            ['href' => '/tools/', 'label' => 'Tools', 'section' => 'tools'],
            ['href' => '/account/key/', 'label' => 'Account', 'section' => 'account'],
        ];

        if ($viewerProfile !== null
            && ((int) ($viewerProfile['is_approved'] ?? 0)) === 1
            && (($viewerProfile['_authenticated_identity'] ?? true) === true)) {
            $items[] = ['href' => '/invites/', 'label' => 'Invite', 'section' => 'invite', 'invite_action' => true];
        }

        return $items;
    }

    /**
     * Renders a single partial with no page/layout wrapper at all - for
     * small HTML fragments returned to client-side JS (e.g. lazy-loaded
     * content), not full pages.
     *
     * @param array<string, mixed> $data
     */
    public function renderFragment(string $partialPath, array $data): string
    {
        return $this->renderFile($partialPath, $data);
    }

    /**
     * Renders a page with no shared site chrome (no nav bar, theme menu, or
     * global status bar) - for pages that intentionally present their own
     * complete, self-contained UI.
     *
     * @param array<string, mixed> $pageData
     * @param string[] $scriptPaths
     * @param string[] $additionalCssPaths
     */
    public function renderStandalonePage(
        string $pageTemplate,
        array $pageData,
        string $title,
        string $bodyClass = '',
        array $scriptPaths = [],
        array $additionalCssPaths = [],
    ): string {
        $content = $this->renderFile('pages/' . $pageTemplate, $pageData);
        $assetScriptPaths = [];
        foreach ($scriptPaths as $scriptPath) {
            $assetScriptPaths[] = $this->assetPath($scriptPath);
        }
        $assetAdditionalCssPaths = [];
        foreach ($additionalCssPaths as $additionalCssPath) {
            $assetAdditionalCssPaths[] = $this->assetPath($additionalCssPath);
        }

        return $this->renderFile('standalone_layout.php', [
            'title' => $title,
            'content' => $content,
            'bodyClass' => $bodyClass,
            'scriptPaths' => $assetScriptPaths,
            'siteCssPath' => $this->assetPath('/assets/site.css'),
            'additionalCssPaths' => $assetAdditionalCssPaths,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $relativePath, array $data): string
    {
        $path = $this->templateRoot . '/' . ltrim($relativePath, '/');
        if (!is_file($path)) {
            throw new RuntimeException('Missing template: ' . $relativePath);
        }
        $data = array_merge([
            'unicodeAuthoredTextEnabled' => $this->featureFlags->isEnabled(FeatureFlagRegistry::UNICODE_AUTHORED_TEXT),
            'emojiAuthoredTextEnabled' => $this->featureFlags->isEnabled(FeatureFlagRegistry::EMOJI_AUTHORED_TEXT),
            'composerPrompt' => SiteProfileRegistry::active()['composerPrompt'],
        ], $data);

        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $br = static fn (mixed $value): string => nl2br(htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $friendlyTimestamp = fn (?string $timestamp): string => $this->formatFriendlyTimestamp($timestamp);
        $timestamp = fn (?string $timestamp): string => $this->renderTimestampHtml($timestamp, $e);
        $relativeTimestamp = fn (?string $timestamp): string => $this->renderRelativeTimestampHtml($timestamp, $e);
        $author = fn (array $record): string => $this->renderAuthorHtml($record, $e);
        $forteAuthor = fn (array $record): string => $this->renderAuthorHtml($record, $e, true);
        $contentMeta = fn (array $record, string $timeField = 'created_at', string $timeLabel = 'Posted'): string => $this->renderContentMeta($record, $timeField, $timeLabel, $e);
        $forteContentMeta = fn (array $record, string $timeField = 'created_at', string $timeLabel = 'Posted'): string => $this->renderContentMeta($record, $timeField, $timeLabel, $e, true);
        $timeMeta = fn (string $label, ?string $timestamp): string => $this->renderTimeMeta($label, $timestamp, $e);
        $heat = fn (?string $timestamp, int $replyCount = 0): int => $this->heatLevel($timestamp, $replyCount);
        $threadTitle = static fn (array $thread): string => ThreadTitle::displayTitle(
            (string) ($thread['subject'] ?? ''),
            (string) ($thread['body_preview'] ?? $thread['body'] ?? ''),
            (string) ($thread['root_post_id'] ?? $thread['thread_id'] ?? $thread['post_id'] ?? '')
        );
        $partial = fn (string $partialPath, array $partialData = []): string => $this->renderFile(
            $partialPath,
            array_merge($data, $partialData)
        );
        $indent = static function (string $html, int $levels = 1, string $unit = '  '): string {
            $prefix = str_repeat($unit, max(0, $levels));
            $lines = explode("\n", $html);
            $protectedTags = ['pre', 'textarea', 'script', 'style'];
            $protectedDepth = 0;

            foreach ($lines as $index => $line) {
                $trimmed = ltrim($line);
                if ($trimmed !== '') {
                    foreach ($protectedTags as $tag) {
                        if (preg_match('/^<\s*' . $tag . '\b/i', $trimmed) === 1) {
                            $protectedDepth++;
                            break;
                        }
                    }
                }

                if ($line !== '' && $protectedDepth === 0) {
                    $lines[$index] = $prefix . $line;
                }

                if ($trimmed !== '') {
                    foreach ($protectedTags as $tag) {
                        if (preg_match('/<\/\s*' . $tag . '\s*>\s*$/i', $trimmed) === 1) {
                            $protectedDepth = max(0, $protectedDepth - 1);
                            break;
                        }
                    }
                }

                if ($line === '') {
                    continue;
                }
            }

            return implode("\n", $lines);
        };

        extract($data, EXTR_SKIP);

        ob_start();
        require $path;

        return (string) ob_get_clean();
    }

    /**
     * @param callable(mixed): string $escape
     */
    private function renderAuthorHtml(array $record, callable $escape, bool $forteTarget = false): string
    {
        $authorLabel = trim((string) ($record['author_label'] ?? ''));
        if ($authorLabel === '') {
            $authorLabel = 'guest';
        }

        $authorProfileSlug = trim((string) ($record['author_profile_slug'] ?? ''));
        $authorUsernameToken = trim((string) ($record['author_username_token'] ?? ''));
        $authorIsApproved = ((int) ($record['author_is_approved'] ?? 0)) === 1;

        if ($authorProfileSlug === '') {
            return $escape($authorLabel);
        }

        $profilesBase = $forteTarget ? '/forte/profiles/' : '/profiles/';
        $userBase = $forteTarget ? '/forte/user/' : '/user/';
        $summaryAttrs = $forteTarget ? ' data-forte-author-link data-profile-slug="' . $escape($authorProfileSlug) . '"' : '';

        if ($authorIsApproved && $authorUsernameToken !== '') {
            return '<a href="' . $userBase . $escape($authorUsernameToken) . '"' . $summaryAttrs . '>' . $escape($authorLabel) . '</a>';
        }

        return '<a href="' . $profilesBase . $escape($authorProfileSlug) . '"' . $summaryAttrs . '>' . $escape($authorLabel) . '</a> <span class="meta">(unapproved)</span>';
    }

    /**
     * @param callable(mixed): string $escape
     */
    private function renderContentMeta(array $record, string $timeField, string $timeLabel, callable $escape, bool $forteTarget = false): string
    {
        $authorHtml = $this->renderAuthorHtml($record, $escape, $forteTarget);
        $timestampHtml = $this->renderTimestampHtml((string) ($record[$timeField] ?? ''), $escape);
        $prefix = trim($timeLabel);

        if ($timestampHtml !== '') {
            return ($prefix !== '' ? $prefix . ' ' : '') . 'by ' . $authorHtml . ' on ' . $timestampHtml;
        }

        return ($prefix !== '' ? $prefix . ' ' : '') . 'by ' . $authorHtml;
    }

    /**
     * @param callable(mixed): string $escape
     */
    private function renderTimeMeta(string $label, ?string $timestamp, callable $escape): string
    {
        $timestampHtml = $this->renderTimestampHtml($timestamp, $escape);

        return $timestampHtml !== '' ? $label . ' ' . $timestampHtml : $label;
    }

    /**
     * Buckets a timestamp's age into a 1 (coldest) to 8 (hottest) heat level,
     * then lets reply volume push the level up to two bands hotter (capped).
     * Rendered as data-heat on content cards so themes can color by activity.
     */
    private function heatLevel(?string $timestamp, int $replyCount = 0): int
    {
        $value = trim((string) $timestamp);
        if ($value === '') {
            return 1;
        }

        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Exception) {
            return 1;
        }

        $ageSeconds = max(0, time() - $date->getTimestamp());
        $buckets = [
            [8, 3600],           // within the hour
            [7, 6 * 3600],       // within 6 hours
            [6, 24 * 3600],      // within a day
            [5, 3 * 86400],      // within 3 days
            [4, 7 * 86400],      // within a week
            [3, 30 * 86400],     // within a month
            [2, 90 * 86400],     // within a quarter
        ];
        $level = 1;
        foreach ($buckets as [$bucketLevel, $maxAgeSeconds]) {
            if ($ageSeconds <= $maxAgeSeconds) {
                $level = $bucketLevel;
                break;
            }
        }

        if ($replyCount >= 10) {
            $level += 2;
        } elseif ($replyCount >= 3) {
            $level += 1;
        }

        return min(8, $level);
    }

    private function formatFriendlyTimestamp(?string $timestamp): string
    {
        $value = trim((string) $timestamp);
        if ($value === '') {
            return '';
        }

        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Exception) {
            return $value;
        }

        $utc = new \DateTimeZone('UTC');
        $date = $date->setTimezone($utc);

        return $date->format('M j, Y \a\t H:i \U\T\C');
    }

    /**
     * @param callable(mixed): string $escape
     */
    private function renderTimestampHtml(?string $timestamp, callable $escape): string
    {
        $value = trim((string) $timestamp);
        if ($value === '') {
            return '';
        }

        return '<time datetime="' . $escape($value) . '">' . $escape($this->formatFriendlyTimestamp($value)) . '</time>';
    }

    /**
     * A short, human-scale rendering of a timestamp ("3 hours ago", "5 days
     * ago"), falling back to a bare date ("Sep 20, 2026") past a week old
     * since "19 days ago" reads worse than the date itself. Distinct from
     * `formatFriendlyTimestamp()` above (misleadingly named - it's actually
     * the full "M j, Y at H:i UTC" form `renderTimestampHtml()`/`$timestamp`
     * already use everywhere) so this stays additive and opt-in rather than
     * changing what every existing `$timestamp` call site renders.
     */
    private function formatRelativeTimestamp(?string $timestamp): string
    {
        $value = trim((string) $timestamp);
        if ($value === '') {
            return '';
        }

        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Exception) {
            return $value;
        }

        $ageSeconds = max(0, time() - $date->getTimestamp());

        if ($ageSeconds < 60) {
            return 'just now';
        }
        if ($ageSeconds < 3600) {
            $minutes = intdiv($ageSeconds, 60);
            return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
        }
        if ($ageSeconds < 86400) {
            $hours = intdiv($ageSeconds, 3600);
            return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
        }
        if ($ageSeconds < 7 * 86400) {
            $days = intdiv($ageSeconds, 86400);
            return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
        }

        return $date->setTimezone(new \DateTimeZone('UTC'))->format('M j, Y');
    }

    /**
     * Reusable "friendly date" rendering: the short relative text is what's
     * visible, the precise `formatFriendlyTimestamp()` form lives in the
     * native `title` attribute as a hover tooltip - no JS needed for that
     * part, browsers do it for free on any element with `title`.
     *
     * @param callable(mixed): string $escape
     */
    private function renderRelativeTimestampHtml(?string $timestamp, callable $escape): string
    {
        $value = trim((string) $timestamp);
        if ($value === '') {
            return '';
        }

        return '<time datetime="' . $escape($value) . '" title="' . $escape($this->formatFriendlyTimestamp($value)) . '">'
            . $escape($this->formatRelativeTimestamp($value)) . '</time>';
    }

    private function assetPath(string $path): string
    {
        return AssetFingerprint::fingerprintedPath(dirname($this->templateRoot) . '/public', $path);
    }
}
