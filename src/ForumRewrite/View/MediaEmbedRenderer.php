<?php

declare(strict_types=1);

namespace ForumRewrite\View;

final class MediaEmbedRenderer
{
    private const PROVIDER_LABELS = [
        'youtube' => ['icon' => '▶', 'label' => 'YouTube'],
        'instagram' => ['icon' => '📷', 'label' => 'Instagram'],
        'bandcamp' => ['icon' => '♫', 'label' => 'Bandcamp'],
        'soundcloud' => ['icon' => '♫', 'label' => 'SoundCloud'],
        'spotify' => ['icon' => '♫', 'label' => 'Spotify'],
        'tiktok' => ['icon' => '▶', 'label' => 'TikTok'],
    ];

    private const WARM_PREVIEW_ENDPOINT = '/internal/media-embeds/warm-preview';

    public function __construct(
        private readonly MediaEmbedDetector $detector = new MediaEmbedDetector(),
        private readonly ?MediaEmbedPreviewCacheStore $previewCacheStore = null,
        private readonly ?\ForumRewrite\Images\ImageStore $imageStore = null,
        private readonly ?MediaEmbedPosterStore $posterStore = null,
    ) {
    }

    /**
     * With $markdown, text between image and embed cards renders through the post-safe Markdown
     * renderer, and only embed URLs alone on their line become cards (others are ordinary links).
     */
    public function render(string $body, bool $enabled, bool $inlinePlayerEnabled = false, bool $imagesEnabled = false, bool $markdown = false, bool $tagLinks = true): string
    {
        try { $image = \ForumRewrite\Images\ImageReference::fromBody($body); }
        catch (\RuntimeException) { $image = null; }
        if ($image !== null) {
            $line = \ForumRewrite\Images\ImageReference::line($image);
            [$before, $after] = explode($line, $body, 2);
            $available = false;
            if ($imagesEnabled && $this->imageStore !== null) {
                try { $this->imageStore->resolve($image); $available = true; } catch (\RuntimeException) { /* missing image: render the unavailable placeholder */ }
            }
            $description = $this->escape($image['description']);
            $html = '<span class="post-image" data-image-reference="' . $image['sha256'] . '">';
            if ($available) {
                // The page shows a compressed copy sized for the screen; the stored original opens from the link.
                $sources = \ForumRewrite\Images\ThumbnailStore::displaySources($image);
                $srcset = \ForumRewrite\Images\ThumbnailStore::displaySrcset($image);
                $html .= '<a class="post-image__original" href="/images/' . \ForumRewrite\Images\ImageReference::filename($image) . '">'
                    . '<img src="' . $sources[min(1, count($sources) - 1)]['url'] . '"' . ($srcset === null ? '' : ' srcset="' . $srcset . '" sizes="(min-width: 48rem) 45rem, 100vw"')
                    . ' alt="' . $description . '" width="' . $image['width'] . '" height="' . $image['height'] . '" loading="lazy" style="max-width:100%;height:auto"></a>';
            }
            $html .= '<span class="meta">' . ($available ? '' : 'Image unavailable' . ($description === '' ? '' : ' — ')) . $description . '</span>';
            ['details' => $details, 'rest' => $after] = \ForumRewrite\Images\ImageReference::photoDetails($after);
            if ($details !== []) {
                $list = '<span class="post-photo-details">';
                foreach ($details as $detail) {
                    $list .= '<span class="meta">' . $this->escape($detail['label']) . '</span><span>' . $this->escape($detail['display']) . '</span>';
                }
                $list .= '</span>';
                // One detail stays visible; two or more fold behind a closed "Photo details" row.
                $html .= count($details) === 1 ? $list
                    : '<details class="post-photo-details-disclosure"><summary class="meta">Photo details</summary>' . $list . '</details>';
            }
            $html .= '</span>';
            return $this->render($before, $enabled, $inlinePlayerEnabled, false, $markdown, $tagLinks) . $html . $this->render($after, $enabled, $inlinePlayerEnabled, false, $markdown, $tagLinks);
        }
        $matches = $enabled ? $this->detector->detect($body) : [];
        if ($markdown) {
            $matches = array_values(array_filter($matches, static fn (array $match): bool => self::standsAlone($body, $match)));
        }
        $text = $markdown
            ? static fn (string $segment): string => trim($segment) === '' ? '' : \ForumRewrite\Docs\PublicMarkdownRenderer::renderPost(trim($segment, "\r\n"), $tagLinks)
            : fn (string $segment): string => $this->escapeAndBreak($segment, $tagLinks);

        if ($matches === []) {
            return $text($body);
        }

        usort($matches, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        $result = '';
        $cursor = 0;

        foreach ($matches as $match) {
            $result .= $text(substr($body, $cursor, $match['offset'] - $cursor));
            $result .= $this->renderCard($match, $inlinePlayerEnabled);
            $cursor = $match['offset'] + $match['length'];
        }

        $result .= $text(substr($body, $cursor));

        return $result;
    }

    /** @param array{offset:int,length:int} $match */
    private static function standsAlone(string $body, array $match): bool
    {
        $lineStart = strrpos(substr($body, 0, $match['offset']), "\n");
        $before = substr($body, $lineStart === false ? 0 : $lineStart + 1, $match['offset'] - ($lineStart === false ? 0 : $lineStart + 1));
        $rest = substr($body, $match['offset'] + $match['length']);
        $lineEnd = strpos($rest, "\n");
        $after = $lineEnd === false ? $rest : substr($rest, 0, $lineEnd);

        return trim($before) === '' && trim($after) === '';
    }

    public function hasEmbeds(string $body): bool
    {
        return $this->detector->detect($body) !== [];
    }

    private function renderCard(array $match, bool $inlinePlayerEnabled): string
    {
        if ($inlinePlayerEnabled && $match['provider'] === 'youtube') {
            return $this->renderYoutubeExpando($match);
        }

        if ($inlinePlayerEnabled && $match['provider'] === 'instagram') {
            return $this->renderInstagramCard($match);
        }

        if ($inlinePlayerEnabled && $match['provider'] === 'bandcamp') {
            return $this->renderPlayerExpando(
                $match, '', 'Bandcamp ' . $match['contentKind'] . ' player',
                ' media-embed-card--bandcamp' . ($match['contentKind'] === 'track' ? ' media-embed-card--track' : ''),
                '/internal/media-embeds/bandcamp-player?url=' . rawurlencode($match['embedId']),
            );
        }

        if ($inlinePlayerEnabled && $match['provider'] === 'soundcloud') {
            return $this->renderPlayerExpando(
                $match,
                'https://w.soundcloud.com/player/?url=' . rawurlencode('https://soundcloud.com/' . $match['embedId']) . '&auto_play=false',
                'SoundCloud ' . $match['contentKind'] . ' player',
                ' media-embed-card--soundcloud' . ($match['contentKind'] === 'playlist' ? ' media-embed-card--collection' : ''),
            );
        }

        if ($inlinePlayerEnabled && $match['provider'] === 'spotify') {
            return $this->renderPlayerExpando(
                $match,
                'https://open.spotify.com/embed/' . $match['contentKind'] . '/' . $match['embedId'],
                'Spotify ' . $match['contentKind'] . ' player',
                ' media-embed-card--spotify',
            );
        }

        if ($inlinePlayerEnabled && $match['provider'] === 'tiktok') {
            return $this->renderPlayerExpando(
                $match,
                'https://www.tiktok.com/player/v1/' . $match['embedId'] . '?autoplay=0',
                'TikTok video player',
                ' media-embed-card--portrait',
            );
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
        $shorts = ($match['contentKind'] ?? '') === 'shorts';
        $poster = '';
        $beacon = '';
        // With posters on, a stored poster replaces the bar; a missing one is fetched in the background for next time.
        if ($this->posterStore !== null && !$shorts) {
            if ($this->posterStore->path($match['embedId']) !== null) {
                $poster = '<img class="media-embed-card__poster" src="/internal/media-embeds/posters/' . $this->escape($match['embedId']) . '.jpg" alt="" width="480" height="360" loading="lazy">';
            } else {
                $beacon = '<img class="media-embed-card__warm-beacon" data-media-embed-warm-beacon src="'
                    . $this->escape(self::WARM_PREVIEW_ENDPOINT . '?provider=youtube&kind=poster&url=' . rawurlencode($match['displayUrl']))
                    . '" alt="" width="0" height="0" style="display:none" loading="eager">';
            }
        }

        return $this->renderPlayerExpando(
            $match,
            'https://www.youtube-nocookie.com/embed/' . $match['embedId'],
            'YouTube video player',
            ($shorts ? ' media-embed-card--portrait' : '') . ($poster !== '' ? ' media-embed-card--poster' : ''),
            poster: $poster,
        ) . $beacon;
    }

    /**
     * One bar per embed: a play control (the disclosure's summary) and, beside it, the provider
     * link. The link sits outside the summary so tapping it never toggles the player.
     */
    private function renderPlayerExpando(array $match, string $embedSrc, string $title, string $extraClass = '', string $resolveSrc = '', string $poster = ''): string
    {
        $provider = self::PROVIDER_LABELS[$match['provider']]['label'];
        return '<span class="media-embed-player" data-provider="' . $this->escape($match['provider']) . '">'
            . '<span class="media-embed-card__source"><a class="media-embed-card__link" href="' . $this->escape($match['displayUrl']) . '" aria-label="Open on ' . $this->escape($provider) . '">' . $this->escape($provider) . ' ↗</a></span>'
            . '<details class="media-embed-card media-embed-card--inline-player' . $extraClass . '" data-media-embed-card data-provider="' . $this->escape($match['provider']) . '">'
            . '<summary>' . $poster . '<span class="media-embed-card__play" aria-hidden="true"></span><span class="media-embed-card__action">Play</span><span class="media-embed-card__action media-embed-card__action--close">Close</span></summary>'
            . '<iframe class="media-embed-card__iframe" data-media-embed-iframe data-embed-src="' . $this->escape($embedSrc) . '"'
            . ($resolveSrc !== '' ? ' hidden data-resolve-src="' . $this->escape($resolveSrc) . '"' : '')
            . ' title="' . $this->escape($title) . '" src=""'
            . ' sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"'
            . ' allow="autoplay; encrypted-media; fullscreen; picture-in-picture"'
            . ' referrerpolicy="strict-origin-when-cross-origin" loading="lazy" allowfullscreen></iframe>'
            . ($resolveSrc !== '' ? '<span data-media-embed-status role="status"></span>' : '')
            . '</details></span>';
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

    private function escapeAndBreak(string $value, bool $tagLinks): string
    {
        return nl2br($tagLinks ? PostHashtags::linkEscapedText($this->escape($value)) : $this->escape($value));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
