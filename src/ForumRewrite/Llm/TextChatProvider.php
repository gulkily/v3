<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

interface TextChatProvider
{
    /**
     * @param list<array{role:string, content:string}> $messages
     * @param array<string, mixed> $options
     * @return array{
     *   provider:string,
     *   provider_model:string,
     *   provider_request_id:?string,
     *   response_text:string,
     *   raw_response:array<string, mixed>,
     *   timings?:array<string, float>
     * }
     */
    public function completeTextChat(array $messages, array $options = []): array;
}
