<?php

declare(strict_types=1);
namespace ForumRewrite\Scoring;

final class FastmodMetaArtifacts
{
    public static function flush(FastmodMetaStore $store,string $path,string $root,?string $staticRoot=null): void
    {
        if (!$store->dirty()) return;
        $token=['path'=>$path,'generation'=>FastmodMetaPublication::generation($path)];
        (new \ForumRewrite\Support\ExecutionLock($path.'.publication.lock'))->withExclusiveLock(function () use ($store,$path,$root,$staticRoot,$token): void {
            if (FastmodMetaPublication::generation($path) !== $token['generation']) throw new \RuntimeException('Meta decisions changed before invalidation; retry.');
            $generation=$store->generation();
            $staticRoot ??= getenv('FORUM_STATIC_HTML_ROOT') ?: \ForumRewrite\PresentationPathResolver::staticHtmlRoot($root,\ForumRewrite\SiteProfileRegistry::active());
            $public=getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: $root.'/public';
            (new \ForumRewrite\Write\StaticArtifactInvalidator($public,$staticRoot))->invalidateAllGeneratedPresentation();
            $queue=\ForumRewrite\TaskQueue\TaskQueueDatabaseConfig::path($root);
            if (!is_dir(dirname($queue)) && !mkdir(dirname($queue),0700,true) && !is_dir(dirname($queue))) throw new \RuntimeException('Cannot open offline regeneration queue.');
            (new \ForumRewrite\TaskQueue\SqliteTaskQueueStore(new \PDO('sqlite:'.$queue)))->enqueue(\ForumRewrite\TaskQueue\SqliteTaskQueueStore::PUBLISH_OFFLINE_SNAPSHOT,'offline-snapshot');
            $store->acknowledge($generation);
        });
    }
}
