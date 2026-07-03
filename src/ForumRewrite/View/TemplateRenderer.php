<?php

declare(strict_types=1);

namespace ForumRewrite\View;

use ForumRewrite\Host\AssetFingerprint;
use ForumRewrite\SiteConfig;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\ThreadTitle;
use RuntimeException;

final class TemplateRenderer
{
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
    ): string {
        $content = $this->renderFile('pages/' . $pageTemplate, $pageData);

        return $this->renderLayout($title, $content, $activeSection, $scriptPaths, $routeSource);
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
    ): string {
        $assetScriptPaths = [];
        foreach ($scriptPaths as $scriptPath) {
            $assetScriptPaths[] = $this->assetPath($scriptPath);
        }

        return $this->renderFile('layout.php', [
            'title' => $title,
            'content' => $content,
            'activeSection' => $activeSection,
            'scriptPaths' => $assetScriptPaths,
            'routeSource' => $routeSource,
            'siteName' => SiteConfig::SITE_NAME,
            'appVersion' => $this->appVersion,
            'appVersionNotificationEnabled' => $this->featureFlags->isEnabled(FeatureFlagRegistry::APP_VERSION_NOTIFICATION),
            'siteCssPath' => $this->assetPath('/assets/site.css'),
            'themeToggleScriptPath' => $this->assetPath('/assets/theme_toggle.js'),
            'composeDraftClearScriptPath' => $this->assetPath('/assets/compose_draft_clear.js'),
            'versionCheckScriptPath' => $this->assetPath('/assets/version_check.js'),
            'themes' => ThemeRegistry::all(),
            'explicitThemeNames' => ThemeRegistry::explicitNames(),
            'navItems' => [
                ['href' => '/', 'label' => 'Board', 'section' => 'board'],
                ['href' => '/about/', 'label' => 'About', 'section' => 'about'],
                ['href' => '/users/', 'label' => 'Users', 'section' => 'profiles'],
                ['href' => '/tools/', 'label' => 'Tools', 'section' => 'tools'],
                ['href' => '/account/key/', 'label' => 'Account', 'section' => 'account'],
            ],
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
        ], $data);

        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $br = static fn (mixed $value): string => nl2br(htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $friendlyTimestamp = fn (?string $timestamp): string => $this->formatFriendlyTimestamp($timestamp);
        $timestamp = fn (?string $timestamp): string => $this->renderTimestampHtml($timestamp, $e);
        $author = fn (array $record): string => $this->renderAuthorHtml($record, $e);
        $contentMeta = fn (array $record, string $timeField = 'created_at', string $timeLabel = 'Posted'): string => $this->renderContentMeta($record, $timeField, $timeLabel, $e);
        $timeMeta = fn (string $label, ?string $timestamp): string => $this->renderTimeMeta($label, $timestamp, $e);
        $heat = fn (?string $timestamp): int => $this->heatLevel($timestamp);
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
    private function renderAuthorHtml(array $record, callable $escape): string
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

        if ($authorIsApproved && $authorUsernameToken !== '') {
            return '<a href="/user/' . $escape($authorUsernameToken) . '">' . $escape($authorLabel) . '</a>';
        }

        return '<a href="/profiles/' . $escape($authorProfileSlug) . '">' . $escape($authorLabel) . '</a> <span class="meta">(unapproved)</span>';
    }

    /**
     * @param callable(mixed): string $escape
     */
    private function renderContentMeta(array $record, string $timeField, string $timeLabel, callable $escape): string
    {
        $authorHtml = $this->renderAuthorHtml($record, $escape);
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
     * Buckets a timestamp's age into a 1 (coldest) to 8 (hottest) heat level.
     * Rendered as data-heat on content cards so themes can color by recency.
     */
    private function heatLevel(?string $timestamp): int
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
        foreach ($buckets as [$level, $maxAgeSeconds]) {
            if ($ageSeconds <= $maxAgeSeconds) {
                return $level;
            }
        }

        return 1;
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

    private function assetPath(string $path): string
    {
        return AssetFingerprint::fingerprintedPath(dirname($this->templateRoot) . '/public', $path);
    }
}
