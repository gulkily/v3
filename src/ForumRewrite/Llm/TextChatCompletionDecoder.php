<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

use RuntimeException;

final class TextChatCompletionDecoder
{
    /**
     * @param array<string, mixed> $response
     */
    public static function decodeOpenAiCompatiblePayload(array $response): string
    {
        $message = $response['choices'][0]['message'] ?? null;
        $text = is_array($message) ? self::contentToText($message['content'] ?? null) : '';
        if ($text === '') {
            $text = self::contentToText($response['output_text'] ?? null);
        }
        if ($text !== '') {
            return $text;
        }

        $finishReason = (string) ($response['choices'][0]['finish_reason'] ?? 'unknown');
        throw new RuntimeException('OpenAI-compatible response did not include text content; finish_reason=' . $finishReason);
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function decodeAnthropicPayload(array $response): string
    {
        $text = self::contentToText($response['content'] ?? null);
        if ($text !== '') {
            return $text;
        }

        $stopReason = (string) ($response['stop_reason'] ?? 'unknown');
        throw new RuntimeException('Anthropic response did not include text content; stop_reason=' . $stopReason);
    }

    private static function contentToText(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }
        if (!is_array($content)) {
            return '';
        }

        $parts = [];
        foreach ($content as $item) {
            if (is_array($item) && (string) ($item['type'] ?? 'text') === 'text' && isset($item['text'])) {
                $parts[] = (string) $item['text'];
            } elseif (is_string($item)) {
                $parts[] = $item;
            }
        }

        return trim(implode('', $parts));
    }
}
