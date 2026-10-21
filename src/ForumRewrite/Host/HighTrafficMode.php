<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

use ForumRewrite\Retraction\PublicationGuard;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use RuntimeException;

/** Policy-neutral local mode service; UI, CLI and future automation share this boundary. */
final class HighTrafficMode
{
    private const BEGIN = '# BEGIN V3 HIGH TRAFFIC';
    private const END = '# END V3 HIGH TRAFFIC';
    public function __construct(public readonly HighTrafficCache $cache) {}
    public function status(): array
    {
        $state=$this->cache->state();
        $rules=@file_get_contents($this->cache->publicRoot . '/.htaccess') ?: '';
        $effective=($state['enabled'] ?? false) && is_link($this->cache->publicRoot . '/.high-traffic')
            && realpath($this->cache->publicRoot . '/.high-traffic') === realpath($this->cache->root . '/pages')
            && str_starts_with($rules,self::BEGIN . "\n");
        return $state + ['effective_mode'=>$effective ? 'high-traffic' : 'normal'];
    }
    public function change(bool $enable, string $reason): array
    {
        return PublicationGuard::withLock($this->cache->repositoryRoot, fn () => $this->cache->locked(function () use ($enable,$reason): array {
            $path=$this->cache->publicRoot . '/.htaccess';
            if (is_link($path) || !is_file($path)) throw new RuntimeException('A regular managed .htaccess file is required.');
            $current=(string)file_get_contents($path);
            $normal=$this->normalRules($current);
            if (!str_contains($normal,'RewriteRule ^ index.php [L]')) throw new RuntimeException('Unsupported routing layout; restore the project front-controller rules first.');
            if ($enable) {
                if (FeatureFlagEvaluator::forApplication($this->cache->repositoryRoot,$this->cache->projectRoot)->isEnabled(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY)) throw new RuntimeException('Private instances cannot enable high-traffic mode.');
                if (($this->status()['effective_mode'] ?? '') === 'high-traffic') return $this->status();
                $this->cache->atomic($this->cache->root . '/normal.htaccess',$normal);
                $this->clearPages();
                $this->cache->atomic($this->cache->root . '/pages/.htaccess',"Options -Indexes\n<IfModule mod_headers.c>\nHeader set Cache-Control \"public, no-cache, must-revalidate\"\nHeader set X-Content-Type-Options \"nosniff\"\nHeader set X-V3-High-Traffic \"hit\"\n</IfModule>\n");
                $link=$this->cache->publicRoot . '/.high-traffic';
                HighTrafficSafety::detach($this->cache->publicRoot);
                if (!symlink($this->cache->root . '/pages',$link)) throw new RuntimeException('Cannot expose the high-traffic cache.');
                try { $this->cache->atomic($path,$this->rules() . $normal); }
                catch (\Throwable $error) { HighTrafficSafety::detach($this->cache->publicRoot);throw $error; }
            } else {
                HighTrafficSafety::detach($this->cache->publicRoot);
                $this->cache->atomic($path,$normal);
            }
            $state=['enabled'=>$enable,'generation'=>bin2hex(random_bytes(16)),'reason'=>substr($reason,0,200),'changed_at'=>gmdate('c')];
            try { $this->cache->atomic($this->cache->root . '/state.json',json_encode($state,JSON_THROW_ON_ERROR)); }
            catch (\Throwable $error) { HighTrafficSafety::detach($this->cache->publicRoot); throw $error; }
            return $this->status();
        }));
    }
    public function restore(): array
    {
        return PublicationGuard::withLock($this->cache->repositoryRoot, fn () => $this->cache->locked(function (): array {
            $saved=$this->cache->root . '/normal.htaccess';
            if (!is_file($saved)) throw new RuntimeException('No saved normal routing exists. Restore public/.htaccess from the application package.');
            HighTrafficSafety::detach($this->cache->publicRoot);
            $this->cache->atomic($this->cache->publicRoot . '/.htaccess',(string)file_get_contents($saved));
            $this->cache->atomic($this->cache->root . '/state.json',json_encode(['enabled'=>false,'generation'=>bin2hex(random_bytes(16)),'reason'=>'CLI routing recovery'],JSON_THROW_ON_ERROR));
            return $this->status();
        }));
    }
    public function purge(): void
    {
        PublicationGuard::withLock($this->cache->repositoryRoot, fn () => $this->cache->locked(function (): void {
            $state=$this->cache->state();$state['generation']=bin2hex(random_bytes(16));
            $this->cache->atomic($this->cache->root . '/state.json',json_encode($state,JSON_THROW_ON_ERROR));
            foreach ($this->htmlFiles() as $file) if (!unlink($file)) throw new RuntimeException('Cannot purge cached page.');
        }));
    }
    private function htmlFiles(): array
    {
        $files=[];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->cache->root . '/pages',\FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isLink()) throw new RuntimeException('Cache directory contains an unexpected symlink.');
            if ($file->isFile() && $file->getExtension()==='html') $files[]=$file->getPathname();
        }
        return $files;
    }
    private function clearPages(): void { foreach ($this->htmlFiles() as $file) if (!unlink($file)) throw new RuntimeException('Cannot reset old cached pages.'); }
    private function normalRules(string $current): string
    {
        if (!str_contains($current,self::BEGIN) && !str_contains($current,self::END)) return $current;
        if (substr_count($current,self::BEGIN)!==1 || substr_count($current,self::END)!==1 || !str_starts_with($current,self::BEGIN . "\n")) throw new RuntimeException('Damaged managed routing block; use high-traffic restore.');
        $end=strpos($current,self::END);
        if ($end===false) throw new RuntimeException('Invalid routing markers.');
        return substr($current,$end+strlen(self::END)+1);
    }
    private function rules(): string
    {
        // All targets are fixed or constrained ASCII route backreferences. END prevents front-controller re-entry.
        return self::BEGIN . <<<'RULES'

<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{THE_REQUEST} \s/+\.high-traffic(?:[/\s?]) [NC]
RewriteRule ^\.high-traffic(?:/|$) - [F,END]
RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{HTTP:Authorization} ^$
RewriteCond %{DOCUMENT_ROOT}/.high-traffic/index.html -f
RewriteRule ^$ .high-traffic/index.html [END]
RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{HTTP:Authorization} ^$
RewriteCond %{DOCUMENT_ROOT}/.high-traffic/$1/index.html -f
RewriteRule ^(threads|about)/$ .high-traffic/$1/index.html [END]
RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{HTTP:Authorization} ^$
RewriteCond %{DOCUMENT_ROOT}/.high-traffic/threads/$1/index.html -f
RewriteRule ^threads/([a-zA-Z0-9][a-zA-Z0-9_-]{0,127})/$ .high-traffic/threads/$1/index.html [END]
</IfModule>
# END V3 HIGH TRAFFIC

RULES;
    }
}
