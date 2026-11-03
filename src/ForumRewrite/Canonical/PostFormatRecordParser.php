<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class PostFormatRecordParser
{
    public const RECORD_ID_PREFIX = 'post-format-';

    /** Headers in canonical order; nothing else is accepted. */
    private const HEADERS = ['Record-ID', 'Created-At', 'Post-ID', 'Body-Format', 'Actor-Identity-ID'];

    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): PostFormatRecord
    {
        $record = $this->parser->parse($contents);
        if (array_keys($record->headers) !== self::HEADERS) {
            throw new CanonicalRecordParseException('Post-format record must carry exactly ' . implode(', ', self::HEADERS) . ' in that order.');
        }
        if ($record->body !== '') {
            throw new CanonicalRecordParseException('Post-format record must not carry a body.');
        }

        $postId = $record->headers['Post-ID'];
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $postId) !== 1) {
            throw new CanonicalRecordParseException('Post-format Post-ID is not a valid post identifier.');
        }
        $createdAt = $record->headers['Created-At'];
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/D', $createdAt, $time) !== 1) {
            throw new CanonicalRecordParseException('Post-format Created-At must be an RFC 3339 UTC timestamp.');
        }
        $stamp = $time[1] . $time[2] . $time[3] . $time[4] . $time[5] . $time[6];
        if (preg_match('/^' . preg_quote(self::RECORD_ID_PREFIX . $postId . '-' . $stamp . '-', '/') . '[a-f0-9]{8}$/D', $record->headers['Record-ID']) !== 1) {
            throw new CanonicalRecordParseException('Post-format Record-ID must be post-format-<Post-ID>-<Created-At digits>-<8 hex>.');
        }
        $format = $record->headers['Body-Format'];
        if (!in_array($format, PostRecord::FORMATS, true)) {
            throw new CanonicalRecordParseException('Post-format Body-Format must be markdown or plain.');
        }
        $actor = $record->headers['Actor-Identity-ID'];
        if (preg_match('/^openpgp:[a-f0-9]{40,64}$/D', $actor) !== 1) {
            throw new CanonicalRecordParseException('Actor-Identity-ID must use the lowercase OpenPGP identity form.');
        }

        return new PostFormatRecord($record->headers['Record-ID'], $createdAt, $postId, $format, $actor);
    }
}
