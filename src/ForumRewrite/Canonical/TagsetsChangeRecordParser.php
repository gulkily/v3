<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * records/tagsets-changes/<Record-ID>.txt: headers, then the full tagsets text as the body
 * (empty for a reset). The detached signature covers the whole record, text included.
 */
final class TagsetsChangeRecordParser
{
    private const REQUIRED_HEADERS = ['Record-ID', 'Created-At', 'Operation', 'Operator-Identity-ID'];

    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): TagsetsChangeRecord
    {
        $record = $this->parser->parse($contents);
        foreach (self::REQUIRED_HEADERS as $header) {
            if (!isset($record->headers[$header]) || $record->headers[$header] === '') {
                throw new CanonicalRecordParseException('Missing required tagsets change header: ' . $header);
            }
        }
        if (array_diff(array_keys($record->headers), self::REQUIRED_HEADERS) !== []) {
            throw new CanonicalRecordParseException('Tagsets change record has an unknown header.');
        }
        $recordId = $record->headers['Record-ID'];
        if (preg_match('/^tagsets-change-[A-Za-z0-9][A-Za-z0-9._-]*$/', $recordId) !== 1) {
            throw new CanonicalRecordParseException('Tagsets change Record-ID is invalid.');
        }
        $createdAt = $record->headers['Created-At'];
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $createdAt, new \DateTimeZone('UTC'));
        if ($parsed === false || $parsed->format('Y-m-d\TH:i:s\Z') !== $createdAt) {
            throw new CanonicalRecordParseException('Tagsets change Created-At must be a valid RFC 3339 UTC timestamp.');
        }
        $operation = $record->headers['Operation'];
        if (!in_array($operation, [TagsetsChangeRecord::SET, TagsetsChangeRecord::RESET], true)) {
            throw new CanonicalRecordParseException('Tagsets change Operation must be set or reset.');
        }
        if ($operation === TagsetsChangeRecord::RESET && $record->body !== '') {
            throw new CanonicalRecordParseException('A tagsets reset record has no body.');
        }
        $operatorIdentityId = $record->headers['Operator-Identity-ID'];
        if (preg_match('/^openpgp:[a-f0-9]{40}$/', $operatorIdentityId) !== 1) {
            throw new CanonicalRecordParseException('Tagsets change Operator-Identity-ID must use the retained lowercase OpenPGP identity form.');
        }

        return new TagsetsChangeRecord($recordId, $createdAt, $operation, $operatorIdentityId, $record->body);
    }
}
