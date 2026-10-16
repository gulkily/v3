<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

final class StubTextChatProvider implements TextChatProvider
{
    public function completeTextChat(array $messages, array $options = []): array
    {
        $paraphrase = str_contains((string) ($messages[1]['content'] ?? ''), 'Application source status: KJV source unavailable.')
            || str_contains((string) ($messages[1]['content'] ?? ''), 'Application source status: Requested translation cannot');
        return [
            'provider' => 'stub',
            'provider_model' => 'stub/agent-response',
            'provider_request_id' => null,
            'response_text' => str_contains((string) ($messages[0]['content'] ?? ''), 'specified JSON document')
                ? json_encode(['segments' => [
                    ['type' => 'reflection', 'text' => 'Choose what truly needs doing now, leave optional plans for later, and make a responsible stopping point. This is practical application of the value of thoughtful diligence, not a promise of success.'],
                    ...($paraphrase ? [] : [['type' => 'quotation', 'book' => 'Proverbs', 'chapter' => 21, 'start' => 5, 'end' => 5, 'translation' => 'KJV']]),
                ]], JSON_THROW_ON_ERROR)
                : 'Stub agent response.',
            'raw_response' => [
                'messages' => $messages,
                'options' => $options,
            ],
        ];
    }
}
