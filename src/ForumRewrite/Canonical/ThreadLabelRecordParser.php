<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class ThreadLabelRecordParser
{
    private const REQUIRED_HEADERS = [
        'Record-ID',
        'Created-At',
        'Thread-ID',
        'Operation',
        'Labels',
    ];

    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): ThreadLabelRecord
    {
        $record = $this->parser->parse($contents);

        foreach (self::REQUIRED_HEADERS as $header) {
            if (!isset($record->headers[$header]) || $record->headers[$header] === '') {
                throw new CanonicalRecordParseException('Missing required thread-label header: ' . $header);
            }
        }

        $createdAt = $this->parseCreatedAt($record->headers['Created-At']);
        $operation = $record->headers['Operation'];
        if ($operation !== 'add') {
            throw new CanonicalRecordParseException('Thread-label Operation must be add in V1.');
        }

        $authorIdentityId = $record->headers['Author-Identity-ID'] ?? null;
        if ($authorIdentityId !== null && !preg_match('/^openpgp:[a-f0-9]{40}$/', $authorIdentityId)) {
            throw new CanonicalRecordParseException('Author-Identity-ID must use the retained lowercase OpenPGP identity form.');
        }

        $labels = $this->parseLabels($record->headers['Labels']);

        return new ThreadLabelRecord(
            $record->headers['Record-ID'],
            $createdAt,
            $record->headers['Thread-ID'],
            $operation,
            $labels,
            $authorIdentityId,
            $record->headers['Reason'] ?? null,
            $record->body,
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

    /**
     * @return string[]
     */
    private function parseLabels(string $value): array
    {
        $labels = array_values(array_filter(explode(' ', trim($value)), static fn (string $label): bool => $label !== ''));
        if ($labels === []) {
            throw new CanonicalRecordParseException('Labels must include at least one label token.');
        }

        $normalized = [];
        foreach ($labels as $label) {
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $label)) {
                throw new CanonicalRecordParseException('Invalid label token: ' . $label);
            }

            if (!in_array($label, $normalized, true)) {
                $normalized[] = $label;
            }
        }

        if ($normalized === []) {
            throw new CanonicalRecordParseException('Labels must include at least one label token.');
        }

        return $normalized;
    }
}
