<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class PostRetractionRecordParser
{
    public const RECORD_ID_PREFIX = 'post-retraction-';

    private const REQUIRED_HEADERS = [
        'Record-ID',
        'Created-At',
        'Post-ID',
        'Operation',
        'Author-Identity-ID',
    ];

    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): PostRetractionRecord
    {
        $record = $this->parser->parse($contents);

        foreach (self::REQUIRED_HEADERS as $header) {
            if (!isset($record->headers[$header]) || $record->headers[$header] === '') {
                throw new CanonicalRecordParseException('Missing required post-retraction header: ' . $header);
            }
        }

        if ($record->body !== '') {
            throw new CanonicalRecordParseException('Post-retraction record must not carry a body.');
        }

        if ($record->headers['Operation'] !== 'retract') {
            throw new CanonicalRecordParseException('Post-retraction Operation must be retract in V1.');
        }

        $postId = $record->headers['Post-ID'];
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $postId) !== 1) {
            throw new CanonicalRecordParseException('Post-retraction Post-ID is not a valid post identifier.');
        }

        if ($record->headers['Record-ID'] !== self::RECORD_ID_PREFIX . $postId) {
            throw new CanonicalRecordParseException('Post-retraction Record-ID must be derived from Post-ID.');
        }

        $authorIdentityId = $record->headers['Author-Identity-ID'];
        if (preg_match('/^openpgp:[a-f0-9]{40}$/', $authorIdentityId) !== 1) {
            throw new CanonicalRecordParseException('Author-Identity-ID must use the retained lowercase OpenPGP identity form.');
        }

        return new PostRetractionRecord(
            $record->headers['Record-ID'],
            $this->parseCreatedAt($record->headers['Created-At']),
            $postId,
            $record->headers['Operation'],
            $authorIdentityId,
        );
    }

    private function parseCreatedAt(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) !== 1) {
            throw new CanonicalRecordParseException('Created-At must use RFC 3339 UTC format like 2026-04-13T12:34:56Z.');
        }

        try {
            $timestamp = new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new CanonicalRecordParseException('Created-At must be a valid UTC timestamp.');
        }

        if ($timestamp->format('Y-m-d\TH:i:s\Z') !== $value) {
            throw new CanonicalRecordParseException('Created-At must be a valid UTC timestamp.');
        }

        return $value;
    }
}
