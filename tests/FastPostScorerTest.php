<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\TextChatProvider;
use ForumRewrite\Analysis\ProviderRequestException;
use ForumRewrite\Scoring\FastPostScorer;

final class FastPostScorerTest
{
    public function testScoreUsesCsvTextAndCompactContext(): void
    {
        $provider = new FastPostScorerFakeProvider('0.75');
        $result = (new FastPostScorer($provider, 'Score only.'))->score([
            'post_id' => 'post-1',
            'content_hash' => 'hash-1',
            'post_text' => 'Target text',
            'exchange_dependencies' => \ForumRewrite\Llm\ExchangeDependencies::manifest(['post-1', 'parent']),
        ]);

        assertSame('scored', $result['status']);
        assertSame(0.75, $result['probability']);
        assertSame('llm', $result['source']);
        assertSame(['moderation' => 0.75], $result['scores']);
        assertSame(1024, $provider->options['max_completion_tokens']);
        assertSame('fast_post_score', $provider->options['exchange_context']['call_type']);
        assertSame(['parent', 'post-1'], $provider->options['exchange_context']['exchange_dependencies']['post_ids']);
        assertSame(false, str_contains($provider->messages[1]['content'], 'exchange_dependencies'));
        assertStringContains('Target text', $provider->messages[1]['content']);
    }

    public function testScoreRejectsNonNumericAndOutOfRangeProbabilities(): void
    {
        $stringResult = (new FastPostScorer(new FastPostScorerFakeProvider('not numeric'), 'Score only.'))->score([]);
        $outOfRangeResult = (new FastPostScorer(new FastPostScorerFakeProvider('1.1'), 'Score only.'))->score([]);

        assertSame('invalid_response', $stringResult['status']);
        assertSame(null, $stringResult['probability']);
        assertSame('invalid_response', $outOfRangeResult['status']);
    }

    public function testScoreReportsProviderFailureWithoutManufacturingAScore(): void
    {
        $result = (new FastPostScorer(new FastPostScorerFakeProvider('', true), 'Score only.'))->score([]);

        assertSame('provider_error', $result['status']);
        assertSame(null, $result['probability']);
        assertSame('none', $result['source']);
    }

    public function testScoreClassifiesMalformedTextCompletionAsInvalidResponse(): void
    {
        $result = (new FastPostScorer(new FastPostScorerFakeProvider('', false, true), 'Score only.'))->score([]);

        assertSame('invalid_response', $result['status']);
        assertSame('invalid_response', $result['failure_code']);
    }
}

final class FastPostScorerFakeProvider implements TextChatProvider
{
    /** @var list<array{role:string, content:string}> */
    public array $messages = [];
    /** @var array<string, mixed> */
    public array $options = [];

    public function __construct(private readonly string $response, private readonly bool $throws = false, private readonly bool $malformed = false)
    {
    }

    public function completeTextChat(array $messages, array $options = []): array
    {
        if ($this->throws) {
            throw new ProviderRequestException('Provider unavailable.', []);
        }
        if ($this->malformed) {
            throw new RuntimeException('Text completion was malformed.');
        }

        $this->messages = $messages;
        $this->options = $options;

        return [
            'provider' => 'fake',
            'provider_model' => 'fake-fast-model',
            'provider_request_id' => 'request-1',
            'response_text' => $this->response,
            'raw_response' => [],
        ];
    }
}
