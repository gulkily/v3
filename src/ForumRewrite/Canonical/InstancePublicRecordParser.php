<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class InstancePublicRecordParser
{
    private const REQUIRED_HEADERS = [
        'Instance-Name',
        'Admin-Name',
        'Admin-Contact',
        'Retention-Policy',
        'Install-Date',
    ];

    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): InstancePublicRecord
    {
        $record = $this->parser->parse($contents);

        foreach (self::REQUIRED_HEADERS as $header) {
            if (!isset($record->headers[$header]) || $record->headers[$header] === '') {
                throw new CanonicalRecordParseException('Missing required instance header: ' . $header);
            }
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $record->headers['Install-Date'])) {
            throw new CanonicalRecordParseException('Install-Date must use YYYY-MM-DD.');
        }

        return new InstancePublicRecord($record->headers, $record->body);
    }
}
