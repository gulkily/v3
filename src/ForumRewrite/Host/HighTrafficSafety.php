<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

/** Canonical writers already hold the repository publication lock before invoking this hook. */
final class HighTrafficSafety
{
    public static function beforeWrite(string $repository, string $database, string $relativePath): void
    {
        $project = dirname(__DIR__,3);
        $cache = new HighTrafficCache($project,$repository,$database,getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: $project . '/public');
        if (!is_file($cache->root . '/state.json')) return;
        // Restrictions/feature changes and retractions retire the bypass before canonical publication.
        $withdraw = preg_match('~(?:post-retractions|feature-flags|thread-labels|approval-seeds|approvals)/|feature-flags\.txt|site-feature-flags~', $relativePath) === 1;
        $cache->locked(function () use ($cache,$withdraw): void {
            $state = $cache->state();
            $state['generation'] = bin2hex(random_bytes(16));
            if ($withdraw) {
                self::detach($cache->publicRoot);
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
