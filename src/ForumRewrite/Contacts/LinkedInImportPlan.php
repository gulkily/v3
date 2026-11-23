<?php

declare(strict_types=1);

namespace ForumRewrite\Contacts;

final class LinkedInImportPlan
{
    private const SOURCE_FIELDS = ['first_name', 'last_name', 'url', 'email', 'company', 'position', 'connected_on'];

    public static function build(array $saved, array $parsed): array
    {
        $savedUrls = $savedNames = $unlinkedNames = $savedUnlinked = [];
        foreach ($saved as $id => $contact) {
            $name = self::name($contact);
            $url = self::profileUrl($contact['url'] ?? '');
            $savedNames[$name] = true;
            if ($url === '') {
                $savedUnlinked[self::fingerprint($contact)] = true;
                $unlinkedNames[$name] = true;
            } else {
                $savedUrls[$url] = isset($savedUrls[$url]) ? false : $id;
            }
        }

        $candidates = $incomingNames = $urlVersions = [];
        $skipped = $parsed['skipped'];
        foreach ($parsed['contacts'] as $number => $contact) {
            $url = self::profileUrl($contact['url'] ?? '');
            if ($url === '' && ($contact['url'] ?? '') !== '') {
                $skipped[$number] = 'Unrecognized LinkedIn profile URL';
                continue;
            }
            $contact['url'] = $url;
            $signature = self::fingerprint($contact);
            $incomingNames[self::name($contact)][$url === '' ? $signature : $url] = true;
            if ($url !== '') {
                $urlVersions[$url][$signature] = true;
            }
            $candidates[$number] = [$contact, $signature];
        }

        $counts = ['added' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];
        $rows = $changes = $seen = [];
        foreach ($skipped as $number => $reason) {
            $rows[$number] = self::row($number, 'skipped', $parsed['contacts'][$number] ?? [], $reason);
            $counts['skipped']++;
        }
        foreach ($candidates as $number => [$contact, $signature]) {
            $url = $contact['url'];
            $name = self::name($contact);
            $matched = $url === '' ? null : ($savedUrls[$url] ?? null);
            $reason = '';
            $status = 'skipped';
            if ($matched === false || ($url !== '' && count($urlVersions[$url]) > 1)) {
                $reason = 'Conflicting rows use the same LinkedIn profile URL';
            } elseif (isset($seen[$signature]) || ($url === '' && isset($savedUnlinked[$signature]))) {
                $status = 'unchanged';
                $reason = 'Exact repeat';
            } elseif ($url === '' && (isset($savedNames[$name]) || count($incomingNames[$name]) > 1)
                || ($url !== '' && $matched === null && isset($unlinkedNames[$name]))) {
                $reason = 'Uncertain match for a name shared with a contact without a profile URL';
            } elseif (is_string($matched)) {
                $existing = $saved[$matched];
                $contact = array_replace($existing, array_filter($contact, static fn (string $value) => $value !== ''));
                $status = $contact === $existing ? 'unchanged' : 'updated';
                if ($status === 'updated') {
                    $changes[$matched] = $contact;
                }
            } else {
                $status = 'added';
                $id = bin2hex(random_bytes(16));
                $changes[$id] = ['id' => $id] + $contact;
            }
            if ($status !== 'skipped') {
                $seen[$signature] = true;
            }
            $counts[$status]++;
            $rows[$number] = self::row($number, $status, $contact, $reason);
        }
        ksort($rows);
        return ['rows' => array_values($rows), 'counts' => $counts, 'changes' => $changes];
    }

    private static function profileUrl(string $value): string
    {
        $parts = parse_url($value);
        if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || !preg_match('/^(?:(?:www|[a-z]{2})\.)?linkedin\.com$/iD', $parts['host'] ?? '')
            || array_intersect(['user', 'pass', 'port'], array_keys($parts))
            || !preg_match('~^/in/([^/]+)/?$~iD', $parts['path'] ?? '', $match)) {
            return '';
        }
        $slug = rawurldecode($match[1]);
        if (!preg_match('#^[\p{L}\p{N}._~-]+$#uD', $slug) || $slug === '.' || $slug === '..') {
            return '';
        }
        return 'https://www.linkedin.com/in/' . rawurlencode(mb_strtolower($slug, 'UTF-8'));
    }

    private static function name(array $contact): string
    {
        return mb_strtolower(($contact['first_name'] ?? '') . "\0" . ($contact['last_name'] ?? ''), 'UTF-8');
    }

    private static function fingerprint(array $contact): string
    {
        $values = [];
        foreach (self::SOURCE_FIELDS as $field) {
            $values[] = $contact[$field] ?? '';
        }
        return hash('sha256', implode("\0", $values));
    }

    private static function row(int $number, string $status, array $contact, string $reason): array
    {
        return [
            'row' => $number,
            'status' => $status,
            'name' => trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')),
            'company' => $contact['company'] ?? '',
            'position' => $contact['position'] ?? '',
            'email' => $contact['email'] ?? '',
            'reason' => $reason,
        ];
    }
}
