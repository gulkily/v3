<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\StructuredChatProvider;
use ForumRewrite\Scoring\FastPostScorer;

final class FastPostScorerTest
{
    public function testScoreUsesOneProbabilitySchemaAndCompactContext(): void
    {
        $provider = new FastPostScorerFakeProvider(['probability' => 0.75]);
        $result = (new FastPostScorer($provider, 'Score only.'))->score([
            'post_id' => 'post-1',
            'content_hash' => 'hash-1',
            'post_text' => 'Target text',
        ]);

        assertSame('scored', $result['status']);
        assertSame(0.75, $result['probability']);
        assertSame('llm', $result['source']);
        assertSame('FastPostScore', $provider->schemaName);
        assertSame(16, $provider->options['max_completion_tokens']);
        assertSame('fast_post_score', $provider->options['exchange_context']['call_type']);
        assertSame(['probability'], $provider->schema['required']);
        assertSame(false, array_key_exists('reason', $provider->schema['properties']));
        assertStringContains('Target text', $provider->messages[1]['content']);
    }

    public function testScoreRejectsNonNumericAndOutOfRangeProbabilities(): void
    {
        $stringResult = (new FastPostScorer(new FastPostScorerFakeProvider(['probability' => '0.5']), 'Score only.'))->score([]);
        $outOfRangeResult = (new FastPostScorer(new FastPostScorerFakeProvider(['probability' => 1.1]), 'Score only.'))->score([]);

        assertSame('invalid_response', $stringResult['status']);
        assertSame(null, $stringResult['probability']);
        assertSame('invalid_response', $outOfRangeResult['status']);
    }

    public function testScoreReportsProviderFailureWithoutManufacturingAScore(): void
    {
        $result = (new FastPostScorer(new FastPostScorerFakeProvider([], true), 'Score only.'))->score([]);

        assertSame('provider_error', $result['status']);
        assertSame(null, $result['probability']);
        assertSame('none', $result['source']);
    }
}

final class FastPostScorerFakeProvider implements StructuredChatProvider
{
    /** @var list<array{role:string, content:string}> */
    public array $messages = [];
    /** @var array<string, mixed> */
    public array $schema = [];
    /** @var array<string, mixed> */
    public array $options = [];
    public string $schemaName = '';

    /** @param array<string, mixed> $decoded */
    public function __construct(private readonly array $decoded, private readonly bool $throws = false)
    {
    }

    public function completeStructuredChat(string $schemaName, array $messages, array $jsonSchema, array $options = []): array
    {
        if ($this->throws) {
            throw new RuntimeException('Provider unavailable.');
        }

        $this->schemaName = $schemaName;
        $this->messages = $messages;
        $this->schema = $jsonSchema;
        $this->options = $options;

        return [
            'provider' => 'fake',
            'provider_model' => 'fake-fast-model',
            'provider_request_id' => 'request-1',
            'decoded' => $this->decoded,
            'raw_response' => [],
        ];
    }
}
