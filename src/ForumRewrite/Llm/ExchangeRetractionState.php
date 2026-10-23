<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\RetractedPosts;

final class ExchangeRetractionState
{
    /** null means unavailable, never an assertion that nothing was retracted. */
    public static function read(string $root): ?array
    {
        if (!is_dir($root . '/records') || !is_readable($root . '/records')) return null;
        if (file_exists($root . '/.git')) {
            $tracked = []; $code = 0;
            exec('git -C ' . escapeshellarg($root) . ' ls-tree -r --name-only HEAD -- records/post-retractions 2>/dev/null', $tracked, $code);
            if ($code !== 0) return null;
            foreach ($tracked as $path) {
                if (str_ends_with($path, '.txt') && (!is_file($root . '/' . $path) || !is_readable($root . '/' . $path))) return null;
            }
        }
        $ids = [];
        try {
            foreach (\ForumRewrite\Canonical\LabelRemoval::all($root) as $id => $removal) {
                if ($removal['operation'] === 'delete') $ids[$id] = true;
            }
        } catch (\Throwable) { return null; }
        $directory = $root . '/records/post-retractions';
        if (!file_exists($directory)) return $ids;
        if (!is_dir($directory) || !is_readable($directory) || is_link($directory)) return null;
        $paths = glob($directory . '/*');
        if ($paths === false) return null;
        $repository = new CanonicalRecordRepository($root);
        try {
            foreach ($paths as $path) {
                if (pathinfo($path, PATHINFO_EXTENSION) !== 'txt') continue;
                if (is_link($path)) return null;
                $record = $repository->loadPostRetraction('records/post-retractions/' . basename($path));
                $found = false;
                foreach (CanonicalPathResolver::postCandidates($record->postId) as $postPath) {
                    if (!is_file($root . '/' . $postPath)) continue;
                    $found = true;
                    if (RetractedPosts::applies($repository->loadPost($postPath), $record)) $ids[$record->postId] = true;
                    break;
                }
                if (!$found && !isset($ids[$record->postId])) return null;
            }
        } catch (\Throwable) { return null; }
        return $ids;
    }
}
