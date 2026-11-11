<?php

declare(strict_types=1);

namespace ForumRewrite\Write;

use ForumRewrite\Canonical\RecordLimits;
use RuntimeException;

/**
 * Signed posts that verified but broke an authoring rule, kept until the
 * author sends a fixed version with the same post ID. Lives outside the
 * records tree, the read model and every export: only the author and
 * operators see it.
 *
 * One folder per post ID: `record.txt` and `record.txt.asc` hold the exact
 * signed bytes; `staged.txt` holds `Key: value` lines.
 */
final class StagedSubmissionStore
{
    public const PER_AUTHOR = 20;
    public const MAX_RECORD_BYTES = RecordLimits::ARMOR;

    public function __construct(private readonly string $directory) {}

    /**
     * Keeps a signed post, or updates the reason for one already staged by
     * the same author. An identical resubmission returns the existing item.
     *
     * @param list<array{char?: string, codepoint: string, index: int}> $chars
     * @return array<string, mixed>
     */
    public function stage(string $postId, string $author, ?string $threadId, string $record, string $signature, string $reason, string $field, array $chars, int $now): array
    {
        self::assertPostId($postId);
        if (strlen($record) > self::MAX_RECORD_BYTES || strlen($signature) > RecordLimits::ARMOR) {
            throw new RuntimeException('Signed post is too large to stage.');
        }
        $existing = $this->find($postId);
        if ($existing !== null && $existing['author_identity_id'] !== $author) {
            throw new RuntimeException('Post ID is already staged by another author.', 409);
        }
        if ($existing !== null && $existing['record'] === $record && $existing['signature'] === $signature) {
            return $existing;
        }
        if ($existing === null && count($this->listFor([$author])) >= self::PER_AUTHOR) {
            throw new RuntimeException('You have ' . self::PER_AUTHOR . ' staged posts. Fix or discard one at /staged/ first.');
        }

        $meta = "Post-ID: {$postId}\nAuthor-Identity-ID: {$author}\n"
            . ($threadId !== null ? "Thread-ID: {$threadId}\n" : '')
            . 'Staged-At: ' . gmdate('Y-m-d\TH:i:s\Z', $now) . "\n"
            . 'Reason: ' . self::line($reason) . "\n"
            . ($field !== '' ? 'Field: ' . self::line($field) . "\n" : '');
        foreach ($chars as $char) {
            $meta .= 'Character: ' . $char['codepoint'] . ' ' . (int) $char['index'] . "\n";
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create staging directory.');
        }
        $final = $this->directory . '/' . $postId;
        $temporary = $final . '.tmp-' . bin2hex(random_bytes(6));
        if (!mkdir($temporary, 0700)) throw new RuntimeException('Unable to stage post.');
        foreach (['record.txt' => $record, 'record.txt.asc' => $signature, 'staged.txt' => $meta] as $name => $bytes) {
            if (file_put_contents($temporary . '/' . $name, $bytes) !== strlen($bytes)) {
                self::remove($temporary);
                throw new RuntimeException('Unable to stage post.');
            }
        }
        if (is_dir($final)) {
            $old = $final . '.old-' . bin2hex(random_bytes(6));
            if (!rename($final, $old)) { self::remove($temporary); throw new RuntimeException('Unable to replace staged post.'); }
            if (!rename($temporary, $final)) { rename($old, $final); throw new RuntimeException('Unable to replace staged post.'); }
            self::remove($old);
        } elseif (!rename($temporary, $final)) {
            self::remove($temporary);
            throw new RuntimeException('Unable to stage post.');
        }

        return $this->find($postId) ?? throw new RuntimeException('Unable to read staged post.');
    }

    /** @return array<string, mixed>|null */
    public function find(string $postId): ?array
    {
        if (!self::validPostId($postId)) return null;
        $folder = $this->directory . '/' . $postId;
        $meta = @file_get_contents($folder . '/staged.txt');
        $record = @file_get_contents($folder . '/record.txt');
        $signature = @file_get_contents($folder . '/record.txt.asc');
        if (!is_string($meta) || !is_string($record) || !is_string($signature)) return null;

        $item = ['post_id' => $postId, 'author_identity_id' => '', 'thread_id' => null, 'staged_at' => '', 'reason' => '', 'field' => '', 'chars' => []];
        foreach (explode("\n", $meta) as $line) {
            if (!str_contains($line, ': ')) continue;
            [$key, $value] = explode(': ', $line, 2);
            match ($key) {
                'Author-Identity-ID' => $item['author_identity_id'] = $value,
                'Thread-ID' => $item['thread_id'] = $value,
                'Staged-At' => $item['staged_at'] = $value,
                'Reason' => $item['reason'] = $value,
                'Field' => $item['field'] = $value,
                'Character' => $item['chars'][] = self::character($value),
                default => null,
            };
        }
        $item['chars'] = array_values(array_filter($item['chars']));
        [$item['subject'], $item['body']] = self::preview($record);
        return $item + ['record' => $record, 'signature' => $signature];
    }

    /**
     * Staged posts by the given authors, or every staged post when `$authors` is null. Newest first.
     *
     * @param list<string>|null $authors
     * @return list<array<string, mixed>>
     */
    public function listFor(?array $authors): array
    {
        $items = [];
        foreach (glob($this->directory . '/*', GLOB_ONLYDIR) ?: [] as $folder) {
            $item = $this->find(basename($folder));
            if ($item !== null && ($authors === null || in_array($item['author_identity_id'], $authors, true))) {
                $items[] = $item;
            }
        }
        usort($items, static fn (array $a, array $b): int => [$b['staged_at'], $b['post_id']] <=> [$a['staged_at'], $a['post_id']]);
        return $items;
    }

    public function discard(string $postId): void
    {
        if (!self::validPostId($postId) || !is_dir($this->directory . '/' . $postId)) return;
        $trash = $this->directory . '/' . $postId . '.discard-' . bin2hex(random_bytes(6));
        if (!rename($this->directory . '/' . $postId, $trash)) throw new RuntimeException('Unable to discard staged post.');
        self::remove($trash);
    }

    public static function validPostId(string $postId): bool
    {
        return preg_match('/^(thread|reply)-[0-9]{14}-[a-f0-9]{32}$/D', $postId) === 1;
    }

    private static function assertPostId(string $postId): void
    {
        if (!self::validPostId($postId)) throw new RuntimeException('Invalid post ID for staging.');
    }

    /** @return array{codepoint: string, index: int}|null */
    private static function character(string $value): ?array
    {
        return preg_match('/^(U\+[0-9A-F]{4,6}) (\d+)$/D', $value, $m) === 1 ? ['codepoint' => $m[1], 'index' => (int) $m[2]] : null;
    }

    /** @return array{0: string, 1: string} subject and body, read leniently for display */
    private static function preview(string $record): array
    {
        $parts = explode("\n\n", str_replace("\r\n", "\n", $record), 2);
        preg_match('/^Subject: (.*)$/m', $parts[0], $subject);
        return [self::line($subject[1] ?? ''), $parts[1] ?? ''];
    }

    private static function line(string $value): string
    {
        return trim((string) preg_replace('/[\r\n]+/', ' ', $value));
    }

    private static function remove(string $folder): void
    {
        foreach (glob($folder . '/*') ?: [] as $file) @unlink($file);
        @rmdir($folder);
    }
}
