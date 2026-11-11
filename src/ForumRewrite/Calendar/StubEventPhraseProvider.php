<?php

declare(strict_types=1);

namespace ForumRewrite\Calendar;

use ForumRewrite\Llm\StructuredChatProvider;

/**
 * LLM_PROVIDER=stub for event phrases: no network, a fixed answer. A phrase containing
 * "unclear" is unclear; anything else is the day a week after today at 18:00, so a local
 * walk-through can see the fallback fill the fields.
 */
final class StubEventPhraseProvider implements StructuredChatProvider
{
    public function completeStructuredChat(string $schemaName, array $messages, array $jsonSchema, array $options = []): array
    {
        $user = (string) ($messages[1]['content'] ?? '');
        preg_match('/Today: \w+, (\d{4}-\d{2}-\d{2})/', $user, $today);
        $unclear = str_contains(strtolower($user), 'unclear') || !isset($today[1]);
        $decoded = $unclear
            ? ['status' => 'unclear', 'date' => '', 'end_date' => '', 'time' => '']
            : ['status' => 'read', 'date' => (new \DateTimeImmutable($today[1]))->modify('+7 days')->format('Y-m-d'), 'end_date' => '', 'time' => '18:00'];

        return [
            'provider' => 'stub',
            'provider_model' => 'stub/event-phrase',
            'provider_request_id' => null,
            'decoded' => $decoded,
            'raw_response' => ['messages' => $messages, 'options' => $options],
        ];
    }
}
