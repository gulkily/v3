<?php
declare(strict_types=1);
require_once __DIR__.'/Support/RebuildHistoryFixture.php';
use ForumRewrite\Activity\ActivityService;
use ForumRewrite\Activity\CommitEvidenceLookup;
use ForumRewrite\ReadModel\ActivityEvidenceIndex;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Canonical\CanonicalRecordRepository;

final class CommitEvidenceLookupTest
{
    public function testSharedCommitCrossesBatchesWithOneManifestAndExactEvidence(): void
    {
        $f = new RebuildHistoryFixture(0);
        $oldTrace = getenv('GIT_TRACE');
        try {
            for ($i=0;$i<1000;$i++) file_put_contents($f->repository.'/extra-'.$i, 'unrelated');
            $f->git('add','.'); $f->git('commit','-qm','unrelated');
            $sha = $f->git('rev-list','--max-parents=0','HEAD');
            $db=$f->root.'/model.sqlite';
            (new ReadModelBuilder($f->repository,$db,new CanonicalRecordRepository($f->repository)))->rebuild();
            $pdo=new PDO('sqlite:'.$db,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
            $pdo->exec('DELETE FROM activity');
            $insert=$pdo->prepare("INSERT INTO activity(created_at,kind,label,board_tags_json,author_label,source_path,source_commit_sha) VALUES ('now','post','fixture','[]','guest','records/posts/root-001.txt',?)");
            $pdo->beginTransaction();
            for($i=0;$i<501;$i++) $insert->execute([$sha]);
            $service=new ActivityService(static fn():PDO=>$pdo,$f->repository,$db);
            $expected=$service->buildIndexedEvidence($pdo->query('SELECT * FROM activity LIMIT 1')->fetch(),[]);
            putenv('GIT_TRACE='.$f->root.'/trace');
            ActivityEvidenceIndex::refresh($pdo,$f->repository,$db);
            $pdo->commit();
            $check=$pdo->prepare('SELECT COUNT(*) FROM activity WHERE evidence_json = ?');$check->execute([$expected]);
            assertSame(501,(int)$check->fetchColumn());
            assertSame(1,substr_count(file_get_contents($f->root.'/trace'),'built-in: git diff-tree'));
            $lookup=new CommitEvidenceLookup($f->repository);
            $lookup->prepareCommits([$sha,$f->git('rev-parse','HEAD')]);
            assertSame(1,$lookup->processes());assertSame(2,$lookup->manifestReads());
            $lookup->prepareCommit($sha);
            $files=$lookup->filesForPaths($sha,['records/posts/root-001.txt','absent']);
            assertSame(['records/posts/root-001.txt'],array_keys($files));
            assertSame('added',$files['records/posts/root-001.txt']['status']);
            assertSame(1,$lookup->processes());
            $path=(new ReflectionProperty($lookup,'path'))->getValue($lookup);
            $lookup->close();assertFalse(file_exists($path));
        } finally {putenv($oldTrace===false?'GIT_TRACE':'GIT_TRACE='.$oldTrace);$f->remove();}
    }

    public function testSignedEvidenceMatchesLegacyAndNewOperationsSeeRepositoryChanges(): void
    {
        $f=new RebuildHistoryFixture(2,2);
        try {
            $path='records/thread-labels/thread-label-20260415153000-ab12cd34.txt';
            copy(__DIR__.'/fixtures/openpgp_signature/signed-label.txt',$f->repository.'/'.$path);
            copy(__DIR__.'/fixtures/openpgp_signature/signed-label.txt.asc',$f->repository.'/'.$path.'.asc');
            copy(__DIR__.'/fixtures/openpgp_signature/reaction-public-key.asc',$f->repository.'/records/public-keys/openpgp-066236E516A3B74EC51B3B423A26034600DE67FD.asc');
            $f->git('add','.');$f->git('commit','-qm','signed label and key');
            $db=$f->root.'/model.sqlite';
            $builder=new ReadModelBuilder($f->repository,$db,new CanonicalRecordRepository($f->repository));$builder->rebuild();
            $pdo=new PDO('sqlite:'.$db,null,null,[PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
            $legacy=new ActivityService(static fn():PDO=>$pdo,$f->repository,$db);
            foreach($pdo->query('SELECT * FROM activity')->fetchAll() as $row) assertSame($legacy->buildIndexedEvidence($row),$row['evidence_json']);
            $sha=$f->git('rev-parse','HEAD');
            $lookup=new CommitEvidenceLookup($f->repository);
            $expected=array_column($legacy->sourceCommitFiles($sha),null,'path');
            assertSame($expected,$lookup->filesForPaths($sha,array_keys($expected)));
            $lookup->close();
            $f->git('mv',$path,$path.'.moved');$f->git('commit','-qm','rename');
            $sha=$f->git('rev-parse','HEAD');
            $fresh=new CommitEvidenceLookup($f->repository);
            $files=$fresh->filesForPaths($sha,[$path.'.moved']);
            assertSame('renamed',$files[$path.'.moved']['status']);assertSame($path,$files[$path.'.moved']['previous_path']);
            assertSame([],$fresh->filesForPaths(str_repeat('0',40),[$path]));
            $fresh->close();
        } finally {$f->remove();}
    }

    public function testScratchWriteFailureRollsBackAndCleansUp(): void
    {
        $f=new RebuildHistoryFixture(0);
        try {
            $lookup=new CommitEvidenceLookup($f->repository);
            $path=(new ReflectionProperty($lookup,'path'))->getValue($lookup);
            $pdo=(new ReflectionProperty($lookup,'pdo'))->getValue($lookup);
            $pdo->exec('PRAGMA query_only = ON');
            $failed=false;
            try {$lookup->prepareCommit($f->git('rev-parse','HEAD'));}
            catch (PDOException $error) {$failed=true;}
            assertTrue($failed);assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM files')->fetchColumn());assertSame(0,$lookup->manifestReads());
            unset($pdo);$lookup->close();assertFalse(file_exists($path));assertFalse(file_exists($path.'-journal'));
        } finally {$f->remove();}
    }

    public function testFailedStreamCannotPublishPartialManifestAndCleansScratch(): void
    {
        $f=new RebuildHistoryFixture(0);$previous=getenv('PATH');
        try {
            $sha=$f->git('rev-parse','HEAD');mkdir($f->root.'/bin');
            file_put_contents($f->root.'/bin/git',"#!/bin/sh\nprintf '%s\\n' '$sha' 'A\trecords/posts/root-001.txt'\nexit 1\n");chmod($f->root.'/bin/git',0755);
            putenv('PATH='.$f->root.'/bin:'.$previous);
            $lookup=new CommitEvidenceLookup($f->repository);
            assertSame([],$lookup->filesForPaths($sha,['records/posts/root-001.txt']));
            assertSame(0,$lookup->manifestReads());assertSame(2,$lookup->processes());
            // Failure is memoized for this operation, avoiding a process per event.
            assertSame([],$lookup->filesForPaths($sha,['records/posts/root-001.txt']));assertSame(2,$lookup->processes());
            $path=(new ReflectionProperty($lookup,'path'))->getValue($lookup);unset($lookup);
            assertFalse(file_exists($path));assertFalse(file_exists($path.'-journal'));
        } finally {putenv('PATH='.$previous);$f->remove();}
    }
}
