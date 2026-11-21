<?php

declare(strict_types=1);

namespace ForumRewrite\Port;

use RuntimeException;

/**
 * A thread copied from another site, as the bookmarklet sends it.
 *
 * Plain text: a header block, then one block per item, each block separated by
 * a line that is exactly "%%". Every block is `Key: value` lines, a blank line,
 * then the item's text. A text line that starts with "%" is written with one
 * extra "%" in front, so "%%" alone always separates blocks.
 *
 *     Port-Format: hn-v1
 *     Thread-Title: My YC app: Dropbox
 *     Thread-Link: https://news.ycombinator.com/item?id=8863
 *     %%
 *     Item: 8863
 *     Author: dhouston
 *     Created-At: 2007-04-04T19:16:40Z
 *     Link: http://www.getdropbox.com/u/2/screencast.html
 *
 *     Text of the story, if any.
 *     %%
 *     Item: 8869
 *     Parent: 8863
 *     ...
 *
 * The first item is the root: the story, or the comment the user chose. Every
 * later item names a Parent that appears earlier in the file.
 */
final class PortFile
{
    public const FORMAT = 'hn-v1';
    public const MAX_ITEMS = 501;
    public const MAX_BYTES = 4_000_000;

    /** @param list<PortItem> $items */
    private function __construct(
        public readonly string $threadTitle,
        public readonly string $threadLink,
        public readonly array $items,
    ) {
    }

    public static function parse(string $contents): self
    {
        if (strlen($contents) > self::MAX_BYTES) {
            throw new RuntimeException('This thread is too large to port. Select one comment to port its replies.');
        }
        $contents = str_replace("\r\n", "\n", $contents);
        $blocks = self::splitBlocks($contents);
        if (count($blocks) < 2) {
            throw new RuntimeException('The page sent no thread.');
        }
        $header = self::parseBlock(array_shift($blocks))[0];
        if (($header['Port-Format'] ?? '') !== self::FORMAT) {
            throw new RuntimeException('This bookmarklet is out of date. Copy it again from Tools → Bookmarklets.');
        }
        $threadTitle = self::line($header['Thread-Title'] ?? '', 'Thread-Title');
        $threadLink = $header['Thread-Link'] ?? '';
        if (self::itemId($threadLink) === null) {
            throw new RuntimeException('Thread-Link must be a Hacker News item address.');
        }
        if (count($blocks) > self::MAX_ITEMS) {
            throw new RuntimeException('This thread has more than ' . (self::MAX_ITEMS - 1) . ' comments. Select one comment to port it and its replies.');
        }

        $items = [];
        $seen = [];
        foreach ($blocks as $index => $block) {
            [$fields, $text] = self::parseBlock($block);
            $id = $fields['Item'] ?? '';
            if (preg_match('/^[1-9][0-9]{0,11}$/D', $id) !== 1) {
                throw new RuntimeException('Item ' . ($index + 1) . ' has no Hacker News item number.');
            }
            if (isset($seen[$id])) {
                throw new RuntimeException('Item ' . $id . ' appears twice.');
            }
            $parent = $fields['Parent'] ?? null;
            if ($index === 0 && $parent !== null) {
                $parent = null; // The root's own parent on Hacker News is not ported.
            }
            if ($index > 0 && ($parent === null || !isset($seen[$parent]))) {
                throw new RuntimeException('Comment ' . $id . ' replies to a comment that is not in the port.');
            }
            $author = $fields['Author'] ?? '';
            if (preg_match('/^(?:[A-Za-z0-9_-]{1,40}|\[deleted\])$/D', $author) !== 1) {
                throw new RuntimeException('Comment ' . $id . ' has no valid author.');
            }
            $createdAt = $fields['Created-At'] ?? '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $createdAt) !== 1) {
                throw new RuntimeException('Comment ' . $id . ' has no valid time.');
            }
            $link = $fields['Link'] ?? '';
            if ($link !== '' && preg_match('#^https?://[^\s]+$#D', $link) !== 1) {
                throw new RuntimeException('Item ' . $id . ' has an invalid link.');
            }
            $seen[$id] = true;
            $items[] = new PortItem($id, $parent, $author, $createdAt, self::line($fields['Title'] ?? '', 'Title'), $link, trim($text));
        }

        return new self($threadTitle, $threadLink, $items);
    }

    /** Writes the format back out; the bookmarklet's JavaScript writes the same text. */
    public function toText(): string
    {
        $out = "Port-Format: " . self::FORMAT . "\nThread-Title: {$this->threadTitle}\nThread-Link: {$this->threadLink}\n";
        foreach ($this->items as $item) {
            $out .= "%%\nItem: {$item->id}\n"
                . ($item->parent !== null ? "Parent: {$item->parent}\n" : '')
                . "Author: {$item->author}\nCreated-At: {$item->createdAt}\n"
                . ($item->title !== '' ? "Title: {$item->title}\n" : '')
                . ($item->link !== '' ? "Link: {$item->link}\n" : '')
                . "\n" . preg_replace('/^%/m', '%%', $item->text) . "\n";
        }
        return $out;
    }

    public function root(): PortItem
    {
        return $this->items[0];
    }

    public function commentCount(): int
    {
        return count($this->items) - 1;
    }

    public static function itemAddress(string $id): string
    {
        return 'https://news.ycombinator.com/item?id=' . $id;
    }

    public static function itemId(string $address): ?string
    {
        return preg_match('#^https://news\.ycombinator\.com/item\?id=([1-9][0-9]{0,11})$#D', $address, $m) === 1 ? $m[1] : null;
    }

    /** @return list<string> */
    private static function splitBlocks(string $contents): array
    {
        $blocks = [];
        $current = [];
        foreach (explode("\n", rtrim($contents, "\n")) as $line) {
            if ($line === '%%') {
                $blocks[] = $current;
                $current = [];
                continue;
            }
            $current[] = str_starts_with($line, '%%') ? substr($line, 1) : $line;
        }
        $blocks[] = $current;
        return array_map(static fn (array $lines): string => implode("\n", $lines), $blocks);
    }

    /** @return array{0: array<string, string>, 1: string} */
    private static function parseBlock(string $block): array
    {
        $parts = explode("\n\n", $block, 2);
        $fields = [];
        foreach (explode("\n", $parts[0]) as $line) {
            if ($line === '') {
                continue;
            }
            $colon = strpos($line, ': ');
            if ($colon === false) {
                throw new RuntimeException('The port file has a line without "Key: value".');
            }
            $fields[substr($line, 0, $colon)] = substr($line, $colon + 2);
        }
        return [$fields, $parts[1] ?? ''];
    }

    private static function line(string $value, string $field): string
    {
        $value = trim($value);
        if (str_contains($value, "\n") || mb_strlen($value) > 300) {
            throw new RuntimeException($field . ' must be one line of at most 300 characters.');
        }
        return $value;
    }
}
