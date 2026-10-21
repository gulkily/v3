<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

use RuntimeException;

final class HighTrafficRefresh
{
    public function __construct(private readonly HighTrafficCache $cache) {}
    /** Read only a bounded suffix; no log cursor or rotation management. */
    public function popular(string $log): array
    {
        $handle=@fopen($log,'rb');
        if ($handle===false) throw new RuntimeException('Cannot read access log; check the sysop-configured path and permissions.');
        try {
            $size=fstat($handle)['size']; $offset=max(0,$size-$this->cache->limits['read_bytes']);
            if (fseek($handle,$offset)!==0) throw new RuntimeException('Access log must be a seekable regular file.');
            $suffix=stream_get_contents($handle,$this->cache->limits['read_bytes']);
            if ($suffix===false) throw new RuntimeException('Cannot read recent access log lines.');
        } finally { fclose($handle); }
        $lines=explode("\n",rtrim($suffix,"\n"));
        if ($offset>0) array_shift($lines);
        $lines=array_slice($lines,-$this->cache->limits['lines']);$counts=[];
        foreach ($lines as $line) {
            if (strlen($line)>$this->cache->limits['line_bytes']) continue;
            if (!preg_match('~"GET ([^ ]+) HTTP/[0-9.]+" [23][0-9]{2} ~',$line,$m)) continue;
            $route=HighTrafficRoute::canonical($m[1]);
            if ($route===null && !str_contains($m[1],'?')) $route=HighTrafficRoute::canonical(rtrim($m[1],'/') . '/');
            if ($route!==null) $counts[$route]=($counts[$route] ?? 0)+1;
        }
        arsort($counts,SORT_NUMERIC);
        return array_slice(array_keys($counts),0,$this->cache->limits['queue']);
    }
    public function run(string $log): array
    {
        $this->cache->initialize();
        if (!$this->cache->enabled()) return ['status'=>'disabled','rendered'=>0];
        $lock=fopen($this->cache->root . '/locks/cron.lock','c');
        if ($lock===false) throw new RuntimeException('Cannot open cron lock.');
        if (!flock($lock,LOCK_EX|LOCK_NB)) { fclose($lock);return ['status'=>'busy','rendered'=>0]; }
        $start=microtime(true);$report=['status'=>'ok','rendered'=>0,'skipped'=>0,'failed'=>0];
        try {
            $popular=[];
            try { $popular=$this->popular($log); }
            catch (\Throwable $e) { $this->cache->report($e);$report['status']='partial';$report['log_error']=$e->getMessage(); }
            $popular=array_values(array_filter($popular,fn (string $route): bool => !$this->cache->fresh($route)));
            $low=array_values(array_filter($this->hints(),fn (string $route): bool => !$this->cache->fresh($route)));
            $maximum=$this->cache->limits['renders'];
            $reserved=min(5,count($low),$maximum);
            $routes=array_values(array_unique(array_merge(array_slice($popular,0,$maximum-$reserved),array_slice($low,0,$reserved))));
            $attempts=0;
            foreach ($routes as $route) {
                $remaining=$this->cache->limits['run_seconds']-(microtime(true)-$start);
                if ($remaining<1 || $attempts>=$maximum || !$this->cache->enabled()) break;
                if ($this->cache->fresh($route)) { $report['skipped']++;continue; }
                $attempts++;
                $this->cache->limits['render_seconds']=max(1,min(30,(int)$remaining));
                try {
                    if ($this->cache->generate($route,true)!==null) { $report['rendered']++;$this->forget($route); }
                    else $report['skipped']++;
                } catch (\Throwable $e) { $this->cache->report($e);$report['failed']++;$report['status']='partial'; }
            }
            $this->cleanup();
            $report['finished_at']=gmdate('c');
            $this->cache->atomic($this->cache->root . '/refresh-status.json',json_encode($report,JSON_THROW_ON_ERROR));
            return $report;
        } finally { flock($lock,LOCK_UN);fclose($lock); }
    }
    public function hint(string $route): bool
    {
        if (HighTrafficRoute::canonical($route)===null || !$this->cache->enabled()) return false;
        return $this->cache->locked(function () use ($route): bool {
            if (!$this->cache->enabled() || !is_file($this->cache->path($route)) || $this->cache->fresh($route)) return false;
            $path=$this->cache->root . '/hints/' . hash('sha256',$route) . '.json';
            if (is_file($path)) return false;
            if (count(glob($this->cache->root . '/hints/*.json') ?: []) >= $this->cache->limits['queue']) return false;
            $this->cache->atomic($path,json_encode(['route'=>$route,'queued_at'=>time()],JSON_THROW_ON_ERROR));
            return true;
        });
    }
    public function hints(): array
    {
        $routes=[];
        foreach (array_slice(glob($this->cache->root . '/hints/*.json') ?: [],0,$this->cache->limits['queue']) as $file) {
            $entry=json_decode((string)file_get_contents($file),true);
            if (is_array($entry) && HighTrafficRoute::canonical($entry['route'] ?? '')!==null) $routes[]=$entry['route'];
        }
        return $routes;
    }
    public function forget(string $route): void
    {
        $path=$this->cache->root . '/hints/' . hash('sha256',$route) . '.json';
        if (is_file($path)) @unlink($path);
    }
    private function cleanup(): void
    {
        // Only abandoned temporary files; serving HTML is never expired by cron.
        foreach (['pages','hints'] as $part) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->cache->root . '/' . $part,\FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && str_starts_with($file->getFilename(),'.v3-') && $file->getMTime()<time()-3600) @unlink($file->getPathname());
            }
        }
    }
}
