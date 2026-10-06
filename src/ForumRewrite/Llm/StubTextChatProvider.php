<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

final class StubTextChatProvider implements TextChatProvider
{
    public function completeTextChat(array $messages, array $options = []): array
    {
        return [
            'provider' => 'stub',
            'provider_model' => 'stub/agent-response',
            'provider_request_id' => null,
            'response_text' => 'Stub agent response.',
            'raw_response' => [
                'messages' => $messages,
                'options' => $options,
            ],
        ];
    }
}
