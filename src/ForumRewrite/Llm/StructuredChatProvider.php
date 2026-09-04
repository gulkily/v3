<?php

declare(strict_types=1);

namespace ForumRewrite\Llm;

interface StructuredChatProvider
{
    /**
     * @param list<array{role:string, content:string}> $messages
     * @param array<string, mixed> $jsonSchema
     * @param array<string, mixed> $options
     * @return array{
     *   provider:string,
     *   provider_model:string,
     *   provider_request_id:?string,
     *   decoded:array<string, mixed>,
     *   raw_response:array<string, mixed>,
     *   timings?:array<string, float>
     * }
     */
    public function completeStructuredChat(string $schemaName, array $messages, array $jsonSchema, array $options = []): array;
}
