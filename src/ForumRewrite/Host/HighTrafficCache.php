<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

use RuntimeException;

/** Local disposable cache. Lock order: page/slot, then short state/publication lock. */
final class HighTrafficCache
{
    public readonly string $root;
    public array $limits;
    private ?string $generationRefusal = null;
    public function __construct(
        public readonly string $projectRoot,
        public readonly string $repositoryRoot,
        public readonly string $databasePath,
        public readonly string $publicRoot,
        private readonly ?\Closure $progressReporter = null,
    ) {
        $this->root = $projectRoot . '/state/high_traffic/' . substr(hash('sha256', realpath($repositoryRoot) ?: $repositoryRoot), 0, 16);
        $defaults = ['lines'=>10000, 'read_bytes'=>16777216, 'line_bytes'=>16384, 'fresh_seconds'=>60, 'renders'=>50, 'run_seconds'=>45, 'generators'=>2, 'wait_ms'=>250, 'pages'=>1000, 'bytes'=>268435456, 'page_bytes'=>1048576, 'queue'=>1000];
        $this->limits = array_map(static function (string $key, int $default): int {
            $value = getenv('FORUM_HIGH_TRAFFIC_' . strtoupper($key));
            return $value !== false && ctype_digit($value) && (int)$value > 0 ? min((int)$value, $default * 10) : $default;
        }, array_keys($defaults), array_values($defaults));
        $this->limits = array_combine(array_keys($defaults), $this->limits);
    }
    public function initialize(): void
    {
        foreach (['', '/pages', '/locks', '/hints'] as $part) {
            if (!is_dir($this->root . $part) && !@mkdir($this->root . $part, 0755, true) && !is_dir($this->root . $part)) throw new RuntimeException('Cannot create high-traffic cache.');
        }
    }
    public function path(string $route): string { return $this->root . '/pages/' . HighTrafficRoute::file($route); }
    public function state(): array
    {
        if (!is_file($this->root . '/state.json')) return ['enabled'=>false, 'generation'=>'off'];
        $value = json_decode((string)file_get_contents($this->root . '/state.json'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new RuntimeException('Invalid high-traffic state.');
        return $value;
    }
    public function enabled(): bool { $state=$this->state(); return ($state['enabled'] ?? false) === true && ($state['profile'] ?? \ForumRewrite\SiteProfileRegistry::active()['name'])===\ForumRewrite\SiteProfileRegistry::active()['name'] && ($state['public_root'] ?? $this->publicRoot)===(realpath($this->publicRoot) ?: $this->publicRoot) && is_link($this->publicRoot . '/.high-traffic') && realpath($this->publicRoot . '/.high-traffic') === realpath($this->root . '/pages'); }
    public function fresh(string $route): bool
    {
        $path = $this->path($route); clearstatcache(true, $path);
        return is_file($path) && filemtime($path) > time() - $this->limits['fresh_seconds'];
    }
    public function locked(callable $callback): mixed
    {
        $this->initialize();
        $lock = fopen($this->root . '/locks/state.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) { if (is_resource($lock)) fclose($lock); throw new RuntimeException('High-traffic state is busy; retry.'); }
        try { return $callback(); } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
    public function atomic(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('Cannot create cache directory.');
        $tmp = tempnam($dir, '.v3-');
        if ($tmp === false) throw new RuntimeException('Cannot create temporary file.');
        try {
            if (file_put_contents($tmp,$contents) !== strlen($contents) || !chmod($tmp,0644) || !rename($tmp,$path)) throw new RuntimeException('Cannot replace cache file.');
        } finally { if (is_file($tmp)) unlink($tmp); }
    }
    /** Null means no capacity or no longer eligible; never fall through to unlimited rendering. */
    public function generate(string $route, bool $force = false): ?string
    {
        $this->generationRefusal = null;
        $this->progress('Refreshing ' . $route);
        if (HighTrafficRoute::canonical($route) === null) return $this->refuseGeneration('Route is not eligible for high-traffic caching: ' . $route);
        $this->initialize();
        $page = fopen($this->root . '/locks/' . substr(hash('sha256',$route),0,3) . '.lock','c');
        if ($page === false) throw new RuntimeException('Cannot open page lock.');
        $slot = null; $renderAssets = null; $renderWorkspace = null; $deadline = microtime(true) + $this->limits['wait_ms']/1000;
        try {
            $this->progress('Acquiring page lock (wait up to ' . $this->limits['wait_ms'] . ' ms)');
            while (!flock($page, LOCK_EX | LOCK_NB)) {
                if (!$force && is_file($this->path($route))) return file_get_contents($this->path($route)) ?: null;
                if (microtime(true) >= $deadline) return $this->refuseGeneration('Page generation is already running for ' . $route . '; retry after it finishes.');
                usleep(10000);
            }
            if (!$force && is_file($this->path($route))) return file_get_contents($this->path($route)) ?: null;
            $this->progress('Checking active mode under publication lock');
            $token = \ForumRewrite\Support\PublicationLock::withLock($this->repositoryRoot, fn () => $this->state());
            if (!$this->enabled()) return $this->refuseGeneration('High-traffic mode is inactive or its profile/public-root link does not match; run ./v3 high-traffic status.');
            $this->progress('Acquiring generator slot (' . $this->limits['generators'] . ' configured)');
            for ($i=0;$i<$this->limits['generators'];$i++) {
                $candidate = fopen($this->root . '/locks/slot-' . $i,'c');
                if ($candidate === false) throw new RuntimeException('Cannot open high-traffic generator slot ' . $i . '; check cache directory permissions.');
                if ($candidate !== false && flock($candidate,LOCK_EX | LOCK_NB)) { $slot=$candidate; break; }
                if (is_resource($candidate)) fclose($candidate);
            }
            if ($slot === null) return $this->refuseGeneration('All ' . $this->limits['generators'] . ' high-traffic generator slots are occupied; retry after current renders finish.');
            if (!is_file($this->path($route))) {
                $pageCount=0;
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root . '/pages',\FilesystemIterator::SKIP_DOTS)) as $existing) if ($existing->isFile() && $existing->getExtension()==='html') $pageCount++;
                if ($pageCount >= $this->limits['pages']) return $this->refuseGeneration('High-traffic cache page limit reached (' . $pageCount . '/' . $this->limits['pages'] . '); review cache usage and FORUM_HIGH_TRAFFIC_PAGES.');
            }
            $this->progress('Checking publication inputs (no full-record scan)');
            $context = HighTrafficPublication::captureForRender($this->repositoryRoot);
            $this->progress('Using live read model');
            $meta = \ForumRewrite\Scoring\FastmodMetaPublication::token($this->projectRoot);
            $renderWorkspace=$this->root . '/.render-' . bin2hex(random_bytes(8));
            $renderAssets=$renderWorkspace . '/assets';
            $html = $this->render($route,$renderAssets,$context);
            $html = str_replace('</head>', '<meta name="v3-refresh-after" content="' . $this->limits['fresh_seconds'] . '"><meta name="v3-cache-generation" content="' . htmlspecialchars((string)$token['generation'], ENT_QUOTES) . '"></head>', $html);
            if (strlen($html) > $this->limits['page_bytes']) throw new RuntimeException('Generated HTML exceeds page size limit.');
            $this->progress('Validating candidate for publication (' . strlen($html) . ' HTML bytes)');
            \ForumRewrite\Support\PublicationLock::withLock($this->repositoryRoot, function () use ($route,$html,$token,$context,$meta,$renderAssets,$renderWorkspace): void {
            HighTrafficPublication::validate($context);
            \ForumRewrite\Scoring\FastmodMetaPublication::publish($meta,$this->path($route),function () use ($route,$html,$token,$renderAssets): void {
            $this->locked(function () use ($route,$html,$token,$renderAssets): void {
                if (!$this->enabled() || $this->state() !== $token) throw new RuntimeException('Mode changed during generation.');
                $count=0; $bytes=0;
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root . '/pages', \FilesystemIterator::SKIP_DOTS));
                foreach ($files as $file) { if ($file->isFile() && $file->getExtension() === 'html') { $count++; $bytes += $file->getSize(); } }
                $assetBytes=0;$pendingAssets=[];
                if (is_dir($this->root . '/assets')) foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root . '/assets',\FilesystemIterator::SKIP_DOTS)) as $asset) if ($asset->isFile()) $assetBytes+=$asset->getSize();
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($renderAssets,\FilesystemIterator::SKIP_DOTS)) as $asset) {
                    if ($asset->isLink()) throw new RuntimeException('Unexpected rendered asset symlink.');
                    if (!$asset->isFile()) continue;
                    $destination=$this->root . '/assets/' . substr($asset->getPathname(),strlen($renderAssets)+1);
                    if (!is_file($destination)) { $pendingAssets[$destination]=$asset->getPathname();$assetBytes+=$asset->getSize(); }
                }
                $bytes+=$assetBytes;
                $target=$this->path($route); $old=is_file($target) ? filesize($target) : 0;
                if ((!is_file($target) && $count >= $this->limits['pages']) || $bytes-$old+strlen($html)>$this->limits['bytes']) throw new RuntimeException('High-traffic cache capacity reached; purge unused pages.');
                foreach ($pendingAssets as $destination=>$source) $this->atomic($destination,(string)file_get_contents($source));
                $this->progress('Publishing HTML to ' . $target);
                $this->atomic($target,$html);
            });
            });
            });
            $this->progress('Published ' . $route . ' (' . strlen($html) . ' bytes)');
            return $html;
        } finally { if ($renderWorkspace!==null) self::removeTemporaryTree($renderWorkspace); if (is_resource($slot)) { flock($slot,LOCK_UN); fclose($slot); } flock($page,LOCK_UN); fclose($page); }
    }
    public function generationRefusalReason(): ?string
    {
        return $this->generationRefusal;
    }
    private function refuseGeneration(string $reason): ?string
    {
        $this->generationRefusal = $reason;
        $this->progress('Generation refused: ' . $reason);
        return null;
    }
    public function render(string $route, string $assets, ?array $context = null): string
    {
        $arguments = [(getenv('FORUM_PHP_CLI') ?: PHP_BINDIR . '/php'), $this->projectRoot . '/scripts/high_traffic_render.php', $this->projectRoot, $this->repositoryRoot, $this->databasePath, $assets, $route, \ForumRewrite\SiteProfileRegistry::active()['name']];
        if ($context !== null) $arguments[] = HighTrafficPublication::identity($context);
        $this->progress('Starting isolated renderer (time limit ' . ($this->limits['render_seconds'] ?? 30) . ' seconds; HTML limit ' . $this->limits['page_bytes'] . ' bytes)');
        $proc=proc_open($arguments, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($proc)) throw new RuntimeException('Cannot start shared renderer.');
        fclose($pipes[0]); stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false);
        $body='';$errors='';$start=microtime(true);$exit=-1;
        $progressBuffer = ''; $lastHeartbeat = $start; $stage = 'worker startup';
        try {
            do {
                $body .= stream_get_contents($pipes[1]);
                $chunk = stream_get_contents($pipes[2]);
                $errors .= $chunk; $progressBuffer .= $chunk;
                while (($newline = strpos($progressBuffer, "\n")) !== false) {
                    $line = substr($progressBuffer, 0, $newline);
                    $progressBuffer = substr($progressBuffer, $newline + 1);
                    if (str_starts_with($line, 'High-traffic stage: ')) {
                        $stage = substr($line, strlen('High-traffic stage: '));
                        $this->progress('Renderer: ' . $stage);
                        $lastHeartbeat = microtime(true);
                    }
                }
                $status=proc_get_status($proc);
                $elapsed = microtime(true) - $start;
                if ($status['running'] && microtime(true) - $lastHeartbeat >= 5) {
                    $this->progress(sprintf('Renderer still running (%.1f seconds; stage: %s; HTML %d bytes)', $elapsed, $stage, strlen($body)));
                    $lastHeartbeat = microtime(true);
                }
                $limit = null;
                if (strlen($body) > $this->limits['page_bytes']) $limit = 'HTML output limit (' . strlen($body) . ' bytes; maximum ' . $this->limits['page_bytes'] . ')';
                elseif (strlen($errors) > 65536) $limit = 'stderr output limit (' . strlen($errors) . ' bytes; maximum 65536)';
                elseif ($elapsed > ($this->limits['render_seconds'] ?? 30)) $limit = 'time limit (' . ($this->limits['render_seconds'] ?? 30) . ' seconds)';
                if ($limit !== null) {
                    proc_terminate($proc, 9);
                    preg_match_all('/^High-traffic stage: ([^\r\n]+)$/m', $errors, $stages);
                    $stage = $stages[1] === [] ? 'worker startup (no progress reported)' : end($stages[1]);
                    throw new RuntimeException(sprintf('Shared render exceeded %s after %.2f seconds. Last stage: %s', $limit, $elapsed, $stage));
                }
                if (!$status['running']) { $exit=$status['exitcode']; break; }
                usleep(10000);
            } while (true);
            $body .= stream_get_contents($pipes[1]); $errors .= stream_get_contents($pipes[2]);
            if ($exit===4) throw new HighTrafficNotFound('Page not found.');
            if ($exit!==0) throw new RuntimeException('Shared render failed (exit ' . $exit . '; last stage: ' . $stage . '): ' . substr($errors,-1000));
            return $body;
        } finally { fclose($pipes[1]);fclose($pipes[2]);proc_close($proc); }
    }
    private function progress(string $message): void
    {
        $this->progressReporter?->__invoke($message);
    }
    public static function removeTemporaryTree(string $path): void
    {
        if (!is_dir($path) || is_link($path)) return;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isDir() && !$file->isLink()) @rmdir($file->getPathname());else @unlink($file->getPathname());
        }
        @rmdir($path);
    }
    public function report(\Throwable $error): void { error_log('High-traffic mode: ' . $error->getMessage()); }
}
