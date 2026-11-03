<?php
/**
 * One server-rendered line naming who stands behind a card's visible signals:
 * eligible likers, eligible flaggers and the identity that set the thread title.
 * Identical for every viewer; the viewer's own click appends in place.
 *
 * @var array{like: list<array{username: string, profile_slug: ?string}>, flag: list<array{username: string, profile_slug: ?string}>, title_author: ?array{username: string, profile_slug: ?string}} $signals
 * @var string $signalsScope 'thread' for the root card, 'post' for replies
 * @var string $signalsClass extra class for the host layout
 */
$signals ??= ['like' => [], 'flag' => [], 'title_author' => null];
$signalsScope ??= 'post';
$signalsClass ??= '';
$signalActorLink = static function (array $actor) use ($e): string {
    $name = $e((string) $actor['username']);

    return isset($actor['profile_slug']) && $actor['profile_slug'] !== ''
        ? '<a href="/profiles/' . $e((string) $actor['profile_slug']) . '">' . $name . '</a>'
        : $name;
};
$signalSegment = static function (string $tag, string $label, array $actors) use ($signalActorLink): string {
    $shown = array_slice($actors, 0, 3);
    $more = count($actors) - count($shown);
    $names = implode(', ', array_map($signalActorLink, $shown));

    return '<span data-signal-tag="' . $tag . '" data-signal-count="' . count($actors) . '"' . ($actors === [] ? ' hidden' : '') . '>'
        . $label . ' <span data-signal-names>' . $names . '</span><span data-signal-more' . ($more > 0 ? '' : ' hidden') . '> +' . $more . '</span></span>';
};
$signalLikes = is_array($signals['like'] ?? null) ? $signals['like'] : [];
$signalFlags = is_array($signals['flag'] ?? null) ? $signals['flag'] : [];
$signalTitleAuthor = is_array($signals['title_author'] ?? null) ? $signals['title_author'] : null;
$signalsEmpty = $signalLikes === [] && $signalFlags === [] && $signalTitleAuthor === null;
?>
<p class="meta post-signals<?= $signalsClass !== '' ? ' ' . $e($signalsClass) : '' ?>" data-role="post-signals" data-signal-scope="<?= $e($signalsScope) ?>"<?= $signalsEmpty ? ' hidden' : '' ?>><?= $signalSegment('like', 'Liked by', $signalLikes) ?><?= $signalSegment('flag', 'Flagged by', $signalFlags) ?><?php if ($signalTitleAuthor !== null): ?><span data-signal-tag="title">Title set by <?= $signalActorLink($signalTitleAuthor) ?></span><?php endif; ?></p>
