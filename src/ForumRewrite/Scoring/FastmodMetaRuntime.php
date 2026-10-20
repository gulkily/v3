<?php

declare(strict_types=1);
namespace ForumRewrite\Scoring;

use PDO;

/** Application retry is independent of provider work/retries. */
final class FastmodMetaRuntime
{
    public function __construct(
        public readonly FastmodMetaStore $store,
        private readonly SqliteFastScoreStore $scores,
        private readonly FastScoringConfig $config,
        private readonly bool $enabled,
        private readonly ?\Closure $flushArtifacts = null,
    ) {}

    public static function open(string $root, array $private, ?string $staticRoot = null): self
    {
        $path=FastScoreDatabaseConfig::path($root,$private);
        if (!is_dir(dirname($path)) && !mkdir(dirname($path),0700,true) && !is_dir(dirname($path))) throw new \RuntimeException('Cannot open private Fastmod state.');
        $pdo=new PDO('sqlite:'.$path);$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $store=new FastmodMetaStore($pdo);
        return new self($store,new SqliteFastScoreStore($pdo),FastScoringConfig::fromPrivateConfig($private),FastmodMetaConfig::enabled($private),
            static fn()=>FastmodMetaArtifacts::flush($store,$path,$root,$staticRoot));
    }

    public function flush(): void { if ($this->flushArtifacts !== null) ($this->flushArtifacts)(); }

    public function retain(array $work, array $result): void
    {
        if ($this->enabled && $this->config->enabled) $this->store->retainOrdinaryResult($work,$result,FastmodMetaConfig::identity($this->config));
    }

    public function recover(PDO $read, string $revision): int
    {
        $this->flush();
        if (!$this->enabled || !$this->config->enabled) return 0;
        $count=0;
        $fetch=static function(string $id) use ($read): ?array {
            $q=$read->prepare('SELECT post_id,thread_id,parent_id,subject,body FROM posts WHERE post_id=? AND is_hidden=0 AND retracted_at IS NULL');
            $q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC) ?: null;
        };
        foreach ($this->store->pending() as $receipt) {
            if ($receipt['rubric_revision'] !== $revision) continue;
            $post=$fetch($receipt['post_id']);
            if ($post === null || $post['post_id'] !== $post['thread_id']) continue;
            $hash=(new FastScoreContextFactory($fetch))->contentHashForPost($post);
            if ($hash !== $receipt['content_hash']) continue;
            $result=isset($receipt['evidence_json']) ? json_decode($receipt['evidence_json'],true,512,JSON_THROW_ON_ERROR) : null;
            if ($result !== null && $this->store->apply($receipt,$result,FastmodMetaConfig::identity($this->config))) $count++;
        }
        $this->flush();
        return $count;
    }
}
