<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

final class AgentResponseTask
{
    public const DEFAULT_TYPE = 'default_text_reply';
    public const LEGACY_TYPE = 'legacy_analysis_reply';
    public const PUBLICATION_SLOT = 'agent_reply';
    public const FACTS_ANALYSIS_TYPE = 'facts_analysis';
    public const EXPLAIN_JOKE_OR_REFERENCE_TYPE = 'explain_joke_or_reference';
    public const SUMMARY_AND_KEY_TAKEAWAYS_TYPE = 'summary_and_key_takeaways';
    public const EXPLAIN_SIMPLY_TYPE = 'explain_simply';
    public const CONSTRUCTIVE_COUNTERPOINT_TYPE = 'constructive_counterpoint';

    /**
     * @param array<string, mixed> $context
     * @return array<string, string|int>
     */
    public static function defaultForPost(array $context): array
    {
        return self::forPost($context, self::DEFAULT_TYPE);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string|int>
     */
    public static function forPost(array $context, string $type): array
    {
        if (!self::isSupportedType($type)) {
            throw new \InvalidArgumentException('Unsupported agent response task type.');
        }

        return [
            'version' => 1,
            'type' => $type,
            'publication_slot' => self::PUBLICATION_SLOT,
            'target_post_id' => (string) ($context['post_id'] ?? ''),
            'target_content_hash' => (string) ($context['content_hash'] ?? ''),
        ];
    }

    /**
     * @return list<array{type:string, label:string, description:string}>
     */
    public static function selectableModes(): array
    {
        return [
            [
                'type' => self::FACTS_ANALYSIS_TYPE,
                'label' => 'Facts analysis',
                'description' => 'Claims, support, missing evidence, and uncertainty.',
            ],
            [
                'type' => self::EXPLAIN_JOKE_OR_REFERENCE_TYPE,
                'label' => 'Explain the joke or reference',
                'description' => 'Unpack humor, allusions, and cultural context.',
            ],
            [
                'type' => self::SUMMARY_AND_KEY_TAKEAWAYS_TYPE,
                'label' => 'Summary and key takeaways',
                'description' => 'Condense the main points.',
            ],
            [
                'type' => self::EXPLAIN_SIMPLY_TYPE,
                'label' => 'Explain simply',
                'description' => 'Restate the argument in plain language.',
            ],
            [
                'type' => self::CONSTRUCTIVE_COUNTERPOINT_TYPE,
                'label' => 'Constructive counterpoint',
                'description' => 'Surface a strong reasonable alternative view.',
            ],
        ];
    }

    public static function isSelectableType(string $type): bool
    {
        foreach (self::selectableModes() as $mode) {
            if ($mode['type'] === $type) {
                return true;
            }
        }

        return false;
    }

    public static function isSupportedType(string $type): bool
    {
        return $type === self::DEFAULT_TYPE || self::isSelectableType($type);
    }

    public static function labelForType(string $type): ?string
    {
        foreach (self::selectableModes() as $mode) {
            if ($mode['type'] === $type) {
                return $mode['label'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    public static function defaultGenerationInput(array $context): array
    {
        return self::generationInputForType($context, self::DEFAULT_TYPE);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    public static function generationInputForType(array $context, string $type): array
    {
        if (!self::isSupportedType($type)) {
            throw new \InvalidArgumentException('Unsupported agent response task type.');
        }

        return [
            'type' => $type,
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
        $type = is_array($task) ? (string) ($task['type'] ?? '') : '';
        if (!self::isSupportedType($type)) {
            return self::legacy();
        }

        return [
            'version' => max(1, (int) ($task['version'] ?? 1)),
            'type' => $type,
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
