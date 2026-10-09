<?php

declare(strict_types=1);

namespace ForumRewrite\Qdb;

final class QdbVoteCaptionCatalog
{
    public function __construct(private readonly QdbVoteCaptionStore $store)
    {
    }

    public static function forReadModel(string $readModelDatabasePath): self
    {
        $store = QdbVoteCaptionStore::open(QdbVoteCaptionDatabaseConfig::path($readModelDatabasePath));
        $store->bootstrap();

        return new self($store);
    }

    /** @return array{caption_set_id:int, positive:array{tag:string,label:string,score:int}, negative:array{tag:string,label:string,score:int}} */
    public function selectActivePair(): array
    {
        $pairs = $this->activePairs();
        if ($pairs === []) {
            throw new \RuntimeException('No complete active QDB vote-caption pair is configured.');
        }

        return $pairs[random_int(0, count($pairs) - 1)];
    }

    /** @return list<array{caption_set_id:int, positive:array{tag:string,label:string,score:int}, negative:array{tag:string,label:string,score:int}}> */
    public function activePairs(): array
    {
        return $this->store->activePairs();
    }

    /** @return array{tag:string,label:string,score:int}|null */
    public function captionForTag(string $tag): ?array
    {
        return $this->store->caption($tag);
    }

    public function isKnownTag(string $tag): bool
    {
        return $this->captionForTag($tag) !== null;
    }

    public function isActiveTag(string $tag): bool
    {
        return $this->store->isActiveTag($tag);
    }

    public function scoreForTag(string $tag): int
    {
        return $this->captionForTag($tag)['score'] ?? 0;
    }
}
