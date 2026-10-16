<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

use RuntimeException;

/** Pinned local text; no request-time network access or translation substitution. */
final class ScriptureSource
{
    public const DIGEST = 'ccb02dea3f3e2846d473e1423f2d5022609000123926aebdd46e9b7f6ae6895b';
    private ?array $corpus = null;

    public function __construct(private readonly ?string $path = null) {}

    public function books(): array { return array_keys($this->load()['books']); }

    public function chapter(string $book, int $chapter): array
    {
        $book = $book === 'Psalm' ? 'Psalms' : $book;
        $text = $this->load()['books'][$book][(string) $chapter] ?? null;
        if (!is_array($text)) { throw new RuntimeException('Scripture source passage is unavailable.'); }
        return $text;
    }

    public function passage(string $book, int $chapter, int $start, int $end): string
    {
        if ($start < 1 || $end < $start) { throw new RuntimeException('Invalid scripture verse range.'); }
        $verses = $this->chapter($book, $chapter);
        if ($end > count($verses)) { throw new RuntimeException('Scripture source passage is unavailable.'); }
        $parts = [];
        for ($v = $start; $v <= $end; $v++) {
            if (!isset($verses[$v])) { throw new RuntimeException('Scripture source passage is unavailable.'); }
            $parts[] = $verses[$v];
        }
        return implode(' ', $parts);
    }

    private function load(): array
    {
        if ($this->corpus !== null) { return $this->corpus; }
        $raw = @file_get_contents($this->path ?? dirname(__DIR__, 3) . '/resources/scripture/kjv.json');
        if ($raw === false || hash('sha256', $raw) !== self::DIGEST) {
            throw new RuntimeException('Verified KJV source is missing or its digest does not match.');
        }
        $this->corpus = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return $this->corpus;
    }
}
