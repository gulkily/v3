<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use ForumRewrite\Canonical\GenericTextRecordParser;
use RuntimeException;

/**
 * A signed confirm that gives one approved key private access (rule 4 of PrivateKeyAccess).
 * Signed intent plus a repository-owned acceptance receipt, modelled on KeyRetirement.
 * Spec: docs/specs/private_access_confirmation_record_v1.md
 */
final class PrivateAccessConfirmation
{
    private const HEADERS = ['Version', 'Action-ID', 'Created-At', 'Actor-Identity-ID', 'Target-Identity-ID', 'Account', 'Authority', 'Context-SHA256'];

    public static function path(string $identity): string
    {
        if (!preg_match('/^openpgp:([a-f0-9]{40})$/D', $identity, $m)) {
            throw new RuntimeException('Invalid confirmation identity.');
        }

        return 'records/private-access-confirmations/openpgp-' . $m[1] . '.txt';
    }

    public static function acceptancePath(string $identity): string
    {
        return str_replace('/private-access-confirmations/', '/private-access-confirmation-acceptances/', self::path($identity));
    }

    /** @return array<string, string> */
    public static function parse(string $bytes): array
    {
        if (strlen($bytes) > 16384) {
            throw new RuntimeException('Confirmation record is too large.');
        }
        $record = (new GenericTextRecordParser())->parse($bytes);
        $h = $record->headers;
        if (array_keys($h) !== self::HEADERS || $record->body !== '' || $h['Version'] !== '1') {
            throw new RuntimeException('Invalid confirmation record.');
        }
        self::path($h['Actor-Identity-ID']);
        self::path($h['Target-Identity-ID']);
        if ($h['Actor-Identity-ID'] === $h['Target-Identity-ID']
            || !preg_match('/^private-access-confirmation-[a-f0-9]{32}$/D', $h['Action-ID'])
            || !preg_match('/^[a-z0-9._-]{1,80}$/D', $h['Account'])
            || !in_array($h['Authority'], ['member', 'operator'], true)
            || !preg_match('/^[a-f0-9]{64}$/D', $h['Context-SHA256'])) {
            throw new RuntimeException('Invalid confirmation authority or target.');
        }
        self::date($h['Created-At']);

        return $h;
    }

    public static function acceptance(string $bytes, string $signature, string $at): string
    {
        $record = self::parse($bytes);
        self::date($at);
        if ($at < $record['Created-At']) {
            throw new RuntimeException('Confirmation acceptance predates its record.');
        }

        return "Version: 1\nAction-ID: {$record['Action-ID']}\nRecord-SHA256: " . hash('sha256', $bytes)
            . "\nSignature-SHA256: " . hash('sha256', $signature) . "\nAccepted-At: {$at}\n\n";
    }

    /** @return array<string, string>|null the accepted record for a target, or null when none exists */
    public static function read(string $root, string $identity): ?array
    {
        $path = self::path($identity);
        $receipt = self::acceptancePath($identity);
        if (!self::exists($root, $path) && !self::exists($root, $receipt) && !self::exists($root, $path . '.asc')) {
            return null;
        }
        $bytes = self::readFile($root, $path, 16384);
        $signature = self::readFile($root, $path . '.asc', 65536);
        $record = self::parse($bytes);
        if ($record['Target-Identity-ID'] !== $identity) {
            throw new RuntimeException('Confirmation target does not match its filename.');
        }
        $accepted = self::readFile($root, $receipt, 4096);
        $at = (new GenericTextRecordParser())->parse($accepted)->headers['Accepted-At'] ?? '';
        if (!hash_equals(self::acceptance($bytes, $signature, $at), $accepted)
            || ReactionSignature::status($root, $path, $bytes, $record['Actor-Identity-ID']) !== 'verified') {
            throw new RuntimeException('Invalid confirmation signature or acceptance. Restore the accepted evidence.');
        }

        return $record + ['Accepted-At' => $at, 'source_path' => $path, 'canonical_record' => $bytes];
    }

    /**
     * The key that confirmed a target, for PrivateKeyAccess. Unreadable evidence counts as no confirm.
     *
     * @return list<string>
     */
    public static function confirmersFor(string $root, string $identity): array
    {
        try {
            $record = self::read($root, $identity);
        } catch (RuntimeException) {
            return [];
        }

        return $record === null ? [] : [$record['Actor-Identity-ID']];
    }

    private static function exists(string $root, string $path): bool
    {
        return file_exists($root . '/' . $path) || is_link($root . '/' . $path);
    }

    private static function date(string $date): void
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $date, new \DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d\TH:i:s\Z') !== $date) {
            throw new RuntimeException('Invalid confirmation timestamp.');
        }
    }

    private static function readFile(string $root, string $path, int $limit): string
    {
        $full = $root;
        foreach (explode('/', $path) as $part) {
            $full .= '/' . $part;
            if (is_link($full)) {
                throw new RuntimeException('Confirmation evidence must not contain symlinks.');
            }
        }
        if (!is_file($full) || filesize($full) > $limit) {
            throw new RuntimeException('Confirmation evidence is missing or oversized. Restore the accepted record, signature and receipt.');
        }
        $bytes = file_get_contents($full, false, null, 0, $limit + 1);
        if ($bytes === false || strlen($bytes) > $limit) {
            throw new RuntimeException('Unable to read confirmation evidence.');
        }

        return $bytes;
    }
}
