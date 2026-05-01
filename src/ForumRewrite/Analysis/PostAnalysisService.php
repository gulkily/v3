<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

use RuntimeException;

final class PostAnalysisService
{
    public function __construct(
        private readonly PostAnalysisStore $store,
        private readonly ?PostAnalyzer $analyzer,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function analyze(array $context): array
    {
        $postId = (string) ($context['post_id'] ?? '');
        $contentHash = (string) ($context['content_hash'] ?? '');
        if ($postId === '' || $contentHash === '') {
            throw new RuntimeException('Post analysis requires post_id and content_hash.');
        }

        $existing = $this->store->find($postId, $contentHash);
        if ($existing !== null && $existing['status'] === 'complete') {
            $existing['cached'] = true;
            return $existing;
        }

        if ($existing !== null && $existing['status'] === 'failed') {
            $retryAfter = strtotime((string) ($existing['retry_after'] ?? ''));
            if ($retryAfter !== false && $retryAfter > time()) {
                $existing['cached'] = true;
                return $existing;
            }
        }

        if ($this->analyzer === null) {
            return [
                'post_id' => $postId,
                'content_hash' => $contentHash,
                'status' => 'config_missing',
                'cached' => false,
                'message' => 'Dedalus API key is not configured.',
            ];
        }

        try {
            $analysis = $this->analyzer->analyze($context);
            $stored = $this->store->saveComplete($postId, $contentHash, $analysis);
            $stored['cached'] = false;
            return $stored;
        } catch (\Throwable $throwable) {
            $stored = $this->store->saveFailed($postId, $contentHash, 'provider_error', $throwable->getMessage());
            $stored['cached'] = false;
            return $stored;
        }
    }
}
