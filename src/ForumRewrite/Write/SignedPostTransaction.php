<?php

declare(strict_types=1);
namespace ForumRewrite\Write;

use ForumRewrite\Support\{BoundedProcess,OperationBudget};
use RuntimeException;

/** Caller owns the repository write lock. Pending payloads are private, exact bytes. */
final class SignedPostTransaction
{
    public function __construct(private readonly string $root, private readonly ?\Closure $checkpoint = null) {}
    private function directory(): string { return $this->root . '/.git/signed-post-pending'; }
    public function git(array $args, bool $allowFailure = false): ?string
    {
        $r=BoundedProcess::run(['git','-C',$this->root,...$args],new OperationBudget(30),32*1024*1024,'signed post Git');
        if ($r['exit_code'] !== 0) {
            if ($allowFailure) return null;
            throw new RuntimeException('Signed post Git operation failed: '.trim($r['stderr']));
        }
        return $r['stdout'];
    }
    private function safePath(string $path): string
    {
        if (!preg_match('#^records/(posts|identity|public-keys|thread-labels)/[A-Za-z0-9/_.-]+$#D',$path) || str_contains($path,'..')) throw new RuntimeException('Unsafe signed post path.');
        $full=$this->root;
        foreach(explode('/',$path) as $part) { $full.='/'.$part; if(is_link($full)) throw new RuntimeException('Signed post path contains a symlink.'); }
        return $full;
    }
    private function checkpoint(string $name): void { if($this->checkpoint !== null) ($this->checkpoint)($name); }
    private function clear(string $directory): void
    {
        foreach(glob($directory.'/*') ?: [] as $file) { if(!is_file($file) || is_link($file) || !unlink($file)) throw new RuntimeException('Unable to clear pending signed post.'); }
        if(!rmdir($directory)) throw new RuntimeException('Unable to clear pending signed post directory.');
    }
    public function recover(): void
    {
        $dir=$this->directory();
        if(!is_dir($dir)) return;
        $files=[];
        foreach(file($dir.'/manifest.txt',FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if(!preg_match('/^File: ([0-9]+) ([a-f0-9]{64}) (.+)$/D',$line,$m)) throw new RuntimeException('Invalid signed post recovery manifest.');
            $this->safePath($m[3]);
            $bytes=file_get_contents($dir.'/'.$m[1].'.txt');
            if($bytes === false || hash('sha256',$bytes) !== $m[2]) throw new RuntimeException('Invalid signed post recovery payload.');
            $files[$m[3]]=$bytes;
        }
        if($files===[]) throw new RuntimeException('Empty signed post recovery manifest.');
        $committed=0;
        foreach($files as $path=>$bytes) {
            $stored=$this->git(['show','HEAD:'.$path],true);
            if($stored !== null) {
                if($stored !== $bytes) throw new RuntimeException('Signed post recovery conflicts with committed content.');
                $committed++;
            }
        }
        if($committed && $committed !== count($files)) throw new RuntimeException('Partial signed post commit requires operator recovery.');
        foreach($files as $path=>$bytes) {
            $full=$this->safePath($path);
            if(file_exists($full) && (!is_file($full) || file_get_contents($full) !== $bytes)) throw new RuntimeException('Signed post recovery conflicts with local content.');
            $staged=$this->git(['show',':'.$path],true);
            if($staged !== null && $staged !== $bytes) throw new RuntimeException('Signed post recovery conflicts with staged content.');
        }
        if(!$committed) {
            $this->git(['reset','-q','HEAD','--',...array_keys($files)]);
            foreach($files as $path=>$bytes) { $full=$this->safePath($path); if(is_file($full) && !unlink($full)) throw new RuntimeException('Unable to roll back signed post.'); }
        } else {
            // A commit is authoritative; restore a missing owned worktree file only.
            foreach($files as $path=>$bytes) if(!is_file($this->safePath($path))) file_put_contents($this->safePath($path),$bytes);
        }
        $this->clear($dir);
    }
    public function accept(array $files): string
    {
        $this->recover();
        if($files===[]) throw new RuntimeException('Empty signed post transaction.');
        if(trim($this->git(['diff','--cached','--name-only'])) !== '') throw new RuntimeException('Repository has staged changes; finish them before posting.');
        foreach($files as $path=>$bytes) {
            if(file_exists($this->safePath($path)) || $this->git(['cat-file','-e','HEAD:'.$path],true) !== null) throw new RuntimeException('Signed post target already exists.');
        }
        $dir=$this->directory(); $tmp=$dir.'-'.bin2hex(random_bytes(6));
        if(!mkdir($tmp,0700)) throw new RuntimeException('Unable to create signed post journal.');
        $manifest=''; $i=0;
        foreach($files as $path=>$bytes) {
            if(file_put_contents($tmp.'/'.$i.'.txt',$bytes)!==strlen($bytes)) throw new RuntimeException('Unable to save pending payload.');
            $manifest.='File: '.$i.' '.hash('sha256',$bytes).' '.$path."\n"; $i++;
        }
        if(file_put_contents($tmp.'/manifest.txt',$manifest)!==strlen($manifest) || !rename($tmp,$dir)) throw new RuntimeException('Unable to save signed post journal.');
        try {
            $this->checkpoint('journal');
            foreach($files as $path=>$bytes) {
                $full=$this->safePath($path); if(!is_dir(dirname($full)) && !mkdir(dirname($full),0777,true)) throw new RuntimeException('Unable to create record directory.');
                if(file_put_contents($full,$bytes)!==strlen($bytes)) throw new RuntimeException('Unable to write signed post file.');
                $this->checkpoint('file');
            }
            $this->git(['add','--',...array_keys($files)]); $this->checkpoint('staged');
            $this->git(['-c','user.name=Forum Rewrite','-c','user.email=forum-rewrite@example.invalid','commit','-m','Accept signed post bundle']);
            $this->checkpoint('committed');
            $sha=trim($this->git(['rev-parse','HEAD']));
            $this->clear($dir);
            return $sha;
        } catch(\Throwable $e) {
            $accepted=true;
            foreach($files as $path=>$bytes) if($this->git(['show','HEAD:'.$path],true)!==$bytes) $accepted=false;
            $this->recover();
            if($accepted) return trim($this->git(['rev-parse','HEAD']));
            throw $e;
        }
    }
}
