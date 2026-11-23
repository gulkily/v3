<?php

declare(strict_types=1);

namespace ForumRewrite\Feedback;

/**
 * One feedback item file (docs/specs/feedback_item_record_v1.md).
 */
final class FeedbackItem
{
    public const KINDS = ['feature' => 'F', 'bug' => 'B', 'other' => 'O'];
    public const WORKFLOWS = ['untriaged', 'ready', 'planned', 'in-progress', 'blocked', 'done', 'declined', 'superseded', 'duplicate'];
    public const CLOSED_WORKFLOWS = ['done', 'declined', 'superseded', 'duplicate'];
    public const EVIDENCE = ['unknown', 'locally-verified', 'deployed', 'production-accepted'];

    /**
     * @param array<string, string> $headers
     * @param list<string> $sources source URLs in file order
     * @param string $notes the Notes: section, used for the touched date
     */
    public function __construct(
        public readonly string $path,
        public readonly array $headers,
        public readonly array $sources,
        public readonly string $notes = '',
    ) {
    }

    public function id(): string
    {
        return $this->headers['ID'] ?? '';
    }

    public function header(string $name, string $default = ''): string
    {
        return $this->headers[$name] ?? $default;
    }

    public function isClosed(): bool
    {
        return in_array($this->header('Workflow'), self::CLOSED_WORKFLOWS, true);
    }

    /**
     * Date of the latest dated note ("YYYY-MM-DD: ..."), else the created date.
     */
    public function touched(): string
    {
        $latest = $this->header('Created');
        if (preg_match_all('/^(\d{4}-\d{2}-\d{2}):/m', $this->notes, $matches) > 0) {
            $latest = max($latest, max($matches[1]));
        }
        return $latest;
    }

    /**
     * Estimate or Impact as a number; null when absent or "none".
     */
    public function number(string $header): ?float
    {
        $value = $this->header($header);
        return $value === '' || $value === 'none' ? null : (float) $value;
    }

    /**
     * Post or thread IDs this item claims, from its source URLs.
     *
     * @return list<string>
     */
    public function sourceIds(): array
    {
        $ids = [];
        foreach ($this->sources as $url) {
            $id = self::sourceId($url);
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    public static function sourceId(string $url): ?string
    {
        return preg_match('#^https?://[^/\s]+/(?:posts|threads)/([A-Za-z0-9][A-Za-z0-9-]*)$#', $url, $m) === 1 ? $m[1] : null;
    }
}
