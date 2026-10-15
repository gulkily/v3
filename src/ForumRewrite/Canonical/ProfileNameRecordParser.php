<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/** A signed, immutable name update; the OpenPGP fingerprint remains the identity. */
final class ProfileNameRecordParser
{
    /** @return array<string, string> */
    public function parse(string $contents): array
    {
        $record = (new GenericTextRecordParser())->parse($contents);
        $h = $record->headers;
        if ($record->body !== '' || ($h['Operation'] ?? '') !== 'set-name'
            || preg_match('/^profile-name-[a-f0-9]{32}$/D', $h['Record-ID'] ?? '') !== 1
            || preg_match('/^openpgp:[a-f0-9]{40}$/D', $h['Author-Identity-ID'] ?? '') !== 1
            || preg_match('/^[a-z0-9._-]{1,80}$/D', $h['Username'] ?? '') !== 1
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $h['Created-At'] ?? '') !== 1) {
            throw new CanonicalRecordParseException('Invalid profile name record.');
        }
        try {
            if ((new \DateTimeImmutable($h['Created-At']))->format('Y-m-d\TH:i:s\Z') !== $h['Created-At']) {
                throw new \RuntimeException();
            }
        } catch (\Exception) {
            throw new CanonicalRecordParseException('Invalid profile name timestamp.');
        }
        return $h;
    }
}
