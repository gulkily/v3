<?php

declare(strict_types=1);

namespace ForumRewrite\View;

final class MediaEmbedRenderer
{
    private const PROVIDER_LABELS = [
        'youtube' => ['icon' => '▶', 'label' => 'YouTube'],
        'instagram' => ['icon' => '📷', 'label' => 'Instagram'],
    ];

    private const WARM_PREVIEW_ENDPOINT = '/internal/media-embeds/warm-preview';

    public function __construct(
        private readonly MediaEmbedDetector $detector = new MediaEmbedDetector(),
        private readonly ?MediaEmbedPreviewCacheStore $previewCacheStore = null,
    ) {
    }

    public function render(string $body, bool $enabled, bool $inlinePlayerEnabled = false): string
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
            $result .= $this->renderCard($match, $inlinePlayerEnabled);
            $cursor = $match['offset'] + $match['length'];
        }

        $result .= $this->escapeAndBreak(substr($body, $cursor));

        return $result;
    }

    private function renderCard(array $match, bool $inlinePlayerEnabled): string
    {
        if ($inlinePlayerEnabled && $match['provider'] === 'youtube') {
            return $this->renderYoutubeExpando($match);
        }

        if ($inlinePlayerEnabled && $match['provider'] === 'instagram') {
            return $this->renderInstagramCard($match);
        }

        return $this->renderPlainCard($match);
    }

    private function renderPlainCard(array $match): string
    {
        $provider = self::PROVIDER_LABELS[$match['provider']] ?? ['icon' => '🔗', 'label' => $match['provider']];
        $escapedUrl = $this->escape($match['displayUrl']);

        return '<span class="media-embed-card" data-media-embed-card data-provider="' . $this->escape($match['provider']) . '">'
            . '<span class="media-embed-card__label">' . $provider['icon'] . ' ' . $this->escape($provider['label']) . '</span> '
            . '<a class="media-embed-card__link" href="' . $escapedUrl . '">' . $escapedUrl . '</a>'
            . '</span>';
    }

    private function renderYoutubeExpando(array $match): string
    {
        $embedSrc = 'https://www.youtube-nocookie.com/embed/' . $this->escape($match['embedId']);

        return '<details class="media-embed-card media-embed-card--inline-player" data-media-embed-card data-provider="youtube">'
            . '<summary>▶ Watch on YouTube</summary>'
            . '<iframe class="media-embed-card__iframe" data-media-embed-iframe data-embed-src="' . $this->escape($embedSrc) . '"'
            . ' title="YouTube video player" src=""'
            . ' sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"'
            . ' referrerpolicy="strict-origin-when-cross-origin" loading="lazy" allowfullscreen></iframe>'
            . '</details>';
    }

    private function renderInstagramCard(array $match): string
    {
        $cached = $this->previewCacheStore?->get('instagram', $match['embedId']);

        if ($cached !== null && $cached['title'] !== null && $cached['thumbnailUrl'] !== null) {
            return $this->renderInstagramPreviewCard($match, $cached['title'], $cached['thumbnailUrl']);
        }

        return $this->renderPlainCard($match) . $this->renderPreviewWarmBeacon($match);
    }

    private function renderInstagramPreviewCard(array $match, string $title, string $thumbnailUrl): string
    {
        $escapedUrl = $this->escape($match['displayUrl']);

        return '<a class="media-embed-card media-embed-card--preview" data-media-embed-card data-provider="instagram" href="' . $escapedUrl . '">'
            . '<img class="media-embed-card__thumbnail" src="' . $this->escape($thumbnailUrl) . '" alt="" loading="lazy">'
            . '<span class="media-embed-card__label">📷 Instagram</span> '
            . '<span class="media-embed-card__preview-title">' . $this->escape($title) . '</span>'
            . '</a>';
    }

    private function renderPreviewWarmBeacon(array $match): string
    {
        if ($this->previewCacheStore === null) {
            return '';
        }

        $warmUrl = self::WARM_PREVIEW_ENDPOINT . '?provider=instagram&url=' . rawurlencode($match['displayUrl']);

        return '<img class="media-embed-card__warm-beacon" data-media-embed-warm-beacon src="' . $this->escape($warmUrl) . '" alt="" width="0" height="0" style="display:none" loading="eager">';
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
