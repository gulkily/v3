<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

final class AgentResponseTask
{
    public const DEFAULT_TYPE = 'default_text_reply';
    public const LEGACY_TYPE = 'legacy_analysis_reply';
    public const PUBLICATION_SLOT = 'agent_reply';

    /**
     * @param array<string, mixed> $context
     * @return array<string, string|int>
     */
    public static function defaultForPost(array $context): array
    {
        return [
            'version' => 1,
            'type' => self::DEFAULT_TYPE,
            'publication_slot' => self::PUBLICATION_SLOT,
            'target_post_id' => (string) ($context['post_id'] ?? ''),
            'target_content_hash' => (string) ($context['content_hash'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    public static function defaultGenerationInput(array $context): array
    {
        return [
            'type' => self::DEFAULT_TYPE,
            'post_id' => (string) ($context['post_id'] ?? ''),
            'content_hash' => (string) ($context['content_hash'] ?? ''),
            'thread_subject' => self::limit((string) ($context['thread_subject'] ?? ''), 500),
            'target_subject' => self::limit((string) ($context['subject'] ?? ''), 500),
            'target_body' => self::limit((string) ($context['body'] ?? ''), 6000),
            'parent_body_preview' => self::limit((string) ($context['parent_body_preview'] ?? ''), 1200),
        ];
    }

    /**
     * @param array<string, mixed> $storedRequestContext
     * @return array<string, string|int>
     */
    public static function fromStoredRequestContext(array $storedRequestContext): array
    {
        $request = $storedRequestContext['agent_reply_request'] ?? null;
        $task = is_array($request) ? ($request['agent_response_task'] ?? null) : null;
        if (!is_array($task) || (string) ($task['type'] ?? '') !== self::DEFAULT_TYPE) {
            return self::legacy();
        }

        return [
            'version' => max(1, (int) ($task['version'] ?? 1)),
            'type' => self::DEFAULT_TYPE,
            'publication_slot' => self::PUBLICATION_SLOT,
            'target_post_id' => (string) ($task['target_post_id'] ?? ''),
            'target_content_hash' => (string) ($task['target_content_hash'] ?? ''),
        ];
    }

    /**
     * @return array<string, string|int>
     */
    private static function legacy(): array
    {
        return [
            'version' => 1,
            'type' => self::LEGACY_TYPE,
            'publication_slot' => self::PUBLICATION_SLOT,
            'target_post_id' => '',
            'target_content_hash' => '',
        ];
    }

    private static function limit(string $value, int $limit): string
    {
        return substr($value, 0, $limit);
    }
}
