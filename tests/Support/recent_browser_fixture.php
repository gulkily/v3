<?php
require dirname(__DIR__,2).'/autoload.php';
$root=$argv[1];$project=dirname(__DIR__,2);
mkdir($root,0700,true);symlink($project.'/templates',$root.'/templates');symlink($project.'/public',$root.'/public');mkdir($root.'/sessions',0700,true);
ForumRewrite\Support\LocalRepositoryBootstrap::initializeLocalRepository($project,$root.'/repository');
for($i=0;$i<65;$i++) {
    $id=sprintf('recent-%03d',$i);
    $reply=$i<5?"Thread-ID: recent-005\nParent-ID: recent-005\n":'';
    file_put_contents($root.'/repository/records/posts/'.$id.'.txt',"Post-ID: $id\nCreated-At: 2026-10-30T12:00:00Z\nBoard-Tags: general\n$reply"."Subject: Recent conversation $i\n\n".($i===64?str_repeat('LongUnbrokenValue',100):'A readable contribution to a conversation.')."\n");
}
exec('git -C '.escapeshellarg($root.'/repository').' add records && git -C '.escapeshellarg($root.'/repository').' -c commit.gpgsign=false commit -qm "Recent fixture"',$output,$code);
if($code!==0)throw new RuntimeException('Fixture commit failed');
(new ForumRewrite\ReadModel\ReadModelBuilder($root.'/repository',$root.'/read.sqlite3',new ForumRewrite\Canonical\CanonicalRecordRepository($root.'/repository')))->rebuild();
echo json_encode(['themes'=>array_values(array_filter(ForumRewrite\SiteProfileRegistry::active()['permittedThemes'],fn($t)=>$t!=='auto'))]);
