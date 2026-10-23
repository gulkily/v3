<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/** The operator signs the cutoff explicitly; acceptance is separate server evidence. */
final class KeyRevocationRecordParser
{
    public function parse(string $contents): array
    {
        $record = (new GenericTextRecordParser())->parse($contents);
        $h = $record->headers;
        $fields = ['Record-ID', 'Operation', 'Author-Identity-ID', 'Target-Identity-ID', 'Username', 'Revocation-Cutoff', 'Review-Context'];
        if ($record->body !== '' || count($h) !== count($fields) || array_diff($fields, array_keys($h)) !== []
            || ($h['Operation'] ?? '') !== 'revoke-key'
            || preg_match('/^key-revocation-[a-f0-9]{32}$/D', $h['Record-ID'] ?? '') !== 1
            || preg_match('/^openpgp:[a-f0-9]{40}$/D', $h['Author-Identity-ID'] ?? '') !== 1
            || preg_match('/^openpgp:[a-f0-9]{40}$/D', $h['Target-Identity-ID'] ?? '') !== 1
            || $h['Author-Identity-ID'] === $h['Target-Identity-ID']
            || preg_match('/^[a-z0-9._-]{1,80}$/D', $h['Username'] ?? '') !== 1
            || preg_match('/^sha256:[a-f0-9]{64}$/D', $h['Review-Context'] ?? '') !== 1) {
            throw new CanonicalRecordParseException('Invalid account key revocation record.');
        }
        self::timestamp($h['Revocation-Cutoff']);
        return $h;
    }

    public static function timestamp(string $value): void
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d\TH:i:s\Z') !== $value) {
            throw new CanonicalRecordParseException('Invalid revocation UTC timestamp.');
        }
    }
}
