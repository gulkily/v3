<?php

declare(strict_types=1);
namespace ForumRewrite\Scoring;

use PDO;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\PrivateConfig;

/** Lock order: existing retraction publication lock, then this classification lock. */
final class FastmodMetaPublication
{
    public static function token(string $root): array
    {
        $path=FastScoreDatabaseConfig::path($root,PrivateConfig::load($root));
        return ['path'=>$path,'generation'=>self::generation($path)];
    }
    public static function generation(string $path): string
    {
        if (!is_file($path)) return 'none';
        $p=new PDO('sqlite:file:'.$path.'?mode=ro');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        if (!$p->query("SELECT 1 FROM sqlite_master WHERE name='fastmod_meta_generation'")->fetchColumn()) return 'none';
        $row=$p->query('SELECT generation,epoch FROM fastmod_meta_generation WHERE singleton=1')->fetch(PDO::FETCH_ASSOC);
        return (int)$row['generation'] === 0 ? 'none' : hash('sha256',$row['epoch'].':'.$row['generation']);
    }
    public static function publish(array $token, string $path, callable $publish): void
    {
        (new ExecutionLock($token['path'].'.publication.lock'))->withExclusiveLock(function () use ($token,$path,$publish): void {
            if (self::generation($token['path']) !== $token['generation']) throw new \RuntimeException('Fastmod tags changed during artifact build; retry.');
            $publish();
            if (file_put_contents($path.'.fastmod-generation',$token['generation']) === false) throw new \RuntimeException('Cannot record Fastmod artifact generation.');
        });
    }
    public static function assertArtifact(array $token,string $path): void
    {
        $stamp=@file_get_contents($path.'.fastmod-generation');
        if (($stamp === false && $token['generation'] !== 'none') || ($stamp !== false && trim($stamp) !== $token['generation'])) {
            throw new \RuntimeException('Static release has older Fastmod tags; rebuild before activation.');
        }
    }
}
