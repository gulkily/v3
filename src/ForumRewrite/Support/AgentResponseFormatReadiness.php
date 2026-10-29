<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use ForumRewrite\Canonical\AgentResponseMetadata;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\RecordLimits;

/** Read-only live-tree inspection; never clears history or external archives for retirement. */
final class AgentResponseFormatReadiness
{
    public function __construct(private readonly string $root) {}

    public function inspect(?callable $afterScan = null): array
    {
        $issues = [];
        $before = $this->inventory($issues);
        $repository = new CanonicalRecordRepository($this->root);
        $legacy = []; $posts = []; $expansions = [];
        foreach ($before as $path => $digest) {
            try {
                $text = RecordLimits::read($this->root . '/' . $path);
                if (hash('sha256', $text) !== $digest) throw new \RuntimeException('Record changed during scan.');
                $headers = explode("\n\n", $text, 2)[0];
                $isLegacy = preg_match('/^Agent-Response:/m', $headers) === 1;
                if ($isLegacy) {
                    preg_match('/^Post-ID: ([^\r\n]+)$/m', $headers, $id);
                    $legacy[] = ['post_id' => $id[1] ?? null, 'path' => $path];
                }
                $record = $repository->loadPost($path);
                if (isset($posts[$record->postId])) throw new \RuntimeException('Duplicate Post-ID: ' . $record->postId);
                $posts[$record->postId] = $path;
                if (($record->agentResponse['type'] ?? null) === AgentResponseMetadata::EXPANSION) {
                    $expansions[] = ['post_id' => $record->postId, 'path' => $path,
                        'source_post_id' => $record->agentResponse['source_post_id'], 'expansion_of' => $record->agentResponse['expansion_of']];
                }
            } catch (\Throwable $error) { $issues[] = $path . ': ' . $error->getMessage(); }
        }
        if ($afterScan !== null) $afterScan();
        $after = $this->inventory($issues);
        if ($before !== $after) $issues[] = 'Live post tree changed during inspection; stop writers and rerun.';
        $legacyIds = array_column($legacy, 'post_id');
        $dependent = [];
        foreach ($expansions as $expansion) {
            $dependencies = [];
            foreach (array_unique([$expansion['source_post_id'], $expansion['expansion_of']]) as $id) {
                if (!isset($posts[$id])) $issues[] = $expansion['path'] . ': Missing dependency ' . $id;
                if (in_array($id, $legacyIds, true)) $dependencies[] = $id;
            }
            if ($dependencies !== []) $dependent[] = $expansion + ['legacy_dependencies' => $dependencies];
        }
        $issues = array_values(array_unique($issues));
        return ['status' => $issues !== [] ? 'blocked' : ($legacy !== [] ? 'legacy-remains' : 'live-tree-ready'),
            'scan_complete' => $issues === [], 'live_repository_ready' => $issues === [] && $legacy === [],
            'scanned_posts' => count($before), 'legacy_records' => $legacy, 'dependent_expansions' => $dependent,
            'blockers' => $issues, 'archive_coverage' => 'unknown: Git history and external archives were not inspected',
            'retirement_decision' => 'not authorized; retain compatibility until retained records and archive policy are resolved'];
    }

    /** Two bounded-content passes detect observable changes without retaining post bodies. */
    private function inventory(array &$issues): array
    {
        $files = [];
        $base = $this->root . '/records/posts';
        if (is_link($this->root . '/records') || is_link($base) || !is_dir($base)) {
            $issues[] = 'Missing or symlinked records/posts directory; select an existing canonical repository.';
            return [];
        }
        $walk = function (string $directory) use (&$walk, &$files, &$issues): void {
            $names = @scandir($directory);
            if ($names === false) { $issues[] = 'Unreadable directory: ' . $directory; return; }
            foreach ($names as $name) {
                if ($name === '.' || $name === '..') continue;
                $absolute = $directory . '/' . $name;
                $path = substr($absolute, strlen($this->root) + 1);
                if (is_link($absolute)) { $issues[] = 'Symlink not inspected: ' . $path; continue; }
                if (is_dir($absolute)) { $walk($absolute); continue; }
                if (!str_ends_with($name, '.txt')) continue;
                try { $files[$path] = hash('sha256', RecordLimits::read($absolute)); }
                catch (\Throwable $error) { $issues[] = $path . ': ' . $error->getMessage(); }
            }
        };
        $walk($base);
        ksort($files);
        return $files;
    }
}
