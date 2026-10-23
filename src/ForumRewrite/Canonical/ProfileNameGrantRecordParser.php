<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * A holder's (or operator's) signed consent for one key to join one username,
 * naming the held `set-name` record it accepts. Audit only: the read model
 * does not derive names or approvals from it.
 */
final class ProfileNameGrantRecordParser
{
    /** @return array<string, string> */
    public function parse(string $contents): array
    {
        $record = (new GenericTextRecordParser())->parse($contents);
        $h = $record->headers;
        if ($record->body !== '' || ($h['Operation'] ?? '') !== 'grant-name'
            || preg_match('/^profile-name-grant-[a-f0-9]{32}$/D', $h['Record-ID'] ?? '') !== 1
            || preg_match('/^openpgp:[a-f0-9]{40}$/D', $h['Author-Identity-ID'] ?? '') !== 1
            || preg_match('/^openpgp:[a-f0-9]{40}$/D', $h['Grantee-Identity-ID'] ?? '') !== 1
            || $h['Author-Identity-ID'] === $h['Grantee-Identity-ID']
            || preg_match('/^[a-z0-9._-]{1,80}$/D', $h['Username'] ?? '') !== 1
            || preg_match('/^profile-name-[a-f0-9]{32}$/D', $h['Profile-Name-Record-ID'] ?? '') !== 1
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $h['Created-At'] ?? '') !== 1) {
            throw new CanonicalRecordParseException('Invalid profile name grant record.');
        }
        return $h;
    }
}
