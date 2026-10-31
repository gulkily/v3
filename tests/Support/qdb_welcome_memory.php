<?php

declare(strict_types=1);
require dirname(__DIR__,2).'/autoload.php';
use ForumRewrite\Application;
putenv('FORUM_SITE_ID=qdb');
$database=$argv[1]; $count=(int)$argv[2];
$app=new Application(dirname(__DIR__,2),dirname(__DIR__).'/fixtures/parity_minimal_v1',$database);
ob_start();$app->handle('GET','/');ob_end_clean();
$pdo=new PDO('sqlite:'.$database,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('DELETE FROM posts; DELETE FROM threads');
$post=$pdo->prepare("INSERT INTO posts(post_id,thread_id,created_at,body,board_tags_json,sequence_number) VALUES(?,?,'2026-10-30',?,'[\"general\"]',?)");
$thread=$pdo->prepare("INSERT INTO threads(root_post_id,root_post_created_at,last_activity_at,subject,body_preview,reply_count,last_post_id,board_tags_json,thread_labels_json) VALUES(?,'2026-10-30','2026-10-30',?,?,0,?,'[\"news\"]','[]')");
$pdo->beginTransaction();
for($i=1;$i<=$count;$i++) {
    $id=sprintf('thread-memory-qdb-%06d',$i);
    $post->execute([$id,$id,str_repeat('b',2048),$i]);
    $thread->execute([$id,'News '.$i,str_repeat('p',1024),$id]);
}
$pdo->commit();
ob_start();$app->handle('GET','/');$html=(string)ob_get_clean();
if(!str_contains($html,$count.' quotes so far.') || substr_count($html,'class="qdb-news-item"')!==3
    || !str_contains($html,'News '.$count.'</strong>') || str_contains($html,'News 1</strong>')
    || !str_contains($html,'href="/tags/news"')) throw new RuntimeException('Welcome count/news/order changed');
echo "Records: $count\nPeak bytes: ".memory_get_peak_usage(true)."\n";
