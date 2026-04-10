<?php

declare(strict_types=1);

namespace ForumRewrite\View;

use ForumRewrite\SiteConfig;
use RuntimeException;

final class TemplateRenderer
{
    public function __construct(
        private readonly string $templateRoot,
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
        return $this->renderFile('layout.php', [
            'title' => $title,
            'content' => $content,
            'activeSection' => $activeSection,
            'scriptPaths' => $scriptPaths,
            'routeSource' => $routeSource,
            'siteName' => SiteConfig::SITE_NAME,
            'navItems' => [
                ['href' => '/', 'label' => 'Board', 'section' => 'board'],
                ['href' => '/activity/', 'label' => 'Activity', 'section' => 'activity'],
                ['href' => '/users/', 'label' => 'Users', 'section' => 'profiles'],
                ['href' => '/compose/thread', 'label' => 'Compose', 'section' => 'compose'],
                ['href' => '/account/key/', 'label' => 'Account', 'section' => 'account'],
                ['href' => '/instance/', 'label' => 'Instance', 'section' => 'instance'],
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

        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $br = static fn (mixed $value): string => nl2br(htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
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
}
