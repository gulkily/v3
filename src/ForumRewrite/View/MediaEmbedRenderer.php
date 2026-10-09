<?php

declare(strict_types=1);

namespace ForumRewrite\View;

final class MediaEmbedRenderer
{
    private const PROVIDER_LABELS = [
        'youtube' => ['icon' => '▶', 'label' => 'YouTube'],
        'instagram' => ['icon' => '📷', 'label' => 'Instagram'],
    ];

    public function __construct(
        private readonly MediaEmbedDetector $detector = new MediaEmbedDetector(),
    ) {
    }

    public function render(string $body, bool $enabled): string
    {
        $matches = $enabled ? $this->detector->detect($body) : [];

        if ($matches === []) {
            return $this->escapeAndBreak($body);
        }

        usort($matches, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        $result = '';
        $cursor = 0;

        foreach ($matches as $match) {
            $result .= $this->escapeAndBreak(substr($body, $cursor, $match['offset'] - $cursor));
            $result .= $this->renderCard($match);
            $cursor = $match['offset'] + $match['length'];
        }

        $result .= $this->escapeAndBreak(substr($body, $cursor));

        return $result;
    }

    private function renderCard(array $match): string
    {
        $provider = self::PROVIDER_LABELS[$match['provider']] ?? ['icon' => '🔗', 'label' => $match['provider']];
        $escapedUrl = $this->escape($match['displayUrl']);

        return '<span class="media-embed-card" data-media-embed-card data-provider="' . $this->escape($match['provider']) . '">'
            . '<span class="media-embed-card__label">' . $provider['icon'] . ' ' . $this->escape($provider['label']) . '</span> '
            . '<a class="media-embed-card__link" href="' . $escapedUrl . '">' . $escapedUrl . '</a>'
            . '</span>';
    }

    private function escapeAndBreak(string $value): string
    {
        return nl2br($this->escape($value));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
