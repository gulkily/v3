<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Analysis\DedalusPostAnalyzer;

final class DedalusPostAnalyzerTest
{
    public function testDecodeCompletionPayloadAcceptsStringJsonContent(): void
    {
        $decoded = DedalusPostAnalyzer::decodeCompletionPayload([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode($this->analysisPayload(), JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ]);

        assertSame('none', $decoded['moderation']['severity']);
        assertSame('curious', $decoded['engagement']['response_style']);
    }

    public function testDecodeCompletionPayloadAcceptsStructuredMessageContent(): void
    {
        $decoded = DedalusPostAnalyzer::decodeCompletionPayload([
            'choices' => [
                [
                    'message' => [
                        'content' => $this->analysisPayload(),
                    ],
                ],
            ],
        ]);

        assertSame('medium', $decoded['quality']['discussion_value']);
    }

    public function testDecodeCompletionPayloadAcceptsTextBlockContent(): void
    {
        $decoded = DedalusPostAnalyzer::decodeCompletionPayload([
            'choices' => [
                [
                    'message' => [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => json_encode($this->analysisPayload(), JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        assertSame(false, $decoded['quality']['needs_human_review']);
    }

    public function testDecodeCompletionPayloadPrefersParsedContent(): void
    {
        $payload = $this->analysisPayload();
        $payload['moderation']['severity'] = 'low';

        $decoded = DedalusPostAnalyzer::decodeCompletionPayload([
            'choices' => [
                [
                    'message' => [
                        'parsed' => $payload,
                        'content' => '',
                    ],
                ],
            ],
        ]);

        assertSame('low', $decoded['moderation']['severity']);
    }

    public function testSystemPromptLoadsFromTemplateFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dedalus-prompt-');
        assertSame(true, is_string($path));

        file_put_contents($path, "Styles: {{response_styles}}\nLabels: {{moderation_labels}}\n");

        try {
            $analyzer = new DedalusPostAnalyzer('test-key', 'https://example.invalid', 'test-model', 60, $path);
            $method = new \ReflectionMethod(DedalusPostAnalyzer::class, 'systemPrompt');
            $method->setAccessible(true);

            assertSame(
                'Styles: curious, clarifying, supportive, challenging, deescalating' . "\n"
                    . 'Labels: trolling, bad_faith, aggression, harassment, threat, spam, low_effort, off_topic, escalation_risk',
                $method->invoke($analyzer)
            );
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisPayload(): array
    {
        return [
            'engagement' => [
                'suggested_response' => 'What would change your mind?',
                'response_style' => 'curious',
                'response_should_be_public' => true,
            ],
            'moderation' => [
                'severity' => 'none',
                'labels' => [],
                'confidence' => 0.82,
                'summary' => 'No obvious issue.',
                'recommended_action' => 'none',
            ],
            'quality' => [
                'discussion_value' => 'medium',
                'good_faith_likelihood' => 0.78,
                'needs_human_review' => false,
            ],
        ];
    }
}
