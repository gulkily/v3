<?php
// Manual bounded-reader probe: php tests/Support/recent_scale.php
require dirname(__DIR__, 2).'/autoload.php';
$p=new PDO('sqlite::memory:');foreach(ForumRewrite\ReadModel\ReadModelSchema::statements() as $s)$p->exec($s);
$q=$p->prepare("INSERT INTO posts(post_id,thread_id,created_at,body,board_tags_json,sequence_number) VALUES (?,?,'2026-10-30T12:00:00Z',?,'[]',1)");
$last=0;foreach([1000,10000,40000] as $size){$p->beginTransaction();for($i=$last;$i<$size;$i++)$q->execute([sprintf('p-%06d',$i),sprintf('p-%06d',$i),str_repeat('Long body ',1000)]);$p->commit();$last=$size;$start=hrtime(true);for($j=0;$j<20;$j++)$page=ForumRewrite\ReadModel\RecentPostRepository::page($p);printf("%d posts: %.2f ms/read, %d rows, %d projected body bytes, %.1f MiB PHP peak\n",$size,(hrtime(true)-$start)/1e6/20,count($page['items']),array_sum(array_map(fn($r)=>strlen($r['body']),$page['items'])),memory_get_peak_usage(true)/1048576);}
print_r($p->query("EXPLAIN QUERY PLAN SELECT post_id FROM posts WHERE is_hidden=0 AND retracted_at IS NULL ORDER BY created_at DESC,post_id DESC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN,3));
