<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class SiteFeatureFlagsRecordParser
{
    public const SCHEMA = 'site-feature-flags-v1';

    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): SiteFeatureFlagsRecord
    {
        $record = $this->parser->parse($contents);

        if (($record->headers['Schema'] ?? '') !== self::SCHEMA) {
            throw new CanonicalRecordParseException('Site feature flags Schema must be site-feature-flags-v1.');
        }

        $updatedAt = $record->headers['Updated-At'] ?? null;
        if ($updatedAt !== null && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $updatedAt)) {
            throw new CanonicalRecordParseException('Updated-At must use RFC 3339 UTC format like 2026-04-13T12:34:56Z.');
        }

        $values = [];
        foreach (explode("\n", $record->body) as $line) {
            if ($line === '') {
                continue;
            }

            if (preg_match('/^([A-Z][A-Z0-9_]*): (true|false)$/', $line, $matches) !== 1) {
                throw new CanonicalRecordParseException('Invalid site feature flag line: ' . $line);
            }

            $key = $matches[1];
            if (array_key_exists($key, $values)) {
                throw new CanonicalRecordParseException('Duplicate site feature flag key: ' . $key);
            }

            $values[$key] = $matches[2] === 'true';
        }

        ksort($values);

        return new SiteFeatureFlagsRecord($values, $updatedAt);
    }
}
