<?php

declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
use ForumRewrite\Host\HighTrafficCache;
use ForumRewrite\Host\HighTrafficMode;
use ForumRewrite\Support\LocalRepositoryBootstrap;
$project=dirname(__DIR__);
$arguments=array_slice($argv,1);
$json=in_array('--json',$arguments,true);
$arguments=array_values(array_filter($arguments,fn ($arg) => $arg!=='--json'));
$command=$arguments[0] ?? 'status';
$usage="Usage: ./v3 high-traffic enable|disable|status|restore|purge|prepare [--json]\n"
    . "       ./v3 high-traffic refresh [/route/] [--json]\n"
    . "       ./v3 high-traffic cron|run [access.log] [--json]\n"
    . "prepare builds/reuses one shared model outside rendering (works while disabled).\ncron prints installation instructions; run refreshes pages once.\n"
    . "prepare/refresh/run stream elapsed progress to stderr; --json keeps results on stdout.\n";
if (in_array($command,['help','--help','-h'],true)) { echo $usage;exit(0); }
try {
    if (!in_array($command,['enable','disable','status','restore','purge','prepare','refresh','cron','run'],true)) throw new RuntimeException('Unknown high-traffic command.');
    $maximum=in_array($command,['refresh','cron','run'],true) ? 2 : 1;
    if (count($arguments)>$maximum || (isset($arguments[1]) && str_starts_with($arguments[1],'-'))) throw new RuntimeException('Unexpected argument.');
    $log=$arguments[1] ?? (getenv('FORUM_HIGH_TRAFFIC_ACCESS_LOG') ?: '/path/to/access.log');
    $cron='* * * * * cd ' . escapeshellarg($project) . ' && { ./v3 high-traffic prepare && ./v3 high-traffic run ' . escapeshellarg($log)
        . '; } >> ' . escapeshellarg($project . '/state/private/high_traffic_cron.log') . ' 2>&1';
    if ($command==='cron') {
        if ($json) echo json_encode(['cron_command'=>$cron],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n";
        else echo "High-traffic cron reference\n\nUse the same environment and user as the web app. Replace placeholder paths.\nCreate the log directory if needed, then install:\n  crontab -e\n  {$cron}\n";
        exit(0);
    }
} catch (Throwable $error) { fwrite(STDERR,$error->getMessage() . "\n" . $usage);exit(1); }

$startedAt = microtime(true);
$progress = in_array($command, ['prepare', 'refresh', 'run'], true)
    ? static function (string $message) use ($startedAt): void { fwrite(STDERR, sprintf('[%6.2fs] %s' . "\n", microtime(true) - $startedAt, $message)); }
    : null;
$progress?->__invoke('Preparing high-traffic ' . $command);
$repository=getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($project);
$database=getenv('FORUM_DATABASE_PATH') ?: LocalRepositoryBootstrap::defaultDatabasePath($project);
$public=getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: $project . '/public';
$cache=new HighTrafficCache($project,$repository,$database,$public,$progress);
$progress?->__invoke('Repository: ' . $repository . '; database: ' . $database);
$progress?->__invoke('Public root: ' . $public . '; cache: ' . $cache->root);
$mode=new HighTrafficMode($cache);
try {
    $result=match($command) {
        'prepare'=>['prepared_model'=>\ForumRewrite\Host\HighTrafficRenderInput::build(\ForumRewrite\Host\HighTrafficPublication::capture($repository), $database, $progress)],
        'run'=>(new \ForumRewrite\Host\HighTrafficRefresh($cache))->run($arguments[1] ?? (getenv('FORUM_HIGH_TRAFFIC_ACCESS_LOG') ?: '')),
        'enable'=>$mode->change(true,'Operator CLI enable'),
        'disable'=>$mode->change(false,'Operator CLI disable'),
        'restore'=>$mode->restore(),
        'status'=>$mode->status(),
        'purge'=>(function () use ($mode) { $mode->purge();return $mode->status(); })(),
        'refresh'=>(function () use ($cache,$arguments) { $html=$cache->generate($arguments[1] ?? '/',true);if ($html===null) throw new RuntimeException($cache->generationRefusalReason() ?? 'High-traffic generation returned no HTML.');return ['refreshed'=>$arguments[1] ?? '/']; })(),
        default=>throw new RuntimeException('Unsupported command.'),
    };
    if (in_array($command,['enable','status'],true)) $result['cron_command']=$cron;
    if ($json) echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n";
    else {
        foreach ($result as $key=>$value) {
            if ($key==='cron_command') continue;
            echo ucfirst(str_replace('_',' ',$key)) . ': ' . (is_bool($value) ? ($value ? 'yes' : 'no') : (is_scalar($value) ? (string)$value : json_encode($value,JSON_THROW_ON_ERROR))) . "\n";
        }
        if (isset($result['cron_command'])) echo "\nFor automatic refresh, install a once-per-minute job (not installed by this command):\n  {$cron}\nSetup: ./v3 high-traffic cron /path/to/access.log\n";
    }
} catch (Throwable $error) { fwrite(STDERR,sprintf('High-traffic %s failed after %.2f seconds: %s' . "\n", $command, microtime(true) - $startedAt, $error->getMessage()));exit(1); }
