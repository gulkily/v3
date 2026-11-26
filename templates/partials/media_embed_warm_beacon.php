<?php
/**
 * The hidden request that fetches a link's title for a thread with an empty
 * subject (MediaEmbedPreviewController::warmPreview()), when the opening post
 * is that one link (or, with first-line titles on, starts with it).
 *
 * @var array<string, mixed> $thread needs root_post_id and subject
 * @var string $beaconBody the opening post's text, or its preview
 */
if (!($mediaEmbedsEnabled ?? false) || trim((string) ($thread['subject'] ?? '')) !== '') {
    return;
}
$beaconMatch = \ForumRewrite\Support\ThreadTitle::bareMediaEmbedMatch('', (string) $beaconBody, (bool) ($mediaEmbedFirstLineTitles ?? false));
if ($beaconMatch === null) {
    return;
}
$beaconUrl = '/internal/media-embeds/warm-preview?provider=' . rawurlencode($beaconMatch['provider'])
    . '&url=' . rawurlencode($beaconMatch['url'])
    . '&thread_id=' . rawurlencode((string) $thread['root_post_id']);
echo '<img class="media-embed-card__warm-beacon" data-media-embed-warm-beacon src="' . $e($beaconUrl) . '" alt="" width="0" height="0" style="display:none" loading="eager">';
