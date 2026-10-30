<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class ThreadLabelRecordParser
{
    public function __construct(
        private readonly ReactionRecordParser $parser = new ReactionRecordParser(),
    ) {
    }

    public function parse(string $contents): ThreadLabelRecord
    {
        $r = $this->parser->parse($contents, ReactionRecordParser::THREAD);

        return new ThreadLabelRecord(
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
            $r['sourcePostId'],
            $r['sourcePostSha256'],
        );
    }
}
