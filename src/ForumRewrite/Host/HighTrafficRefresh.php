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
        $length=strlen($suffix);$end=$length;$counts=[];
        if ($end>0 && $suffix[$end-1]==="\n") $end--;
        for ($n=0;$n<$this->cache->limits['lines'] && $end>0;$n++) {
            $separator=strrpos($suffix,"\n",$end-1-$length);
            if ($separator===false && $offset>0) break; // First sampled line is incomplete.
            $start=$separator===false ? 0 : $separator+1;
            $line=substr($suffix,$start,$end-$start);
            $end=$separator===false ? 0 : $separator;
            if (strlen($line)>$this->cache->limits['line_bytes']) continue;
            if (!preg_match('~"GET ([^ ]+) HTTP/[0-9.]+" [23][0-9]{2} ~',$line,$m)) continue;
            $route=HighTrafficRoute::canonical($m[1]) ?? HighTrafficRoute::alias($m[1]);
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
            $dirty=is_file($this->cache->root . '/dirty');
            $popular=array_values(array_filter($popular,fn (string $route): bool => $dirty || !$this->cache->fresh($route)));
            // hints() already drops browser hints for fresh pages; a write hint stays due until a render that started after the write.
            $low=$this->hints();
            $due=array_flip($low);
            $maximum=$this->cache->limits['renders'];
            $reserved=min(5,count($low),$maximum);
            $routes=array_values(array_unique(array_merge(array_slice($popular,0,$maximum-$reserved),array_slice($low,0,$reserved))));
            if ($routes!==[] && $dirty) $this->cache->locked(function (): void {
                if (is_file($this->cache->root . '/dirty') && !unlink($this->cache->root . '/dirty')) throw new RuntimeException('Cannot consume refresh marker.');
            });
            $attempts=0;
            foreach ($routes as $route) {
                $remaining=$this->cache->limits['run_seconds']-(microtime(true)-$start);
                if ($remaining<1 || $attempts>=$maximum || !$this->cache->enabled()) break;
                if (!$dirty && !isset($due[$route]) && $this->cache->fresh($route)) { $report['skipped']++;continue; }
                $attempts++;
                $this->cache->limits['render_seconds']=max(1,min(30,(int)$remaining));
                try {
                    $renderStarted=microtime(true);
                    if ($this->cache->generate($route,true)!==null) { $report['rendered']++;$this->forget($route,$renderStarted); }
                    else $report['skipped']++;
                } catch (\Throwable $e) { $this->cache->report($e);$report['failed']++;$report['status']='partial'; }
            }
            if ($dirty && $report['failed']>0) $this->cache->locked(function (): void { if (!is_file($this->cache->root . '/dirty')) $this->cache->atomic($this->cache->root . '/dirty',gmdate('c')); });
            $this->cleanup();
            $report['finished_at']=gmdate('c');
            $this->cache->atomic($this->cache->root . '/refresh-status.json',json_encode($report,JSON_THROW_ON_ERROR));
            return $report;
        } finally { flock($lock,LOCK_UN);fclose($lock); }
    }
    /**
     * Queue a cached route for the next cron run. A browser hint ($changedAt null) is
     * dropped while the page is fresh. A write hint carries the write time and stays
     * due until a render that started after it, however fresh the cached file looks.
     */
    public function hint(string $route, ?float $changedAt = null): bool
    {
        if (HighTrafficRoute::canonical($route)===null || !$this->cache->enabled()) return false;
        return $this->cache->locked(function () use ($route,$changedAt): bool {
            if (!$this->cache->enabled() || !is_file($this->cache->path($route))) return false;
            if ($changedAt===null && $this->cache->fresh($route)) return false;
            $path=$this->cache->root . '/hints/' . hash('sha256',$route) . '.json';
            $existing=is_file($path) ? json_decode((string)file_get_contents($path),true) : null;
            if (is_file($path)) {
                if ($changedAt===null) return false;
                $changedAt=max($changedAt,(float)(is_array($existing) ? ($existing['changed_at'] ?? 0) : 0));
            } elseif (count(glob($this->cache->root . '/hints/*.json') ?: []) >= $this->cache->limits['queue']) return false;
            $entry=['route'=>$route,'queued_at'=>time()];
            if ($changedAt!==null) $entry['changed_at']=$changedAt;
            $this->cache->atomic($path,json_encode($entry,JSON_THROW_ON_ERROR));
            return true;
        });
    }
    /**
     * After a post is written: render its thread page and swap it in, or queue it for
     * cron. Never throws; returns refreshed, queued, skipped (page not cached or not
     * eligible) or inactive (mode off).
     */
    public function afterPost(string $threadId): string
    {
        try {
            $route=HighTrafficRoute::canonical('/threads/' . $threadId);
            if ($route===null) return 'skipped';
            if (!$this->cache->enabled()) return 'inactive';
            if (!is_file($this->cache->path($route))) return 'skipped';
            $changedAt=microtime(true);
            try {
                if ($this->cache->generate($route,true)!==null) { $this->forget($route,$changedAt); return 'refreshed'; }
            } catch (\Throwable $error) { $this->cache->report($error); }
            // State lock contention is brief; retry the queue a few times before giving up.
            for ($attempt=0;$attempt<5;$attempt++) {
                try { return $this->hint($route,$changedAt) ? 'queued' : 'skipped'; }
                catch (\Throwable $error) { if ($attempt===4) throw $error; usleep(20000); }
            }
            return 'skipped';
        } catch (\Throwable $error) {
            try { $this->cache->report($error); } catch (\Throwable) { /* reporting is best-effort; afterPost() never throws */ }
            return 'skipped';
        }
    }
    public function hints(): array
    {
        $routes=[];
        foreach (array_slice(glob($this->cache->root . '/hints/*.json') ?: [],0,$this->cache->limits['queue']) as $file) {
            $entry=json_decode((string)file_get_contents($file),true);
            if (is_array($entry) && HighTrafficRoute::canonical($entry['route'] ?? '')!==null) {
                if (isset($entry['changed_at'])) {
                    if (!is_file($this->cache->path($entry['route']))) { @unlink($file);continue; }
                } elseif ($this->cache->fresh($entry['route'])) { @unlink($file);continue; }
                $routes[]=$entry['route'];
            }
        }
        return $routes;
    }
    /** Drop a route's hint after a render; a write hint newer than that render's start stays queued. */
    public function forget(string $route, ?float $renderStarted = null): void
    {
        $path=$this->cache->root . '/hints/' . hash('sha256',$route) . '.json';
        if (!is_file($path)) return;
        // Under the state lock, so a write hint queued during the render is never lost.
        // A busy lock leaves the hint: one extra render is harmless.
        try {
            $this->cache->locked(function () use ($path,$renderStarted): void {
                if (!is_file($path)) return;
                if ($renderStarted!==null) {
                    $entry=json_decode((string)file_get_contents($path),true);
                    if (is_array($entry) && isset($entry['changed_at']) && (float)$entry['changed_at']>$renderStarted) return;
                }
                @unlink($path);
            });
        } catch (\RuntimeException) {
            // Busy lock: the hint stays (see above).
        }
    }
    private function cleanup(): void
    {
        // Only abandoned temporary files; serving HTML is never expired by cron.
        foreach (glob($this->cache->root . '/.render-*') ?: [] as $dir) if (filemtime($dir)<time()-3600) HighTrafficCache::removeTemporaryTree($dir);
        foreach (['pages','hints'] as $part) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->cache->root . '/' . $part,\FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && str_starts_with($file->getFilename(),'.v3-') && $file->getMTime()<time()-3600) @unlink($file->getPathname());
            }
        }
    }
}
