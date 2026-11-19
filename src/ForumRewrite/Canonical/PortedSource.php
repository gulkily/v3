<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * Where a ported post came from: a comment or story on another site, copied
 * into a thread by a member (see docs/concepts/ported_thread.md).
 *
 * The four Ported-* headers travel together. Ported-From is the original's own
 * address; Ported-Author is the name it was posted under there and is never a
 * ZenMemes username; Ported-By is the member who ported it.
 */
final class PortedSource
{
    public const HEADERS = ['Ported-From', 'Ported-Author', 'Ported-Created-At', 'Ported-By'];

    /** Source sites a port may name, by host, with the label readers see. */
    public const SITES = ['news.ycombinator.com' => 'Hacker News'];

    public function __construct(
        public readonly string $from,
        public readonly string $author,
        public readonly string $createdAt,
        public readonly string $by,
    ) {
    }

    /** @param array<string, string> $headers */
    public static function fromHeaders(array $headers): ?self
    {
        $present = array_filter(self::HEADERS, static fn (string $h): bool => isset($headers[$h]));
        if ($present === []) {
            return null;
        }
        if (count($present) !== count(self::HEADERS)) {
            throw new CanonicalRecordParseException('Ported-From, Ported-Author, Ported-Created-At and Ported-By must all be present or all absent.');
        }
        $from = $headers['Ported-From'];
        if (self::siteLabel($from) === null) {
            throw new CanonicalRecordParseException('Ported-From must be a Hacker News item address like https://news.ycombinator.com/item?id=123.');
        }
        $author = $headers['Ported-Author'];
        if (preg_match('/^(?:[A-Za-z0-9_-]{1,40}|\[deleted\])$/D', $author) !== 1) {
            throw new CanonicalRecordParseException('Ported-Author must be 1 to 40 letters, digits, underscores or hyphens, or [deleted].');
        }
        $createdAt = $headers['Ported-Created-At'];
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $createdAt) !== 1) {
            throw new CanonicalRecordParseException('Ported-Created-At must use RFC 3339 UTC format like 2026-04-13T12:34:56Z.');
        }
        $by = $headers['Ported-By'];
        if (preg_match('/^openpgp:[a-f0-9]{40}$/D', $by) !== 1) {
            throw new CanonicalRecordParseException('Ported-By must be a member identity like openpgp:<40 lowercase hex digits>.');
        }
        return new self($from, $author, $createdAt, $by);
    }

    /** The byline the read model stores: "dang on Hacker News". */
    public function authorLabel(): string
    {
        return $this->author . ' on ' . self::siteLabel($this->from);
    }

    /** The source site's reader-facing name, or null when the address is not a supported item page. */
    public static function siteLabel(string $from): ?string
    {
        if (preg_match('#^https://news\.ycombinator\.com/item\?id=[1-9][0-9]{0,11}$#D', $from) === 1) {
            return self::SITES['news.ycombinator.com'];
        }
        return null;
    }

    /**
     * Read-model columns for a post, all null when it was not ported.
     *
     * @return array{ported_from:?string, ported_author:?string, ported_created_at:?string, ported_by:?string}
     */
    public static function columns(?self $source): array
    {
        return [
            'ported_from' => $source?->from,
            'ported_author' => $source?->author,
            'ported_created_at' => $source?->createdAt,
            'ported_by' => $source?->by,
        ];
    }
}
