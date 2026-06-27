<?php

declare(strict_types=1);

namespace ForumRewrite\Write;

final class StaticArtifactInvalidator
{
    public function __construct(
        private readonly string $artifactRoot,
    ) {
    }

    public function invalidateBoardThread(string $threadId): void
    {
        $this->deletePaths([
            $this->artifactRoot . '/index.html',
            $this->artifactRoot . '/threads/' . $threadId . '.html',
            $this->artifactRoot . '/posts/' . $threadId . '.html',
        ]);
    }

    public function invalidateReply(string $threadId, string $postId): void
    {
        $this->deletePaths([
            $this->artifactRoot . '/index.html',
            $this->artifactRoot . '/threads/' . $threadId . '.html',
            $this->artifactRoot . '/posts/' . $postId . '.html',
        ]);
    }

    public function invalidateProfile(string $profileSlug): void
    {
        $this->deletePaths([
            $this->artifactRoot . '/profiles/' . $profileSlug . '.html',
        ]);
    }

    public function invalidateIdentityLink(string $profileSlug, string $threadId, string $postId): void
    {
        $this->deletePaths([
            $this->artifactRoot . '/profiles/' . $profileSlug . '.html',
            $this->artifactRoot . '/threads/' . $threadId . '.html',
            $this->artifactRoot . '/posts/' . $postId . '.html',
        ]);
    }

    public function invalidateApproval(string $profileSlug, string $threadId, string $parentPostId, string $approvalPostId): void
    {
        $this->deletePaths([
            $this->artifactRoot . '/profiles/' . $profileSlug . '.html',
            $this->artifactRoot . '/threads/' . $threadId . '.html',
            $this->artifactRoot . '/posts/' . $parentPostId . '.html',
            $this->artifactRoot . '/posts/' . $approvalPostId . '.html',
        ]);
    }

    public function invalidateFeatureFlags(): void
    {
        $this->deletePaths([
            $this->artifactRoot . '/index.html',
            $this->artifactRoot . '/threads.html',
            $this->artifactRoot . '/threads/index.html',
            $this->artifactRoot . '/about.html',
            $this->artifactRoot . '/about/index.html',
            $this->artifactRoot . '/instance.html',
            $this->artifactRoot . '/activity.html',
            $this->artifactRoot . '/users.html',
            $this->artifactRoot . '/tools.html',
            $this->artifactRoot . '/tools/index.html',
            $this->artifactRoot . '/tools/bookmarklets.html',
            $this->artifactRoot . '/tools/feature-flags.html',
            $this->artifactRoot . '/tags.html',
            $this->artifactRoot . '/tags/index.html',
        ]);
    }

    /**
     * @param list<string> $paths
     */
    private function deletePaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
