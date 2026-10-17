<?php

declare(strict_types=1);
namespace ForumRewrite\Railspike;

use PDO;
use RuntimeException;
use ForumRewrite\TaskQueue\TaskQueueDatabaseConfig;

final class WorkflowFactory
{
    public static function open(string $projectRoot, string $repositoryRoot, string $readModelPath): WorkflowStore
    {
        $path=TaskQueueDatabaseConfig::path($projectRoot);
        $directory=dirname($path);
        if (!is_dir($directory) && !mkdir($directory,0770,true) && !is_dir($directory)) throw new RuntimeException('Private workflow directory unavailable.');
        $actual=(realpath($directory) ?: '').'/'.basename($path);
        foreach ([$projectRoot.'/public',$repositoryRoot] as $root) {
            $root=realpath($root);
            if ($root && ($actual===$root || str_starts_with($actual,$root.'/'))) throw new RuntimeException('Workflow database must be private and outside public/content repositories.');
        }
        if (is_link($path) || realpath($path)===realpath($readModelPath) && is_file($path)) throw new RuntimeException('Workflow database cannot be the read model or a symlink.');
        return new WorkflowStore(new PDO('sqlite:'.$actual));
    }
}
