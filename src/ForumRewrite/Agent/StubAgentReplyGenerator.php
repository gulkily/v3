<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

final class StubAgentReplyGenerator implements AgentReplyGenerator
{
    public function generate(array $context): array
    {
        $mode = (string) (($context['analysis']['respondability']['best_response_mode'] ?? '') ?: 'answer');

        return [
            'provider' => 'stub',
            'provider_model' => 'stub/agent-reply',
            'provider_request_id' => 'stub-agent-reply-' . substr((string) ($context['content_hash'] ?? '00000000'), 0, 8),
            'response_text' => 'Thanks for the thoughtful question. A useful next step is to compare the tradeoffs and name the assumption that matters most.',
            'response_style' => 'curious',
            'response_intent' => $mode,
            'raw_response' => [
                'stub' => true,
                'mode' => $mode,
            ],
        ];
    }
}
