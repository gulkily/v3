<?php

declare(strict_types=1);
namespace ForumRewrite\Canonical;

use RuntimeException;

/** Serialization is not authority: entry points authenticate before local acceptance. */
final class RemovalReceipt
{
    /** Current field order: the target is named by Target-ID and Target-Created-At. */
    private const FIELDS = ['Format', 'Target-ID', 'Operation', 'Authority', 'Accepted-At', 'Actor',
        'Source-SHA256', 'Thread-ID', 'Parent-ID', 'Target-Created-At', 'Author-Identity-ID', 'Thread-Type',
        'Retraction-Class', 'Retraction-Attribution', 'Request-SHA256', 'Signature-SHA256'];

    /** Receipts written before the rename name the target Post-ID and Post-Created-At; still read as stored. */
    private const LEGACY_FIELDS = ['Format', 'Post-ID', 'Operation', 'Authority', 'Accepted-At', 'Actor',
        'Source-SHA256', 'Thread-ID', 'Parent-ID', 'Post-Created-At', 'Author-Identity-ID', 'Thread-Type',
        'Retraction-Class', 'Retraction-Attribution', 'Request-SHA256', 'Signature-SHA256'];

    /** @return list<string>|null the field list $keys matches exactly, or null */
    private static function fieldsFor(array $keys): ?array
    {
        foreach ([self::FIELDS, self::LEGACY_FIELDS] as $fields) {
            if (!array_diff($keys, $fields) && !array_diff($fields, $keys)) return $fields;
        }
        return null;
    }

    /** Writers pass the current form; the legacy form is accepted so stored receipts round-trip unchanged. */
    public static function encode(array $headers, array $labels = []): string
    {
        $fields = self::fieldsFor(array_keys($headers));
        if ($fields === null) {
            throw new RuntimeException('Invalid removal receipt fields.');
        }
        $bytes = '';
        foreach ($fields as $key) {
            $value = $headers[$key];
            if (!is_string($value) || strpbrk($value, "\r\n") !== false) throw new RuntimeException('Invalid receipt value.');
            $bytes .= $key . ': ' . $value . "\n";
        }
        $bytes .= "\n";
        ksort($labels);
        foreach ($labels as $path => $hash) $bytes .= 'Label-Path: ' . $path . "\nLabel-SHA256: " . $hash . "\n\n";
        self::decode($bytes);
        return $bytes;
    }

    /**
     * Headers come back as stored, in either form; read the target with RemovalTarget::fromHeaders().
     * @return array{headers: array, labels: array}
     */
    public static function decode(string $bytes): array
    {
        if (strlen($bytes) > 1048576) throw new RuntimeException('Removal receipt exceeds 1 MiB.');
        $text = (new GenericTextRecordParser())->parse($bytes);
        $h = $text->headers;
        if (self::fieldsFor(array_keys($h)) === null
            || $h['Format'] !== 'removal-acceptance-v1') throw new RuntimeException('Unsupported removal receipt format.');
        $target = RemovalTarget::fromHeaders($h);
        foreach ([$target['id'], $h['Thread-ID']] as $id) self::id($id);
        if ($h['Parent-ID'] !== '') self::id($h['Parent-ID']);
        foreach ([$h['Accepted-At'], (string) $target['created_at']] as $value) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d\TH:i:s\Z') !== $value) throw new RuntimeException('Invalid receipt timestamp.');
        }
        if ($h['Author-Identity-ID'] !== '' && !self::identity($h['Author-Identity-ID'])) throw new RuntimeException('Invalid receipt author.');
        if (!in_array($h['Thread-Type'], ['', 'task'], true)) throw new RuntimeException('Invalid receipt thread type.');
        if ($h['Source-SHA256'] !== '') self::hash($h['Source-SHA256']);
        if ($h['Authority'] === 'signed-retraction') {
            if ($h['Operation'] !== 'delete' || !self::identity($h['Actor']) || !self::identity($h['Author-Identity-ID'])
                || !in_array($h['Retraction-Class'], ['author', 'operator'], true)
                || !in_array($h['Retraction-Attribution'], ['author', 'operator'], true)) throw new RuntimeException('Invalid signed removal profile.');
            self::hash($h['Request-SHA256']); self::hash($h['Signature-SHA256']);
        } elseif ($h['Authority'] === 'local-operator') {
            if (!in_array($h['Operation'], ['delete', 'resolve'], true) || $h['Actor'] !== 'local-operator'
                || $h['Retraction-Class'] !== '' || $h['Retraction-Attribution'] !== ''
                || $h['Request-SHA256'] !== '' || $h['Signature-SHA256'] !== '') throw new RuntimeException('Invalid local removal profile.');
            self::hash($h['Source-SHA256']);
        } else throw new RuntimeException('Invalid removal authority.');
        $labels = [];
        if ($text->body !== '') {
            if (!str_ends_with($text->body, "\n\n")) throw new RuntimeException('Invalid label blocks.');
            foreach (explode("\n\n", substr($text->body, 0, -2)) as $block) {
                $b = (new GenericTextRecordParser())->parse($block . "\n\n")->headers;
                if (array_keys($b) !== ['Label-Path', 'Label-SHA256']
                    || !preg_match('#^records/thread-labels/[A-Za-z0-9][A-Za-z0-9._-]*\.txt$#D', $b['Label-Path'])
                    || isset($labels[$b['Label-Path']])) throw new RuntimeException('Invalid or repeated label binding.');
                self::hash($b['Label-SHA256']); $labels[$b['Label-Path']] = $b['Label-SHA256'];
            }
        }
        ksort($labels);
        return ['headers' => $h, 'labels' => $labels];
    }

    private static function id(string $id): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,200}$/D', $id)) throw new RuntimeException('Invalid removal identifier.');
    }
    private static function identity(string $id): bool { return preg_match('/^openpgp:[a-f0-9]{40}$/D', $id) === 1; }
    private static function hash(string $hash): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) throw new RuntimeException('Invalid removal hash.');
    }
}
