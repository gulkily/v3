<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * Tags that a thread tag unlocks on the thread's replies (Pollyanna's tagsets, plus
 * exclusive groups). The shipped rules are `resources/tagsets.txt`; an operator's site
 * record (`records/instance/tagsets.txt` in the content repository) replaces them when it
 * passes check(). A missing or unreadable file, or a malformed block, unlocks nothing.
 */
final class Tagsets
{
    public const TARGET_REPLY = 'reply';
    public const TARGET_ROOT = 'root';
    public const SOURCE_DEFAULT = 'default';
    public const SOURCE_SITE = 'site';
    public const SITE_RECORD_PATH = 'records/instance/tagsets.txt';
    private const KEYS = ['schema', 'parent', 'target', 'unlocks', 'exclusive'];

    private static ?string $repositoryRoot = null;
    /** @var array<string, array{key:string,state:array{tagsets:self,source:string,error:?string,text:string}}> */
    private static array $states = [];

    /** @param list<array{parent:string,targets:list<string>,unlocks:list<string>,exclusive:bool}> $sets */
    private function __construct(private readonly array $sets)
    {
    }

    /** The content repository whose site record forApplication() reads; set by Application and the write service. */
    public static function useRepository(?string $repositoryRoot): void
    {
        self::$repositoryRoot = $repositoryRoot;
    }

    /** Drop cached rules, after a write in the same second could leave the file time unchanged. */
    public static function forget(): void
    {
        self::$states = [];
    }

    /** The rules in force for the active repository, or the shipped file when none is set. */
    public static function forApplication(): self
    {
        return self::active()['tagsets'];
    }

    public static function forRepository(string $repositoryRoot): self
    {
        return self::stateFor($repositoryRoot)['tagsets'];
    }

    /**
     * Where the rules in force came from: the shipped default or the site record, plus the
     * text in force and, when a site record exists but fails check(), its first error.
     * @return array{tagsets:self,source:string,error:?string,text:string}
     */
    public static function active(): array
    {
        return self::stateFor(self::$repositoryRoot);
    }

    /** @return array{tagsets:self,source:string,error:?string,text:string} */
    public static function stateFor(?string $repositoryRoot): array
    {
        $shippedPath = self::shippedPath();
        $sitePath = $repositoryRoot === null ? null : rtrim($repositoryRoot, '/') . '/' . self::SITE_RECORD_PATH;
        clearstatcache(true, $shippedPath);
        if ($sitePath !== null) {
            clearstatcache(true, $sitePath);
        }
        $siteExists = $sitePath !== null && is_file($sitePath);
        $key = implode('|', [$shippedPath, (string) @filemtime($shippedPath), (string) @filesize($shippedPath),
            $siteExists ? $sitePath . '|' . @filemtime($sitePath) . '|' . @filesize($sitePath) : '-']);
        $cacheKey = $repositoryRoot ?? '';
        if ((self::$states[$cacheKey]['key'] ?? null) === $key) {
            return self::$states[$cacheKey]['state'];
        }
        $shippedText = self::readText($shippedPath);
        $state = ['tagsets' => self::parse($shippedText), 'source' => self::SOURCE_DEFAULT, 'error' => null, 'text' => $shippedText];
        if ($siteExists) {
            $siteText = self::readText((string) $sitePath);
            $errors = self::check($siteText);
            if ($errors === []) {
                $state = ['tagsets' => self::parse($siteText), 'source' => self::SOURCE_SITE, 'error' => null, 'text' => $siteText];
            } else {
                $state['error'] = $errors[0];
            }
        }
        self::$states[$cacheKey] = ['key' => $key, 'state' => $state];

        return $state;
    }

    public static function shippedPath(): string
    {
        return dirname(__DIR__, 3) . '/resources/tagsets.txt';
    }

    public static function fromFile(string $path): self
    {
        return self::parse(self::readText($path));
    }

    /**
     * Problems that stop a text from being saved as the site record, one per line, each
     * starting "Line N:". An empty list means the text is valid; empty text is valid and
     * unlocks nothing.
     * @return list<string>
     */
    public static function check(string $text): array
    {
        $errors = [];
        $groups = [];
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        $block = null;
        $finish = static function (?array $block) use (&$errors, &$groups): void {
            if ($block === null) {
                return;
            }
            $at = 'Line ' . $block['line'] . ': ';
            if (!isset($block['fields']['parent'])) {
                if (array_diff(array_keys($block['fields']), ['schema']) !== []) {
                    $errors[] = $at . 'this block has no Parent.';
                }
                return;
            }
            if (($block['fields']['unlocks'] ?? '') === '') {
                $errors[] = $at . 'this block has no Unlocks.';
                return;
            }
            if (!$block['valid']) {
                return;
            }
            $exclusive = strtolower($block['fields']['exclusive'] ?? 'no') === 'yes';
            foreach ($block['unlocks'] as $tag) {
                if (isset($groups[$tag]) && ($groups[$tag]['exclusive'] || $exclusive) && $groups[$tag]['parent'] !== $block['parent']) {
                    $errors[] = $at . 'tag ' . $tag . ' is also unlocked by #' . $groups[$tag]['parent']
                        . ' (line ' . $groups[$tag]['line'] . '); a state tag can belong to only one tagset.';
                    continue;
                }
                $groups[$tag] ??= ['parent' => $block['parent'], 'exclusive' => $exclusive, 'line' => $block['line']];
            }
        };
        foreach ($lines as $index => $raw) {
            $number = $index + 1;
            $line = trim($raw);
            if ($line === '') {
                $finish($block);
                $block = null;
                continue;
            }
            if (str_starts_with($line, '#')) {
                continue;
            }
            $block ??= ['line' => $number, 'fields' => [], 'valid' => true, 'parent' => null, 'unlocks' => []];
            $pos = strpos($line, ':');
            if ($pos === false) {
                $errors[] = 'Line ' . $number . ': expected "Key: value".';
                $block['valid'] = false;
                continue;
            }
            $key = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            if (!in_array($key, self::KEYS, true)) {
                $errors[] = 'Line ' . $number . ': unknown key "' . trim(substr($line, 0, $pos)) . '" (use Parent, Target, Unlocks or Exclusive).';
                $block['valid'] = false;
                continue;
            }
            if (isset($block['fields'][$key])) {
                $errors[] = 'Line ' . $number . ': ' . ucfirst($key) . ' appears twice in this block.';
                $block['valid'] = false;
                continue;
            }
            $block['fields'][$key] = $value;
            if ($key === 'schema' && $value !== 'tagsets-v1') {
                $errors[] = 'Line ' . $number . ': unsupported schema "' . $value . '" (expected tagsets-v1).';
            } elseif ($key === 'parent') {
                $block['parent'] = self::tag($value);
                if ($block['parent'] === null) {
                    $errors[] = 'Line ' . $number . ': "' . $value . '" is not a tag name (letters, digits, - and _).';
                    $block['valid'] = false;
                }
            } elseif ($key === 'target') {
                foreach (explode(',', $value) as $target) {
                    if (!in_array(strtolower(trim($target)), [self::TARGET_REPLY, self::TARGET_ROOT], true)) {
                        $errors[] = 'Line ' . $number . ': Target must be reply, root, or both.';
                        $block['valid'] = false;
                        break;
                    }
                }
            } elseif ($key === 'exclusive' && !in_array(strtolower($value), ['yes', 'no'], true)) {
                $errors[] = 'Line ' . $number . ': Exclusive must be yes or no.';
                $block['valid'] = false;
            } elseif ($key === 'unlocks') {
                foreach (explode(',', $value) as $raw) {
                    if (trim($raw) === '') {
                        continue;
                    }
                    $tag = self::tag($raw);
                    if ($tag === null) {
                        $errors[] = 'Line ' . $number . ': "' . trim($raw) . '" is not a tag name (letters, digits, - and _).';
                        $block['valid'] = false;
                    } elseif (\ForumRewrite\TagScore::isScoredTag($tag)) {
                        $errors[] = 'Line ' . $number . ': ' . $tag . ' is a built-in tag and cannot be unlocked.';
                        $block['valid'] = false;
                    } elseif (!in_array($tag, $block['unlocks'], true)) {
                        $block['unlocks'][] = $tag;
                    }
                }
            }
        }
        $finish($block);

        return $errors;
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
            $targets = [];
            foreach (explode(',', strtolower($fields['target'] ?? self::TARGET_REPLY)) as $rawTarget) {
                $rawTarget = trim($rawTarget);
                if (in_array($rawTarget, [self::TARGET_REPLY, self::TARGET_ROOT], true) && !in_array($rawTarget, $targets, true)) {
                    $targets[] = $rawTarget;
                }
            }
            $unlocks = [];
            foreach (explode(',', $fields['unlocks'] ?? '') as $raw) {
                $tag = self::tag($raw);
                if ($tag !== null && $tag !== $parent && !in_array($tag, $unlocks, true)) {
                    $unlocks[] = $tag;
                }
            }
            if ($parent === null || $targets === [] || $unlocks === []) {
                continue;
            }
            $sets[] = [
                'parent' => $parent,
                'targets' => $targets,
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
            if (in_array($target, $set['targets'], true) && in_array($set['parent'], $threadTags, true)) {
                foreach ($set['unlocks'] as $tag) {
                    if (!in_array($tag, $out, true)) {
                        $out[] = $tag;
                    }
                }
            }
        }

        return $out;
    }

    /** @return list<array{parent:string,targets:list<string>,unlocks:list<string>,exclusive:bool}> every tagset, in file order */
    public function all(): array
    {
        return $this->sets;
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

    /** @return list<string> the parent tags whose sets contain $tag */
    public function parentsOf(string $tag): array
    {
        return array_values(array_unique(array_map(static fn (array $set): string => $set['parent'], $this->setsContaining($tag))));
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

    /** @return list<array{parent:string,targets:list<string>,unlocks:list<string>,exclusive:bool}> */
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

    private static function readText(string $path): string
    {
        $text = is_file($path) ? @file_get_contents($path) : false;
        return is_string($text) ? $text : '';
    }
}
