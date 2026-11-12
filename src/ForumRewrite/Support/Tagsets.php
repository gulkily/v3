<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * Tags that a thread tag unlocks on the thread's replies, read from
 * `resources/tagsets.txt` (Pollyanna's tagsets, plus exclusive groups).
 * A missing or unreadable file, or a malformed block, unlocks nothing.
 */
final class Tagsets
{
    public const TARGET_REPLY = 'reply';

    /** @param list<array{parent:string,target:string,unlocks:list<string>,exclusive:bool}> $sets */
    private function __construct(private readonly array $sets)
    {
    }

    public static function forApplication(): self
    {
        static $cached = null;
        return $cached ??= self::fromFile(dirname(__DIR__, 3) . '/resources/tagsets.txt');
    }

    public static function fromFile(string $path): self
    {
        $text = is_file($path) ? @file_get_contents($path) : false;
        return self::parse(is_string($text) ? $text : '');
    }

    public static function parse(string $text): self
    {
        $sets = [];
        $blocks = preg_split('/\n\s*\n/', str_replace(["\r\n", "\r"], "\n", $text)) ?: [];
        foreach ($blocks as $block) {
            $fields = [];
            foreach (explode("\n", $block) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $pos = strpos($line, ':');
                if ($pos === false) {
                    $fields = null;
                    break;
                }
                $fields[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
            }
            if ($fields === null || !isset($fields['parent'])) {
                continue;
            }
            $parent = self::tag($fields['parent']);
            $target = strtolower($fields['target'] ?? self::TARGET_REPLY);
            $unlocks = [];
            foreach (explode(',', $fields['unlocks'] ?? '') as $raw) {
                $tag = self::tag($raw);
                if ($tag !== null && $tag !== $parent && !in_array($tag, $unlocks, true)) {
                    $unlocks[] = $tag;
                }
            }
            if ($parent === null || $target !== self::TARGET_REPLY || $unlocks === []) {
                continue;
            }
            $sets[] = [
                'parent' => $parent,
                'target' => $target,
                'unlocks' => $unlocks,
                'exclusive' => strtolower($fields['exclusive'] ?? 'no') === 'yes',
            ];
        }

        return new self($sets);
    }

    /**
     * Tags offered on a reply whose thread carries $threadTags.
     * @param list<string> $threadTags
     * @return list<string>
     */
    public function unlockedFor(array $threadTags, string $target = self::TARGET_REPLY): array
    {
        $threadTags = array_map('strtolower', $threadTags);
        $out = [];
        foreach ($this->sets as $set) {
            if ($set['target'] === $target && in_array($set['parent'], $threadTags, true)) {
                foreach ($set['unlocks'] as $tag) {
                    if (!in_array($tag, $out, true)) {
                        $out[] = $tag;
                    }
                }
            }
        }

        return $out;
    }

    /** True when some tagset can unlock $tag. */
    public function isUnlockable(string $tag): bool
    {
        return $this->setsContaining($tag) !== [];
    }

    /**
     * The exclusive group $tag belongs to, or null when it is not in one.
     * @return list<string>|null
     */
    public function exclusiveGroupOf(string $tag): ?array
    {
        foreach ($this->setsContaining($tag) as $set) {
            if ($set['exclusive']) {
                return $set['unlocks'];
            }
        }

        return null;
    }

    /** @return list<string> every tag that belongs to an exclusive group */
    public function exclusiveTags(): array
    {
        $out = [];
        foreach ($this->sets as $set) {
            if ($set['exclusive']) {
                foreach ($set['unlocks'] as $tag) {
                    $out[] = $tag;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<array{parent:string,target:string,unlocks:list<string>,exclusive:bool}> */
    private function setsContaining(string $tag): array
    {
        $tag = strtolower(trim($tag));
        return array_values(array_filter($this->sets, static fn (array $set): bool => in_array($tag, $set['unlocks'], true)));
    }

    private static function tag(string $raw): ?string
    {
        $tag = strtolower(ltrim(trim($raw), '#'));
        return preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', $tag) === 1 ? $tag : null;
    }
}
