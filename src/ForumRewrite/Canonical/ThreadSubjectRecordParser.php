<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class ThreadSubjectRecordParser
{
    private const REQUIRED_HEADERS = [
        'Record-ID',
        'Created-At',
        'Thread-ID',
        'Operation',
        'Subject',
    ];

    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): ThreadSubjectRecord
    {
        $record = $this->parser->parse($contents);

        foreach (self::REQUIRED_HEADERS as $header) {
            if (!isset($record->headers[$header]) || $record->headers[$header] === '') {
                throw new CanonicalRecordParseException('Missing required thread-subject header: ' . $header);
            }
        }

        $createdAt = $this->parseCreatedAt($record->headers['Created-At']);
        $operation = $record->headers['Operation'];
        if ($operation !== 'set') {
            throw new CanonicalRecordParseException('Thread-subject Operation must be set in V1.');
        }

        $authorIdentityId = $record->headers['Author-Identity-ID'] ?? null;
        if ($authorIdentityId !== null && !preg_match('/^openpgp:[a-f0-9]{40}$/', $authorIdentityId)) {
            throw new CanonicalRecordParseException('Author-Identity-ID must use the retained lowercase OpenPGP identity form.');
        }

        return new ThreadSubjectRecord(
            $record->headers['Record-ID'],
            $createdAt,
            $record->headers['Thread-ID'],
            $operation,
            $record->headers['Subject'],
            $authorIdentityId,
            $record->headers['Reason'] ?? null,
            $record->body,
            $this->optionalActionAt($record->headers['Action-At'] ?? null),
            $record->headers['Intent-ID'] ?? null,
        );
    }

    private function optionalActionAt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z$/', $value) !== 1) {
            throw new CanonicalRecordParseException('Action-At must use RFC 3339 UTC format.');
        }

        return $value;
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
