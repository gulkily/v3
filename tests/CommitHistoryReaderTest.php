<?php
declare(strict_types=1);
require_once __DIR__.'/Support/RebuildHistoryFixture.php';
use ForumRewrite\ReadModel\CommitHistoryReader;

final class CommitHistoryReaderTest
{
    private function legacy(string $root): array
    {
        exec('git -C '.escapeshellarg($root).' log --format='.escapeshellarg('%H%x1f%an%x1f%ae%x1f%cd%x1f%s').' --date=format:%Y-%m-%dT%H:%M:%SZ --shortstat 2>/dev/null', $lines, $status);
        if ($status !== 0) return [];
        $rows=[]; $row=null;
        foreach ($lines as $line) {
            $parts=explode("\x1f",$line);
            if (count($parts)===5 && preg_match('/^[0-9a-f]{40}$/',$parts[0])) {
                if ($row!==null) $rows[]=$row;
                $row=array_combine(['sha','author_name','author_email','committed_at','subject'],$parts)+['file_count'=>0];
            } elseif ($row!==null && preg_match('/^\s*(\d+)\s+files?\s+changed/',$line,$match)) $row['file_count']=(int)$match[1];
        }
        if ($row!==null) $rows[]=$row;
        return $rows;
    }

    public function testTreeCountsMatchLegacyAcrossHistoryAndGitSettings(): void
    {
        $f=new RebuildHistoryFixture(0);
        try {
            foreach (['plain','space name',"tab\tname","line\nbreak",'ümlaut','empty'] as $name) file_put_contents($f->repository.'/'.$name,$name==='empty'?'':"one\ntwo\n");
            file_put_contents($f->repository.'/binary',"\0\1");
            $f->git('add','.');$f->git('commit','-qm','files');
            $f->git('mv','plain','renamed'); chmod($f->repository.'/empty',0755);
            file_put_contents($f->repository.'/binary',"\0\2");
            $f->git('add','.');$f->git('commit','-qm','rename binary mode');
            $f->git('rm','space name');$f->git('commit','-qm','delete');$f->git('commit','--allow-empty','-qm','empty');
            $f->git('checkout','-b','side');file_put_contents($f->repository.'/side','side');$f->git('add','.');$f->git('commit','-qm','side');
            $f->git('checkout','main');file_put_contents($f->repository.'/main','main');$f->git('add','.');$f->git('commit','-qm','main');$f->git('merge','--no-ff','side','-m','merge');
            foreach (['true','false','copies'] as $renames) foreach (['separate','first-parent','combined','dense-combined'] as $merge) {
                $f->git('config','diff.renames',$renames);$f->git('config','log.diffMerges',$merge);
                assertSame($this->legacy($f->repository),iterator_to_array((new CommitHistoryReader())->rows($f->repository)), $renames.'/'.$merge);
            }
            // A corrupt/missing object causes a nonzero process: no partial rows escape.
            $head=$f->git('rev-parse','HEAD');
            unlink($f->repository.'/.git/objects/'.substr($head,0,2).'/'.substr($head,2));
            assertSame([],iterator_to_array((new CommitHistoryReader())->rows($f->repository)));
        } finally {$f->remove();}
    }

    public function testEmptyAndAbsentHistoryProduceNoRows(): void
    {
        $root=sys_get_temp_dir().'/empty-history-'.bin2hex(random_bytes(6));mkdir($root);
        try {
            assertSame([],iterator_to_array((new CommitHistoryReader())->rows($root)));
            exec('git -C '.escapeshellarg($root).' init -q');
            assertSame([],iterator_to_array((new CommitHistoryReader())->rows($root)));
        } finally {
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
            foreach($iterator as $item) $item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
            rmdir($root);
        }
    }
}
