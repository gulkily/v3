<?php

declare(strict_types=1);
namespace ForumRewrite\View;

/** Disposable per-release cache; nonblocking locks deduplicate concurrent discovery. */
final class BandcampPlayerResolver
{
    public function __construct(private readonly string $projectRoot, private readonly BandcampPagePlayerFetcher $fetcher = new BandcampPagePlayerFetcher()) {}

    public function resolve(string $url): ?string
    {
        $release = BandcampReleaseUrl::parse($url);
        if ($release === null) { return null; }
        $directory = $this->projectRoot . '/state/cache/bandcamp_players';
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) { return null; }
        $handle = @fopen($directory . '/' . hash('sha256', $release['url']) . '.json', 'c+');
        if (!$handle) { return null; }
        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) { return null; }
            $cached = json_decode(stream_get_contents($handle, 4096), true);
            $now = time();
            if (is_array($cached) && ($cached['expires'] ?? 0) > $now && array_key_exists('result', $cached)) {
                return $this->playerUrl($cached['result'], $release['kind']);
            }
            $result = $this->fetcher->fetch($release['url']);
            $player = $this->playerUrl($result, $release['kind']);
            rewind($handle); ftruncate($handle, 0);
            fwrite($handle, json_encode(['expires' => $now + ($player !== null ? 86400 : 3600), 'result' => $result], JSON_THROW_ON_ERROR));
            return $player;
        } catch (\Throwable) { return null; }
        finally { fclose($handle); }
    }

    private function playerUrl(mixed $result, string $kind): ?string
    {
        if (!is_array($result) || ($result['kind'] ?? '') !== $kind || !is_string($result['id'] ?? null)
            || !preg_match('/^[1-9][0-9]{0,19}$/D', $result['id'])) { return null; }
        return 'https://bandcamp.com/EmbeddedPlayer/' . $kind . '=' . $result['id']
            . '/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=' . ($kind === 'album' ? 'true' : 'false') . '/artwork=small/transparent=true/';
    }
}
