<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use InvalidArgumentException;

/** Public, bounded score supplement. Never serializes private workflow fields. */
final class FastmodScoreTransfer
{
    public const PATH = 'fastmod-scores.txt';
    public const HEADER = "Format: fastmod-scores-v1\n";
    public const MAX_BYTES = 16777216;
    public const MAX_ROWS = 50000;

    public static function encode(array $rows): string
    {
        if (count($rows) > self::MAX_ROWS) throw new InvalidArgumentException('Too many Fastmod scores.');
        usort($rows, static fn (array $a, array $b): int => [$a['post_id'], $a['content_hash'], $a['rubric_revision']] <=> [$b['post_id'], $b['content_hash'], $b['rubric_revision']]);
        $text = self::HEADER;
        foreach ($rows as $row) {
            self::validate($row);
            $text .= "\nPost: {$row['post_id']}\nContent-Hash: {$row['content_hash']}\nRubric-Revision: {$row['rubric_revision']}\nCreated-At: {$row['created_at']}\nUpdated-At: {$row['updated_at']}\n";
            $scores = $row['scores'];
            ksort($scores);
            foreach ($scores as $id => $score) $text .= 'Score-' . $id . ': ' . json_encode($score == 0 ? 0 : (float) $score, JSON_THROW_ON_ERROR) . "\n";
            if (strlen($text) > self::MAX_BYTES) throw new InvalidArgumentException('Fastmod supplement exceeds 16 MiB.');
        }
        return $text;
    }

    public static function decode(string $text): array
    {
        if (strlen($text) > self::MAX_BYTES || !str_starts_with($text, self::HEADER)) throw new InvalidArgumentException('Invalid or unsupported Fastmod supplement.');
        if ($text === self::HEADER) return [];
        $blocks = explode("\n\n", rtrim($text, "\n"), self::MAX_ROWS + 2);
        if (array_shift($blocks) !== rtrim(self::HEADER) || count($blocks) > self::MAX_ROWS) throw new InvalidArgumentException('Invalid Fastmod supplement.');
        $fields = ['Post' => 'post_id', 'Content-Hash' => 'content_hash', 'Rubric-Revision' => 'rubric_revision', 'Created-At' => 'created_at', 'Updated-At' => 'updated_at'];
        $rows = [];
        foreach ($blocks as $block) {
            $row = ['scores' => []]; $seen = [];
            $lines = explode("\n", $block, 71);
            if (count($lines) > 69) throw new InvalidArgumentException('Too many score fields.');
            foreach ($lines as $line) {
                if (strlen($line) > 256) throw new InvalidArgumentException('Score field exceeds length limit.');
                $parts = explode(': ', $line, 2);
                if (count($parts) !== 2 || isset($seen[$parts[0]])) throw new InvalidArgumentException('Invalid or duplicate score field.');
                [$key, $value] = $parts; $seen[$key] = true;
                if (isset($fields[$key])) $row[$fields[$key]] = $value;
                elseif (str_starts_with($key, 'Score-') && preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?$/D', $value)) $row['scores'][substr($key, 6)] = (float) $value;
                else throw new InvalidArgumentException('Unknown or invalid score field.');
            }
            self::validate($row);
            $rows[] = $row;
        }
        return $rows;
    }

    public static function validate(array $row): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', (string) ($row['post_id'] ?? ''))) throw new InvalidArgumentException('Invalid score post ID.');
        foreach (['content_hash', 'rubric_revision'] as $key) {
            if (!preg_match('/^[a-f0-9]{64}$/D', (string) ($row[$key] ?? ''))) throw new InvalidArgumentException('Invalid score hash/revision.');
        }
        foreach (['created_at', 'updated_at'] as $key) {
            $value = (string) ($row[$key] ?? '');
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);
            if (!$date || $date->format('Y-m-d\TH:i:sP') !== $value) throw new InvalidArgumentException('Invalid score timestamp.');
        }
        if (strtotime($row['updated_at']) < strtotime($row['created_at'])) throw new InvalidArgumentException('Invalid score timestamp order.');
        $scores = $row['scores'] ?? [];
        if (count($scores) > 64) throw new InvalidArgumentException('Too many named scores.');
        FastScoreResult::scoredSet($scores, 'import');
        foreach ($scores as $id => $value) {
            if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $id)) throw new InvalidArgumentException('Invalid score name.');
        }
    }
}
