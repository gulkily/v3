<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

/** What a push service said about one message: sent, gone (delete the device) or refused. */
final class PushSendResult
{
    public const SENT = 'sent';
    public const GONE = 'gone';
    public const REFUSED = 'refused';

    private function __construct(
        public readonly string $outcome,
        public readonly int $status,
        public readonly string $reason,
    ) {
    }

    public static function fromResponse(int $status, string $body): self
    {
        $outcome = match (true) {
            $status >= 200 && $status < 300 => self::SENT,
            $status === 404 || $status === 410 => self::GONE,
            default => self::REFUSED,
        };

        return new self($outcome, $status, $outcome === self::SENT ? '' : self::reason($body));
    }

    /** Not sent, and the device should go (for example its session signed out). */
    public static function gone(string $reason): self
    {
        return new self(self::GONE, 0, $reason);
    }

    public static function refused(string $reason): self
    {
        return new self(self::REFUSED, 0, $reason);
    }

    /** A short reason word: Apple's JSON `reason`, else the start of the body. */
    private static function reason(string $body): string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded) && is_string($decoded['reason'] ?? null)) {
            $body = $decoded['reason'];
        }
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($body)));

        return mb_substr($text, 0, 80);
    }
}
