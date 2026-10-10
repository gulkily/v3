<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class FeatureFlagChangeRecordParser
{
    private const REQUIRED_HEADERS = [
        'Record-ID',
        'Created-At',
        'Flag-Key',
        'Value',
        'Operator-Identity-ID',
    ];

    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): FeatureFlagChangeRecord
    {
        $record = $this->parser->parse($contents);
        foreach (self::REQUIRED_HEADERS as $header) {
            if (!isset($record->headers[$header]) || $record->headers[$header] === '') {
                throw new CanonicalRecordParseException('Missing required feature-flag change header: ' . $header);
            }
        }
        if ($record->body !== '') {
            throw new CanonicalRecordParseException('Feature-flag change record body must be empty.');
        }

        $recordId = $record->headers['Record-ID'];
        if (preg_match('/^feature-flag-change-[A-Za-z0-9][A-Za-z0-9._-]*$/', $recordId) !== 1) {
            throw new CanonicalRecordParseException('Feature-flag change Record-ID is invalid.');
        }
        $createdAt = $this->parseCreatedAt($record->headers['Created-At']);
        $flagKey = $record->headers['Flag-Key'];
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $flagKey) !== 1) {
            throw new CanonicalRecordParseException('Feature-flag change Flag-Key is invalid.');
        }
        $value = match ($record->headers['Value']) {
            'true' => true,
            'false' => false,
            default => throw new CanonicalRecordParseException('Feature-flag change Value must be true or false.'),
        };
        $operatorIdentityId = $record->headers['Operator-Identity-ID'];
        if (preg_match('/^openpgp:[a-f0-9]{40}$/', $operatorIdentityId) !== 1) {
            throw new CanonicalRecordParseException('Feature-flag change Operator-Identity-ID must use the retained lowercase OpenPGP identity form.');
        }

        return new FeatureFlagChangeRecord($recordId, $createdAt, $flagKey, $value, $operatorIdentityId);
    }

    private function parseCreatedAt(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) !== 1) {
            throw new CanonicalRecordParseException('Feature-flag change Created-At must use RFC 3339 UTC format.');
        }
        try {
            $timestamp = new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new CanonicalRecordParseException('Feature-flag change Created-At must be a valid UTC timestamp.');
        }
        if ($timestamp->format('Y-m-d\\TH:i:s\\Z') !== $value) {
            throw new CanonicalRecordParseException('Feature-flag change Created-At must be a valid UTC timestamp.');
        }

        return $value;
    }
}
