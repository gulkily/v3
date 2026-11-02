<?php

declare(strict_types=1);
require dirname(__DIR__,2).'/autoload.php';
use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\Offline\PublicOfflineSnapshotBuilder;
$source=$argv[1];$count=(int)$argv[2];
$pdo=new PDO('sqlite:'.$source,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach(ReadModelSchema::statements() as $sql) $pdo->exec($sql);
$post=$pdo->prepare("INSERT INTO posts(post_id,thread_id,created_at,body,board_tags_json,sequence_number) VALUES(?,?,'2026-10-30',?,'[\"general\"]',?)");
$thread=$pdo->prepare("INSERT INTO threads(root_post_id,root_post_created_at,last_activity_at,subject,body_preview,reply_count,last_post_id,board_tags_json,thread_labels_json) VALUES(?,'2026-10-30','2026-10-30',?,?,0,?,'[\"general\"]',?)");
$pdo->beginTransaction();
for($i=1;$i<=$count;$i++) {
    $id=sprintf('offline-%06d',$i);
    $post->execute([$id,$id,str_repeat('b',2048),$i]);
    $thread->execute([$id,'Thread '.$i,str_repeat('p',1024),$id,$i===1?'["pinned"]':'[]']);
}
$pdo->commit();$pdo=null;
$result=(new PublicOfflineSnapshotBuilder())->build($source,$source.'.snapshot');
$snapshot=new PDO('sqlite:'.$source.'.snapshot');
$ids=$snapshot->query('SELECT root_post_id FROM threads ORDER BY root_post_id')->fetchAll(PDO::FETCH_COLUMN);
$expected=['offline-000001'];
for($i=$count-199;$i<=$count;$i++)$expected[]=sprintf('offline-%06d',$i);
if($result['thread_count']!==201 || $result['post_count']!==201 || $ids!==$expected)throw new RuntimeException('Snapshot selection/content changed');
echo "Records: $count\nSelected: 201\nPeak bytes: ".memory_get_peak_usage(true)."\n";
