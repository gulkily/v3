<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class PostReactionRecordParser
{
    public function __construct(
        private readonly ReactionRecordParser $parser = new ReactionRecordParser(),
    ) {
    }

    public function parse(string $contents): PostReactionRecord
    {
        $r = $this->parser->parse($contents, ReactionRecordParser::POST);

        return new PostReactionRecord(
            $r['recordId'],
            $r['createdAt'],
            $r['targetId'],
            $r['operation'],
            $r['tags'],
            $r['authorIdentityId'],
            $r['reason'],
            $r['body'],
            $r['actionAt'],
            $r['intentId'],
        );
    }
}
