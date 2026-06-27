<?php

declare(strict_types=1);

namespace ForumRewrite\Write;

final class StaticArtifactInvalidator
{
    /** @var list<string> */
    private readonly array $artifactRoots;

    public function __construct(
        string $artifactRoot,
        string ...$additionalArtifactRoots,
    ) {
        $roots = [];
        foreach (array_merge([$artifactRoot], $additionalArtifactRoots) as $root) {
            if ($root !== '' && !in_array($root, $roots, true)) {
                $roots[] = $root;
            }
        }

        $this->artifactRoots = $roots;
    }

    public function invalidateBoardThread(string $threadId): void
    {
        $this->deletePaths([
            '/index.html',
            '/threads/' . $threadId . '.html',
            '/threads/' . $threadId . '/index.html',
            '/posts/' . $threadId . '.html',
            '/posts/' . $threadId . '/index.html',
        ]);
    }

    public function invalidateReply(string $threadId, string $postId): void
    {
        $this->deletePaths([
            '/index.html',
            '/threads/' . $threadId . '.html',
            '/threads/' . $threadId . '/index.html',
            '/posts/' . $postId . '.html',
            '/posts/' . $postId . '/index.html',
        ]);
    }

    public function invalidateProfile(string $profileSlug): void
    {
        $this->deletePaths([
            '/profiles/' . $profileSlug . '.html',
            '/profiles/' . $profileSlug . '/index.html',
        ]);
    }

    public function invalidateIdentityLink(string $profileSlug, string $threadId, string $postId): void
    {
        $this->deletePaths([
            '/profiles/' . $profileSlug . '.html',
            '/profiles/' . $profileSlug . '/index.html',
            '/threads/' . $threadId . '.html',
            '/threads/' . $threadId . '/index.html',
            '/posts/' . $postId . '.html',
            '/posts/' . $postId . '/index.html',
        ]);
    }

    public function invalidateApproval(string $profileSlug, string $threadId, string $parentPostId, string $approvalPostId): void
    {
        $this->deletePaths([
            '/profiles/' . $profileSlug . '.html',
            '/profiles/' . $profileSlug . '/index.html',
            '/threads/' . $threadId . '.html',
            '/threads/' . $threadId . '/index.html',
            '/posts/' . $parentPostId . '.html',
            '/posts/' . $parentPostId . '/index.html',
            '/posts/' . $approvalPostId . '.html',
            '/posts/' . $approvalPostId . '/index.html',
        ]);
    }

    public function invalidateFeatureFlags(): void
    {
        $this->deletePaths([
            '/index.html',
            '/threads.html',
            '/threads/index.html',
            '/about.html',
            '/about/index.html',
            '/instance.html',
            '/instance/index.html',
            '/activity.html',
            '/activity/index.html',
            '/users.html',
            '/users/index.html',
            '/tools.html',
            '/tools/index.html',
            '/tools/bookmarklets.html',
            '/tools/bookmarklets/index.html',
            '/tools/feature-flags.html',
            '/tools/feature-flags/index.html',
            '/tags.html',
            '/tags/index.html',
        ]);
    }

    /**
     * @param list<string> $paths
     */
    private function deletePaths(array $paths): void
    {
        foreach ($this->artifactRoots as $root) {
            foreach ($paths as $path) {
                $absolutePath = $root . $path;
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
        }
    }
}
