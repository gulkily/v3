<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

/** Native writers hold the publication lock; external maintenance must keep routing disabled until complete. */
final class HighTrafficSafety
{
    public static function beforeWrite(string $repository, string $database, string $relativePath, ?string $contents = null): void
    {
        if (str_ends_with($relativePath,'.asc')) return;
        $project = dirname(__DIR__,3);
        $cache = new HighTrafficCache($project,$repository,$database,\ForumRewrite\Support\DeploymentSettings::value('FORUM_PUBLIC_ARTIFACT_ROOT', $project) ?: $project . '/public');
        if (!is_file($cache->root . '/state.json')) return;
        // Restrictions/feature changes retire the bypass before canonical publication.
        $withdraw = preg_match('~(?:feature-flags|approval-seeds|approvals)/|feature-flags\.txt|site-feature-flags~', $relativePath) === 1;
        if (str_starts_with($relativePath,'records/thread-labels/') || str_starts_with($relativePath,'records/post-reactions/')) {
            // Positive votes/tags are ordinary stale-tolerant changes; moderation flags can hide content.
            $withdraw=true;
            if ($contents!==null) {
                try {
                    $tags=str_starts_with($relativePath,'records/thread-labels/')
                        ? (new \ForumRewrite\Canonical\ThreadLabelRecordParser())->parse($contents)->labels
                        : (new \ForumRewrite\Canonical\PostReactionRecordParser())->parse($contents)->tags;
                    $withdraw=array_intersect(array_map('strtolower',$tags),['flag','identity','internal'])!==[];
                } catch (\Throwable) { /* Unknown reaction shape retires serving conservatively. */ }
            }
        }
        $cache->locked(function () use ($cache,$withdraw): void {
            $state = $cache->state();
            if (!$withdraw) {
                // Stale-tolerant writes must not continually cancel background renders.
                // One coalesced marker asks the next popularity refresh to include fresh pages.
                if (($state['enabled'] ?? false) && !is_file($cache->root . '/dirty')) $cache->atomic($cache->root . '/dirty',gmdate('c'));
                return;
            }
            $state['generation'] = bin2hex(random_bytes(16));
            if ($withdraw) {
                self::markWithdrawn($cache->root);
                self::detach($state['public_root'] ?? $cache->publicRoot);
                $state['enabled']=false; $state['reason']='Content/access change; operator may re-enable after review.'; $state['source']='withdrawal';
            }
            $cache->atomic($cache->root . '/state.json',json_encode($state,JSON_THROW_ON_ERROR));
        });
    }
    /**
     * After a post is committed: refresh its cached thread page, or queue it for cron.
     * Never throws. Returns HighTrafficRefresh::afterPost's outcome.
     */
    public static function afterPost(string $repository, string $database, string $threadId): string
    {
        try {
            $project = dirname(__DIR__,3);
            $cache = new HighTrafficCache($project,$repository,$database,\ForumRewrite\Support\DeploymentSettings::value('FORUM_PUBLIC_ARTIFACT_ROOT', $project) ?: $project . '/public');
            if (!is_file($cache->root . '/state.json')) return 'inactive';
            return (new HighTrafficRefresh($cache))->afterPost($threadId);
        } catch (\Throwable $error) {
            error_log('High-traffic mode: post refresh failed: ' . $error->getMessage());
            return 'skipped';
        }
    }
    /**
     * Removal path: withdraw serving and mark each high-traffic cache that serves this public
     * root, so turning the mode on again never keeps a page rendered before the removal,
     * even when the mode was off at the time.
     */
    public static function withdraw(string $publicRoot): void
    {
        $public = realpath($publicRoot) ?: $publicRoot;
        foreach (glob(dirname(__DIR__,3) . '/state/high_traffic/*/state.json') ?: [] as $state) {
            $saved = json_decode((string)@file_get_contents($state), true);
            if (is_array($saved) && ($saved['public_root'] ?? null) === $public) self::markWithdrawn(dirname($state));
        }
        self::detach($publicRoot);
    }
    public static function markWithdrawn(string $cacheRoot): void
    {
        if (is_dir($cacheRoot) && @file_put_contents($cacheRoot . '/withdrawn', gmdate('c') . "\n") === false) throw new \RuntimeException('Cannot mark high-traffic cache withdrawn; write aborted.');
    }
    public static function detach(string $publicRoot): void
    {
        $link = $publicRoot . '/.high-traffic';
        if (is_link($link)) {
            if (!unlink($link)) throw new \RuntimeException('Cannot withdraw high-traffic cache; write aborted.');
        } elseif (file_exists($link)) throw new \RuntimeException('High-traffic serving path must be a managed symlink.');
    }
}
