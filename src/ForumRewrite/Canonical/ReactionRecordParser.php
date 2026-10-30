<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * Shared parser for thread-label and post-reaction records.
 *
 * Reads the unified format (Target-Kind/Target-ID/Tags). Legacy header sets
 * are mapped by the deprecated LegacyReactionHeaders adapter. The caller's
 * record family fixes the expected scope.
 */
final class ReactionRecordParser
{
    public const THREAD = 'thread';
    public const POST = 'post';

    private const FAMILY_NAMES = [self::THREAD => 'thread-label', self::POST => 'post-reaction'];
    private const LEGACY_ONLY = ['Thread-ID', 'Post-ID', 'Labels'];

    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    /**
     * @return array{recordId:string,createdAt:string,targetId:string,operation:string,tags:list<string>,authorIdentityId:?string,reason:?string,body:string,actionAt:?string,intentId:?string,sourcePostId:?string,sourcePostSha256:?string}
     */
    public function parse(string $contents, string $kind): array
    {
        if (!isset(self::FAMILY_NAMES[$kind])) {
            throw new \InvalidArgumentException('Unknown reaction record kind: ' . $kind);
        }
        $record = $this->parser->parse($contents);
        $headers = $record->headers;
        $family = self::FAMILY_NAMES[$kind];

        if (LegacyReactionHeaders::applies($headers)) {
            $headers = LegacyReactionHeaders::toUnified($headers, $kind, $family);
        } else {
            foreach (self::LEGACY_ONLY as $header) {
                if (isset($headers[$header])) {
                    throw new CanonicalRecordParseException('Reaction record mixes unified and legacy headers: ' . $header);
                }
            }
            foreach (['Record-ID', 'Created-At', 'Target-Kind', 'Target-ID', 'Operation', 'Tags'] as $header) {
                if (!isset($headers[$header]) || $headers[$header] === '') {
                    throw new CanonicalRecordParseException('Missing required ' . $family . ' header: ' . $header);
                }
            }
        }

        if ($headers['Target-Kind'] !== $kind) {
            throw new CanonicalRecordParseException('Reaction Target-Kind must be ' . $kind . ' in the ' . $family . ' family.');
        }

        $createdAt = $this->parseCreatedAt($headers['Created-At']);
        $operation = $headers['Operation'];
        if ($operation !== 'add') {
            throw new CanonicalRecordParseException(ucfirst($family) . ' Operation must be add in V1.');
        }

        $authorIdentityId = $headers['Author-Identity-ID'] ?? null;
        if ($authorIdentityId !== null && !preg_match('/^openpgp:[a-f0-9]{40}$/', $authorIdentityId)) {
            throw new CanonicalRecordParseException('Author-Identity-ID must use the retained lowercase OpenPGP identity form.');
        }

        $sourceId = null;
        $sourceHash = null;
        if ($kind === self::THREAD) {
            $sourceId = $headers['Source-Post-ID'] ?? null;
            $sourceHash = $headers['Source-Post-SHA256'] ?? null;
            if (($sourceId === null) !== ($sourceHash === null)
                || ($sourceId !== null && (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,200}$/D', $sourceId)
                    || !preg_match('/^[a-f0-9]{64}$/D', $sourceHash)))) {
                throw new CanonicalRecordParseException('Label source requires a valid post ID and SHA-256 together.');
            }
        } elseif (isset($headers['Source-Post-ID']) || isset($headers['Source-Post-SHA256'])) {
            throw new CanonicalRecordParseException('Label source headers apply only to thread scope.');
        }

        return [
            'recordId' => $headers['Record-ID'],
            'createdAt' => $createdAt,
            'targetId' => $headers['Target-ID'],
            'operation' => $operation,
            'tags' => $this->parseTokens($headers['Tags'], $kind === self::THREAD ? 'label' : 'tag'),
            'authorIdentityId' => $authorIdentityId,
            'reason' => $headers['Reason'] ?? null,
            'body' => $record->body,
            'actionAt' => $this->optionalActionAt($headers['Action-At'] ?? null),
            'intentId' => $headers['Intent-ID'] ?? null,
            'sourcePostId' => $sourceId,
            'sourcePostSha256' => $sourceHash,
        ];
    }

    /**
     * Builds unified-format bytes. Record-ID and Created-At stay first so a
     * prepared record differs from a client record only in those two lines.
     *
     * @param list<string> $tags
     */
    public static function build(
        string $kind,
        string $recordId,
        string $createdAt,
        string $targetId,
        array $tags,
        ?string $authorIdentityId = null,
        ?string $actionAt = null,
        ?string $intentId = null,
        ?string $sourcePostId = null,
        ?string $sourcePostSha256 = null,
    ): string {
        return "Record-ID: {$recordId}\n"
            . "Created-At: {$createdAt}\n"
            . "Target-Kind: {$kind}\n"
            . "Target-ID: {$targetId}\n"
            . "Operation: add\n"
            . 'Tags: ' . implode(' ', $tags) . "\n"
            . ($authorIdentityId !== null ? "Author-Identity-ID: {$authorIdentityId}\n" : '')
            . ($actionAt !== null ? "Action-At: {$actionAt}\n" : '')
            . ($intentId !== null ? "Intent-ID: {$intentId}\n" : '')
            . ($sourcePostId !== null ? "Source-Post-ID: {$sourcePostId}\nSource-Post-SHA256: {$sourcePostSha256}\n" : '')
            . "\n";
    }

    private function optionalActionAt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z$/', $value) !== 1) {
            throw new CanonicalRecordParseException('Action-At must use RFC 3339 UTC format.');
        }

        return $value;
    }

    private function parseCreatedAt(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) !== 1) {
            throw new CanonicalRecordParseException('Created-At must use RFC 3339 UTC format like 2026-04-13T12:34:56Z.');
        }

        try {
            $timestamp = new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new CanonicalRecordParseException('Created-At must be a valid UTC timestamp.');
        }

        if ($timestamp->format('Y-m-d\TH:i:s\Z') !== $value) {
            throw new CanonicalRecordParseException('Created-At must be a valid UTC timestamp.');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function parseTokens(string $value, string $noun): array
    {
        $normalized = [];
        foreach (explode(' ', trim($value)) as $token) {
            if ($token === '') {
                continue;
            }
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $token)) {
                throw new CanonicalRecordParseException('Invalid ' . $noun . ' token: ' . $token);
            }
            if (!in_array($token, $normalized, true)) {
                $normalized[] = $token;
            }
        }
        if ($normalized === []) {
            throw new CanonicalRecordParseException('Tags must include at least one ' . $noun . ' token.');
        }

        return $normalized;
    }
}
