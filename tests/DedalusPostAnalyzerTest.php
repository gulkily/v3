<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Analysis\DedalusPostAnalyzer;
use ForumRewrite\Analysis\PostAnalyzer;
use ForumRewrite\Analysis\PostAnalysisService;
use ForumRewrite\Analysis\SqlitePostAnalysisStore;

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
        assertSame('The post asks how to make replies more useful.', $decoded['post_summary']);
        assertSame('curious', $decoded['engagement']['response_style']);
        assertSame(true, $decoded['respondability']['should_generate_response']);
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

        file_put_contents($path, "Styles: {{response_styles}}\nLabels: {{moderation_labels}}\nQuestion types: {{question_types}}\nModes: {{response_modes}}\n");

        try {
            $analyzer = new DedalusPostAnalyzer('test-key', 'https://example.invalid', 'test-model', 60, $path);
            $method = new \ReflectionMethod(DedalusPostAnalyzer::class, 'systemPrompt');
            $method->setAccessible(true);

            assertSame(
                'Styles: curious, clarifying, supportive, challenging, deescalating' . "\n"
                    . 'Labels: trolling, bad_faith, aggression, harassment, threat, spam, low_effort, off_topic, escalation_risk' . "\n"
                    . 'Question types: none, factual, opinion, advice, clarification, challenge, rhetorical' . "\n"
                    . 'Modes: none, answer, clarify, ask_followup, share_context, challenge_gently, deescalate',
                $method->invoke($analyzer)
            );
        } finally {
            @unlink($path);
        }
    }

    public function testSystemPromptGuidesUseOfRelatedContent(): void
    {
        $analyzer = new DedalusPostAnalyzer('test-key');
        $method = new \ReflectionMethod(DedalusPostAnalyzer::class, 'systemPrompt');
        $method->setAccessible(true);
        $prompt = (string) $method->invoke($analyzer);

        assertSame(true, str_contains($prompt, 'When related_content is present'));
        assertSame(true, str_contains($prompt, 'Do not cite weak related_content matches.'));
        assertSame(true, str_contains($prompt, 'solicitation_score'));
        assertSame(true, str_contains($prompt, 'asked for, solicited, or appropriate'));
        assertSame(true, str_contains($prompt, 'appropriate_to_show=false'));
    }

    public function testResponseSchemaRequiresRelatedContentAssessment(): void
    {
        $analyzer = new DedalusPostAnalyzer('test-key');
        $method = new \ReflectionMethod(DedalusPostAnalyzer::class, 'responseSchema');
        $method->setAccessible(true);

        $schema = $method->invoke($analyzer);

        assertSame(true, in_array('related_content_assessment', $schema['required'], true));
        assertSame(
            ['related_results_appropriate', 'solicitation_score', 'solicitation_reason', 'candidate_reviews'],
            $schema['properties']['related_content_assessment']['required']
        );
        assertSame(
            ['none', 'same_topic', 'same_question', 'direct_answer', 'duplicate_request', 'background_context', 'counterexample'],
            $schema['properties']['related_content_assessment']['properties']['candidate_reviews']['items']['properties']['relationship']['enum']
        );
    }

    public function testSqliteStorePersistsAndHydratesPostSummary(): void
    {
        $store = new SqlitePostAnalysisStore(new PDO('sqlite::memory:'));
        $stored = $store->saveComplete('root-001', 'hash-001', [
            'provider' => 'stub',
            'provider_model' => 'stub/post-analysis',
            'provider_request_id' => 'req-1',
            'post_summary' => 'The post asks how reply context should work.',
            'moderation' => [
                'severity' => 'none',
            ],
            'engagement' => [],
            'quality' => [],
            'respondability' => [],
            'raw_response' => [],
        ]);
        $hydrated = $store->find('root-001', 'hash-001');

        assertSame('The post asks how reply context should work.', $stored['post_summary']);
        assertSame('The post asks how reply context should work.', $hydrated['post_summary']);
        assertSame('none', $hydrated['moderation']['severity']);
    }

    public function testSqliteStorePersistsAndHydratesRelatedContentAssessment(): void
    {
        $store = new SqlitePostAnalysisStore(new PDO('sqlite::memory:'));
        $store->saveComplete('root-001', 'hash-001', [
            'provider' => 'stub',
            'provider_model' => 'stub/post-analysis',
            'post_summary' => 'Summary.',
            'moderation' => [],
            'engagement' => [],
            'quality' => [],
            'respondability' => [],
            'related_content_assessment' => [
                'related_results_appropriate' => false,
                'solicitation_score' => 0.2,
                'solicitation_reason' => 'Not requested.',
                'candidate_reviews' => [],
            ],
            'raw_response' => [],
        ]);

        $hydrated = $store->find('root-001', 'hash-001');

        assertSame(false, $hydrated['related_content_assessment']['related_results_appropriate']);
        assertSame(0.2, $hydrated['related_content_assessment']['solicitation_score']);
    }

    public function testPostAnalysisServicePersistsOnlyApprovedRelatedContent(): void
    {
        $store = new SqlitePostAnalysisStore(new PDO('sqlite::memory:'));
        $service = new PostAnalysisService($store, new class implements PostAnalyzer {
            public function analyze(array $context): array
            {
                return [
                    'provider' => 'test',
                    'provider_model' => 'test-model',
                    'post_summary' => 'Summary.',
                    'moderation' => [],
                    'engagement' => [],
                    'quality' => [],
                    'respondability' => [],
                    'related_content_assessment' => [
                        'related_results_appropriate' => true,
                        'solicitation_score' => 0.8,
                        'solicitation_reason' => 'The target asks for prior discussion.',
                        'candidate_reviews' => [
                            [
                                'post_id' => 'approved',
                                'relationship' => 'direct_answer',
                                'relevance_score' => 0.91,
                                'appropriate_to_show' => true,
                                'reason' => 'Direct prior answer.',
                            ],
                            [
                                'post_id' => 'rejected',
                                'relationship' => 'none',
                                'relevance_score' => 0.2,
                                'appropriate_to_show' => false,
                                'reason' => 'Only lexical overlap.',
                            ],
                        ],
                    ],
                    'raw_response' => [],
                ];
            }
        });

        $analysis = $service->analyze([
            'post_id' => 'target',
            'thread_id' => 'target-thread',
            'content_hash' => 'hash-001',
            'related_content' => [
                [
                    'post_id' => 'approved',
                    'thread_id' => 'approved-thread',
                    'post_url' => '/posts/approved',
                ],
                [
                    'post_id' => 'rejected',
                    'thread_id' => 'rejected-thread',
                    'post_url' => '/posts/rejected',
                ],
            ],
        ]);

        assertSame(['approved'], array_column($analysis['related_content'], 'post_id'));
        assertSame(true, $analysis['related_content_assessment']['related_results_appropriate']);
    }

    public function testPostAnalysisServiceSuppressesUnsolicitedSameTopicRelatedContent(): void
    {
        $store = new SqlitePostAnalysisStore(new PDO('sqlite::memory:'));
        $service = new PostAnalysisService($store, new class implements PostAnalyzer {
            public function analyze(array $context): array
            {
                return [
                    'provider' => 'test',
                    'provider_model' => 'test-model',
                    'post_summary' => 'Summary.',
                    'moderation' => [],
                    'engagement' => [],
                    'quality' => [],
                    'respondability' => [],
                    'related_content_assessment' => [
                        'related_results_appropriate' => true,
                        'solicitation_score' => 0.2,
                        'solicitation_reason' => 'Prior discussion is not solicited.',
                        'candidate_reviews' => [
                            [
                                'post_id' => 'same-topic',
                                'relationship' => 'same_topic',
                                'relevance_score' => 0.9,
                                'appropriate_to_show' => true,
                                'reason' => 'Same broad topic.',
                            ],
                        ],
                    ],
                    'raw_response' => [],
                ];
            }
        });

        $analysis = $service->analyze([
            'post_id' => 'target',
            'thread_id' => 'target-thread',
            'content_hash' => 'hash-001',
            'related_content' => [
                [
                    'post_id' => 'same-topic',
                    'thread_id' => 'same-topic-thread',
                    'post_url' => '/posts/same-topic',
                ],
            ],
        ]);

        assertSame([], $analysis['related_content']);
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisPayload(): array
    {
        return [
            'post_summary' => 'The post asks how to make replies more useful.',
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
            'respondability' => [
                'overall_score' => 0.72,
                'asks_question' => true,
                'question_type' => 'advice',
                'invites_response' => true,
                'author_benefit' => 'high',
                'audience_benefit' => 'medium',
                'response_effort_required' => 'medium',
                'response_risk' => 'low',
                'best_response_mode' => 'answer',
                'should_generate_response' => true,
                'reason' => 'The post asks a clear question.',
            ],
            'related_content_assessment' => [
                'related_results_appropriate' => false,
                'solicitation_score' => 0.1,
                'solicitation_reason' => 'The post does not ask for prior related discussion.',
                'candidate_reviews' => [],
            ],
        ];
    }
}
