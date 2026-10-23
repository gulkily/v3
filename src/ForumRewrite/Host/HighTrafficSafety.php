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
        $cache = new HighTrafficCache($project,$repository,$database,getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: $project . '/public');
        if (!is_file($cache->root . '/state.json')) return;
        // Restrictions/feature changes and retractions retire the bypass before canonical publication.
        $withdraw = preg_match('~(?:post-retractions|feature-flags|approval-seeds|approvals)/|feature-flags\.txt|site-feature-flags~', $relativePath) === 1;
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
                self::detach($state['public_root'] ?? $cache->publicRoot);
                $state['enabled']=false; $state['reason']='Content/access change; operator may re-enable after review.';
            }
            $cache->atomic($cache->root . '/state.json',json_encode($state,JSON_THROW_ON_ERROR));
        });
    }
    public static function detach(string $publicRoot): void
    {
        $link = $publicRoot . '/.high-traffic';
        if (is_link($link)) {
            if (!unlink($link)) throw new \RuntimeException('Cannot withdraw high-traffic cache; write aborted.');
        } elseif (file_exists($link)) throw new \RuntimeException('High-traffic serving path must be a managed symlink.');
    }
}
