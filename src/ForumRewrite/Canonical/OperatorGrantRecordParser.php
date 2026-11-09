<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class OperatorGrantRecordParser
{
    private const REQUIRED_HEADERS = [
        'Record-ID',
        'Created-At',
        'Operator-Identity-ID',
        'Target-Identity-ID',
        'Target-Username',
    ];

    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): OperatorGrantRecord
    {
        $record = $this->parser->parse($contents);
        foreach (self::REQUIRED_HEADERS as $header) {
            if (!isset($record->headers[$header]) || $record->headers[$header] === '') {
                throw new CanonicalRecordParseException('Missing required operator grant header: ' . $header);
            }
        }
        if ($record->body !== '') {
            throw new CanonicalRecordParseException('Operator grant record body must be empty.');
        }

        $recordId = $record->headers['Record-ID'];
        if (preg_match('/^operator-grant-[A-Za-z0-9][A-Za-z0-9._-]*$/D', $recordId) !== 1) {
            throw new CanonicalRecordParseException('Operator grant Record-ID is invalid.');
        }
        $createdAt = $record->headers['Created-At'];
        $timestamp = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $createdAt) === 1
            ? \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $createdAt, new \DateTimeZone('UTC'))
            : false;
        if ($timestamp === false || $timestamp->format('Y-m-d\TH:i:s\Z') !== $createdAt) {
            throw new CanonicalRecordParseException('Operator grant Created-At must be a valid RFC 3339 UTC timestamp.');
        }
        foreach (['Operator-Identity-ID', 'Target-Identity-ID'] as $header) {
            if (preg_match('/^openpgp:[a-f0-9]{40}$/D', $record->headers[$header]) !== 1) {
                throw new CanonicalRecordParseException('Operator grant ' . $header . ' must use the retained lowercase OpenPGP identity form.');
            }
        }
        if ($record->headers['Operator-Identity-ID'] === $record->headers['Target-Identity-ID']) {
            throw new CanonicalRecordParseException('Operator grant cannot target its own operator.');
        }
        if (preg_match('/^[\x21-\x7e]+$/D', $record->headers['Target-Username']) !== 1) {
            throw new CanonicalRecordParseException('Operator grant Target-Username is invalid.');
        }

        return new OperatorGrantRecord(
            $recordId,
            $createdAt,
            $record->headers['Operator-Identity-ID'],
            $record->headers['Target-Identity-ID'],
            $record->headers['Target-Username'],
        );
    }
}
