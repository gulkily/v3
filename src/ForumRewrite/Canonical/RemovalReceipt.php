<?php

declare(strict_types=1);
namespace ForumRewrite\Canonical;

use RuntimeException;

/** Serialization is not authority: entry points authenticate before local acceptance. */
final class RemovalReceipt
{
    private const FIELDS = ['Format', 'Post-ID', 'Operation', 'Authority', 'Accepted-At', 'Actor',
        'Source-SHA256', 'Thread-ID', 'Parent-ID', 'Post-Created-At', 'Author-Identity-ID', 'Thread-Type',
        'Retraction-Class', 'Retraction-Attribution', 'Request-SHA256', 'Signature-SHA256'];

    public static function encode(array $headers, array $labels = []): string
    {
        if (array_diff(array_keys($headers), self::FIELDS) || array_diff(self::FIELDS, array_keys($headers))) {
            throw new RuntimeException('Invalid removal receipt fields.');
        }
        $bytes = '';
        foreach (self::FIELDS as $key) {
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

    /** @return array{headers: array, labels: array} */
    public static function decode(string $bytes): array
    {
        if (strlen($bytes) > 1048576) throw new RuntimeException('Removal receipt exceeds 1 MiB.');
        $text = (new GenericTextRecordParser())->parse($bytes);
        $h = $text->headers;
        if (array_diff(array_keys($h), self::FIELDS) || array_diff(self::FIELDS, array_keys($h))
            || $h['Format'] !== 'removal-acceptance-v1') throw new RuntimeException('Unsupported removal receipt format.');
        foreach (['Post-ID', 'Thread-ID'] as $key) self::id($h[$key]);
        if ($h['Parent-ID'] !== '') self::id($h['Parent-ID']);
        foreach (['Accepted-At', 'Post-Created-At'] as $key) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $h[$key], new \DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d\TH:i:s\Z') !== $h[$key]) throw new RuntimeException('Invalid receipt timestamp.');
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
