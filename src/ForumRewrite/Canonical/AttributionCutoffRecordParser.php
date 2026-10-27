<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/** An operator moving the verified attribution cutoff; see AttributionCutoff for how it is resolved. */
final class AttributionCutoffRecordParser
{
    private const REQUIRED_HEADERS = [
        'Record-ID',
        'Created-At',
        'Cutoff-At',
        'Operator-Identity-ID',
    ];

    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): AttributionCutoffRecord
    {
        $record = $this->parser->parse($contents);
        foreach (self::REQUIRED_HEADERS as $header) {
            if (!isset($record->headers[$header]) || $record->headers[$header] === '') {
                throw new CanonicalRecordParseException('Missing required attribution cutoff header: ' . $header);
            }
        }
        if ($record->body !== '') {
            throw new CanonicalRecordParseException('Attribution cutoff record body must be empty.');
        }

        $recordId = $record->headers['Record-ID'];
        if (preg_match('/^attribution-cutoff-[A-Za-z0-9][A-Za-z0-9._-]*$/', $recordId) !== 1) {
            throw new CanonicalRecordParseException('Attribution cutoff Record-ID is invalid.');
        }
        $operatorIdentityId = $record->headers['Operator-Identity-ID'];
        if (preg_match('/^openpgp:[a-f0-9]{40}$/', $operatorIdentityId) !== 1) {
            throw new CanonicalRecordParseException('Attribution cutoff Operator-Identity-ID must use the retained lowercase OpenPGP identity form.');
        }

        return new AttributionCutoffRecord(
            $recordId,
            self::parseTimestamp($record->headers['Created-At'], 'Created-At'),
            self::parseTimestamp($record->headers['Cutoff-At'], 'Cutoff-At'),
            $operatorIdentityId,
        );
    }

    public static function parseTimestamp(string $value, string $header): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) !== 1) {
            throw new CanonicalRecordParseException('Attribution cutoff ' . $header . ' must use RFC 3339 UTC format.');
        }
        try {
            $timestamp = new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new CanonicalRecordParseException('Attribution cutoff ' . $header . ' must be a valid UTC timestamp.');
        }
        if ($timestamp->format('Y-m-d\\TH:i:s\\Z') !== $value) {
            throw new CanonicalRecordParseException('Attribution cutoff ' . $header . ' must be a valid UTC timestamp.');
        }

        return $value;
    }
}
