<?php

declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
use ForumRewrite\Host\HighTrafficCache;
use ForumRewrite\Host\HighTrafficMode;
use ForumRewrite\Support\LocalRepositoryBootstrap;
$project=dirname(__DIR__);
$repository=getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($project);
$database=getenv('FORUM_DATABASE_PATH') ?: LocalRepositoryBootstrap::defaultDatabasePath($project);
$public=getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: $project . '/public';
$cache=new HighTrafficCache($project,$repository,$database,$public);
$mode=new HighTrafficMode($cache);
try {
    $command=$argv[1] ?? 'status';
    $result=match($command) {
        'enable'=>$mode->change(true,'Operator CLI enable'),
        'disable'=>$mode->change(false,'Operator CLI disable'),
        'restore'=>$mode->restore(),
        'status'=>$mode->status(),
        'purge'=>(function () use ($mode) { $mode->purge();return $mode->status(); })(),
        'refresh'=>(function () use ($cache,$argv) { $html=$cache->generate($argv[2] ?? '/',true);if ($html===null) throw new RuntimeException('No generation capacity or inactive mode.');return ['refreshed'=>$argv[2] ?? '/']; })(),
        default=>throw new RuntimeException('Usage: ./v3 high-traffic enable|disable|status|restore|purge|refresh [/route/]'),
    };
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) { fwrite(STDERR,$error->getMessage() . "\n");exit(1); }
