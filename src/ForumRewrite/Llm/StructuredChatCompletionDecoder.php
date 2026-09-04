<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

use RuntimeException;

final class StructuredChatCompletionDecoder
{
    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public static function decodeOpenAiCompatiblePayload(array $response): array
    {
        $message = $response['choices'][0]['message'] ?? null;
        if (is_array($message)) {
            $parsed = $message['parsed'] ?? null;
            if (is_array($parsed)) {
                return $parsed;
            }

            $content = $message['content'] ?? null;
            if (is_array($content) && !array_is_list($content)) {
                return $content;
            }

            $contentText = self::contentToText($content);
            if ($contentText !== '') {
                $decoded = json_decode($contentText, true);
                if (is_array($decoded)) {
                    return $decoded;
                }

                throw new RuntimeException('OpenAI-compatible response content was not valid JSON.');
            }
        }

        $outputText = self::contentToText($response['output_text'] ?? null);
        if ($outputText !== '') {
            $decoded = json_decode($outputText, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $finishReason = (string) ($response['choices'][0]['finish_reason'] ?? 'unknown');
        throw new RuntimeException('OpenAI-compatible response did not include parseable structured content; finish_reason=' . $finishReason);
    }

    private static function contentToText(mixed $content): string
    {
        if (is_string($content)) {
            return trim($content);
        }

        if (is_array($content)) {
            $parts = [];
            foreach ($content as $item) {
                if (is_array($item) && isset($item['text'])) {
                    $parts[] = (string) $item['text'];
                } elseif (is_string($item)) {
                    $parts[] = $item;
                }
            }

            return trim(implode('', $parts));
        }

        return '';
    }
}
