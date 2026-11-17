<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * The hashtag rule for thread labels, shared by the write side (which turns a post's hashtags
 * into thread label records) and the thread page (which spots replies that are only hashtags).
 */
final class ThreadLabels
{
    private const HASHTAG = '/(^|[^A-Za-z0-9-])#([A-Za-z0-9-]+)/';
    private const LABEL = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** @return list<string> labels in first-seen order; quoted (`>`) lines are skipped */
    public static function fromBody(string $body): array
    {
        $labels = [];
        foreach (explode("\n", $body) as $line) {
            if (self::isQuoted($line) || preg_match_all(self::HASHTAG, $line, $matches) < 1) {
                continue;
            }
            foreach ($matches[2] as $label) {
                if (preg_match(self::LABEL, $label) === 1 && !in_array($label, $labels, true)) {
                    $labels[] = $label;
                }
            }
        }

        return $labels;
    }

    /**
     * True when a reply says nothing but labels: its body has at least one label, and nothing is
     * left once quoted lines, label hashtags and whitespace are removed. A subject, a thread type,
     * agent-response metadata or a retraction keeps the reply in the conversation.
     *
     * @param array<string, mixed> $post a posts read-model row
     */
    public static function isTagOnly(array $post): bool
    {
        foreach (['subject', 'thread_type', 'agent_response_json', 'retracted_at'] as $field) {
            if (trim((string) ($post[$field] ?? '')) !== '') {
                return false;
            }
        }
        $body = (string) ($post['body'] ?? '');
        if (self::fromBody($body) === []) {
            return false;
        }
        foreach (explode("\n", $body) as $line) {
            if (self::isQuoted($line)) {
                continue;
            }
            $rest = preg_replace_callback(
                self::HASHTAG,
                static fn (array $m): string => preg_match(self::LABEL, $m[2]) === 1 ? $m[1] : $m[0],
                $line
            );
            if (trim((string) $rest) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function isQuoted(string $line): bool
    {
        return preg_match('/^\s*>/', $line) === 1;
    }
}
