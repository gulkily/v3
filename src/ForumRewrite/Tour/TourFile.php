<?php

declare(strict_types=1);

namespace ForumRewrite\Tour;

use RuntimeException;

/**
 * Reads resources/tour.txt: the tour's features and essays.
 *
 * Schema: tour-v1. Sections are separated by a line of three dashes. Each
 * section starts with Key: value lines (Name, Route, Title), then a blank
 * line, then the essay; blank lines inside the essay separate paragraphs.
 * Lines starting with # before the first section are comments.
 */
final class TourFile
{
    private const KEYS = ['Name', 'Route', 'Title'];

    /** @return list<array{name: string, route: string, title: string, paragraphs: list<string>}> */
    public static function fromFile(string $path): array
    {
        $text = @file_get_contents($path);
        if (!is_string($text)) throw new RuntimeException('Cannot read tour file: ' . $path);
        return self::parse($text);
    }

    /** @return list<array{name: string, route: string, title: string, paragraphs: list<string>}> */
    public static function parse(string $text): array
    {
        $lines = preg_split('/\r?\n/', $text) ?: [];
        $number = 0;
        $next = static function () use (&$lines, &$number): ?string {
            if ($lines === []) return null;
            $number++;
            return array_shift($lines);
        };

        // Header: Schema line and comments.
        $schemaSeen = false;
        while (($line = $next()) !== null && trim($line) !== '---') {
            if (trim($line) === '' || str_starts_with($line, '#')) continue;
            if (trim($line) === 'Schema: tour-v1') { $schemaSeen = true; continue; }
            throw new RuntimeException("tour.txt line {$number}: expected 'Schema: tour-v1', a comment, or '---'.");
        }
        if (!$schemaSeen) throw new RuntimeException("tour.txt: missing 'Schema: tour-v1' line.");

        $sections = [];
        $names = [];
        while ($line !== null) {
            $start = $number + 1;
            $fields = [];
            while (($line = $next()) !== null && trim($line) !== '') {
                if (trim($line) === '---') break;
                if (preg_match('/^([A-Za-z]+):\s*(.*)$/', $line, $match) !== 1) {
                    throw new RuntimeException("tour.txt line {$number}: expected 'Key: value'.");
                }
                if (!in_array($match[1], self::KEYS, true)) {
                    throw new RuntimeException("tour.txt line {$number}: unknown key '{$match[1]}' (expected " . implode(', ', self::KEYS) . ').');
                }
                if (isset($fields[$match[1]])) throw new RuntimeException("tour.txt line {$number}: duplicate key '{$match[1]}'.");
                $fields[$match[1]] = trim($match[2]);
            }
            $body = [];
            while ($line !== null && trim($line) !== '---' && ($line = $next()) !== null && trim($line) !== '---') {
                $body[] = $line;
            }
            if ($fields === [] && trim(implode("\n", $body)) === '') continue;
            foreach (self::KEYS as $key) {
                if (($fields[$key] ?? '') === '') throw new RuntimeException("tour.txt section starting line {$start}: missing '{$key}'.");
            }
            if (preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $fields['Name']) !== 1) {
                throw new RuntimeException("tour.txt section starting line {$start}: Name must be lowercase letters, digits and hyphens.");
            }
            if (isset($names[$fields['Name']])) throw new RuntimeException("tour.txt section starting line {$start}: duplicate Name '{$fields['Name']}'.");
            if (!TourRoute::valid($fields['Route'])) throw new RuntimeException("tour.txt section starting line {$start}: Route must be a plain public path.");
            $names[$fields['Name']] = true;
            $paragraphs = array_values(array_filter(array_map(
                static fn (string $paragraph): string => trim(preg_replace('/\s*\n\s*/', ' ', $paragraph) ?? ''),
                preg_split('/\n\s*\n/', trim(implode("\n", $body))) ?: []
            ), static fn (string $paragraph): bool => $paragraph !== ''));
            if ($paragraphs === []) throw new RuntimeException("tour.txt section starting line {$start}: the essay is empty.");
            $sections[] = ['name' => $fields['Name'], 'route' => $fields['Route'], 'title' => $fields['Title'], 'paragraphs' => $paragraphs];
        }
        if ($sections === []) throw new RuntimeException('tour.txt: no sections.');
        return $sections;
    }
}
