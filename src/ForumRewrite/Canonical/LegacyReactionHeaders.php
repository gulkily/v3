<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * @deprecated Legacy thread-label (Thread-ID/Labels) and post-reaction
 * (Post-ID/Tags) header sets. Records in these forms are read as stored and
 * never rewritten; no producer writes them. Removing legacy support means
 * deleting this class and its callers (see docs/specs/reaction_record_v1.md).
 */
final class LegacyReactionHeaders
{
    private const TARGET = [ReactionRecordParser::THREAD => 'Thread-ID', ReactionRecordParser::POST => 'Post-ID'];
    private const TOKENS = [ReactionRecordParser::THREAD => 'Labels', ReactionRecordParser::POST => 'Tags'];

    /**
     * Legacy records carry neither unified scope header.
     *
     * @param array<string,string> $headers
     */
    public static function applies(array $headers): bool
    {
        return !isset($headers['Target-Kind']) && !isset($headers['Target-ID']);
    }

    /**
     * Maps a legacy header set onto the unified one.
     *
     * @param array<string,string> $headers
     * @return array<string,string>
     */
    public static function toUnified(array $headers, string $kind, string $family): array
    {
        $target = self::TARGET[$kind];
        $tokens = self::TOKENS[$kind];
        foreach (['Record-ID', 'Created-At', $target, 'Operation', $tokens] as $header) {
            if (!isset($headers[$header]) || $headers[$header] === '') {
                throw new CanonicalRecordParseException('Missing required ' . $family . ' header: ' . $header);
            }
        }
        $mapped = $headers;
        unset($mapped[$target], $mapped[$tokens]);
        if ($kind === ReactionRecordParser::POST) {
            // Legacy post reactions ignored label-source headers.
            unset($mapped['Source-Post-ID'], $mapped['Source-Post-SHA256']);
        }

        return ['Target-Kind' => $kind, 'Target-ID' => $headers[$target], 'Tags' => $headers[$tokens]] + $mapped;
    }

    /**
     * Exact bytes the browser signed before the unified format, accepted from
     * cached older clients.
     */
    public static function signedClientRecord(string $kind, string $recordId, string $createdAt, string $target, string $tag, string $author): string
    {
        return "Record-ID: {$recordId}\nCreated-At: {$createdAt}\n" . self::TARGET[$kind] . ": {$target}\nOperation: add\n"
            . self::TOKENS[$kind] . ": {$tag}\nAuthor-Identity-ID: {$author}\n\n";
    }
}
