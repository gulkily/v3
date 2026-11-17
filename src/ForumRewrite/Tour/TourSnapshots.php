<?php

declare(strict_types=1);

namespace ForumRewrite\Tour;

use ForumRewrite\Host\HighTrafficCache;
use ForumRewrite\Host\HighTrafficNotFound;
use ForumRewrite\Host\HighTrafficPublication;
use RuntimeException;
use Throwable;

/**
 * Tour snapshots: logged-out HTML copies of the pages listed in
 * resources/tour.txt, written to public/assets/tour/ with the tour page
 * itself, so a tour visit costs static files only.
 *
 * Refresh renders each route through the isolated tour worker
 * (scripts/tour_render.php). A route that fails keeps its previous
 * snapshot; a route that is not found (a missing thread, a feature turned
 * off) is left out of the page. Only one refresh runs at a time.
 */
final class TourSnapshots
{
    private const RETRY_SECONDS = 3600;

    public readonly string $directory;
    private readonly string $stateRoot;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        string $publicRoot,
        private readonly ?\Closure $progressReporter = null,
        private readonly ?string $tourFile = null,
    ) {
        $this->directory = $publicRoot . '/assets/tour';
        $this->stateRoot = $projectRoot . '/state/tour/' . substr(hash('sha256', realpath($repositoryRoot) ?: $repositoryRoot), 0, 16);
    }

    /** The instance's tour, with paths resolved the way the other ./v3 commands resolve them. */
    public static function forProject(string $projectRoot, ?\Closure $progressReporter = null): self
    {
        $settings = \ForumRewrite\Support\DeploymentSettings::class;
        return new self(
            $projectRoot,
            $settings::value('FORUM_REPOSITORY_ROOT') ?: \ForumRewrite\Support\LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot),
            $settings::value('FORUM_DATABASE_PATH') ?: \ForumRewrite\Support\LocalRepositoryBootstrap::defaultDatabasePath($projectRoot),
            $settings::value('FORUM_PUBLIC_ARTIFACT_ROOT') ?: $projectRoot . '/public',
            $progressReporter,
        );
    }

    /** @return array<string, mixed> */
    public function refresh(): array
    {
        $this->ensureDirectory($this->stateRoot);
        $lock = fopen($this->stateRoot . '/refresh.lock', 'c');
        if ($lock === false) throw new RuntimeException('Cannot open the tour refresh lock.');
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return ['outcome' => 'already_running'];
        }
        $started = gmdate('Y-m-d\TH:i:s\Z');
        try {
            $this->writeStatus(['State' => 'running', 'Started' => $started] + $this->previousResult());
            $sections = TourFile::fromFile($this->tourFile ?? $this->projectRoot . '/resources/tour.txt');
            $this->ensureDirectory($this->directory);
            $cache = new HighTrafficCache($this->projectRoot, $this->repositoryRoot, $this->databasePath, dirname($this->directory, 2), $this->progressReporter);
            $context = HighTrafficPublication::captureForRender($this->repositoryRoot);
            $results = [];
            $shown = [];
            foreach ($sections as $section) {
                $target = $this->directory . '/' . $section['name'] . '.html';
                $this->progress('Rendering ' . $section['route']);
                $workspace = $this->stateRoot . '/.render-' . bin2hex(random_bytes(8));
                try {
                    $html = $cache->render($section['route'], $workspace . '/assets', $context, 'tour_render.php');
                    $this->atomic($target, TourPage::prepareSnapshot($html));
                    $results[$section['name']] = 'ok';
                } catch (HighTrafficNotFound) {
                    if (is_file($target)) @unlink($target);
                    $results[$section['name']] = 'not found (left out)';
                } catch (Throwable $error) {
                    $results[$section['name']] = 'failed (' . (is_file($target) ? 'kept previous' : 'left out') . '): ' . self::oneLine($error->getMessage());
                } finally {
                    HighTrafficCache::removeTemporaryTree($workspace);
                }
                if (is_file($target)) $shown[] = $section;
            }
            $this->atomic($this->directory . '/index.html', TourPage::render($this->projectRoot, $shown, \ForumRewrite\SiteConfig::displayName()));
            $failed = count(array_filter($results, static fn (string $result): bool => str_starts_with($result, 'failed')));
            $outcome = $failed === 0 ? 'refreshed' : 'refreshed_with_failures';
            $status = ['State' => 'idle', 'Started' => $started, 'Finished' => gmdate('Y-m-d\TH:i:s\Z'), 'Outcome' => $outcome, 'Shown' => (string) count($shown)];
            foreach ($results as $name => $result) $status['Route-' . $name] = $result;
            $this->writeStatus($status);
            return ['outcome' => $outcome, 'shown' => count($shown), 'routes' => $results];
        } catch (Throwable $error) {
            $this->writeStatus(['State' => 'idle', 'Started' => $started, 'Finished' => gmdate('Y-m-d\TH:i:s\Z'), 'Outcome' => 'failed', 'Error' => self::oneLine($error->getMessage())]);
            throw $error;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string, string> Key: value status, empty before the first refresh. */
    public function status(): array
    {
        $text = @file_get_contents($this->stateRoot . '/status.txt');
        if (!is_string($text)) return [];
        $status = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            if (preg_match('/^([A-Za-z-]+):\s?(.*)$/', $line, $match) === 1) $status[$match[1]] = $match[2];
        }
        if (($status['State'] ?? '') === 'running' && $this->idle()) {
            $status['State'] = 'interrupted';
        }
        return $status;
    }

    /**
     * True when a refresh should run on its own: the page or a snapshot is
     * missing, or the deployed code is newer than the last refresh.
     */
    public function needsRefresh(): bool
    {
        $index = $this->directory . '/index.html';
        if (!is_file($index)) return true;
        $built = (int) filemtime($index);
        try {
            $sections = TourFile::fromFile($this->tourFile ?? $this->projectRoot . '/resources/tour.txt');
        } catch (Throwable) {
            return false;
        }
        foreach ([$this->tourFile ?? $this->projectRoot . '/resources/tour.txt', $this->projectRoot . '/templates/tour/page.php'] as $source) {
            if (is_file($source) && (int) filemtime($source) > $built) return true;
        }
        $head = $this->codeVersionTime();
        if ($head !== null && $head > $built) return true;
        $status = $this->status();
        // A failure is retried after an hour, not every cron minute.
        $finished = strtotime($status['Finished'] ?? '') ?: 0;
        $retry = time() - $finished >= self::RETRY_SECONDS;
        if (($status['Outcome'] ?? '') === 'failed') return $retry;
        foreach ($sections as $section) {
            $result = $status['Route-' . $section['name']] ?? null;
            $exists = is_file($this->directory . '/' . $section['name'] . '.html');
            if ($result === null) return true;
            if ($result === 'ok' && !$exists) return true;
            if (str_starts_with($result, 'failed') && !$exists && $retry) return true;
        }
        return false;
    }

    private function idle(): bool
    {
        $lock = @fopen($this->stateRoot . '/refresh.lock', 'c');
        if ($lock === false) return true;
        $free = flock($lock, LOCK_EX | LOCK_NB);
        if ($free) flock($lock, LOCK_UN);
        fclose($lock);
        return $free;
    }

    private function codeVersionTime(): ?int
    {
        $head = $this->projectRoot . '/.git/HEAD';
        if (!is_file($head)) return null;
        $reference = trim((string) file_get_contents($head));
        $path = str_starts_with($reference, 'ref: ') ? $this->projectRoot . '/.git/' . substr($reference, 5) : $head;
        return is_file($path) ? (int) filemtime($path) : (int) filemtime($head);
    }

    /** @return array<string, string> */
    private function previousResult(): array
    {
        $previous = $this->status();
        unset($previous['State'], $previous['Started']);
        return $previous;
    }

    /** @param array<string, string> $status */
    private function writeStatus(array $status): void
    {
        $text = "Schema: tour-status-v1\n";
        foreach ($status as $key => $value) $text .= $key . ': ' . self::oneLine($value) . "\n";
        $this->atomic($this->stateRoot . '/status.txt', $text);
    }

    private function atomic(string $path, string $contents): void
    {
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $contents) !== strlen($contents) || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Cannot write ' . $path . '.');
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException('Cannot create ' . $path . '.');
        }
    }

    private function progress(string $message): void
    {
        $this->progressReporter?->__invoke($message);
    }

    private static function oneLine(string $value): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $value) ?? ''), 0, 300);
    }
}
