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
            '/instance.html',
            '/instance/index.html',
            '/activity.html',
            '/activity/index.html',
            '/users.html',
            '/users/index.html',
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
            '/instance.html',
            '/instance/index.html',
            '/activity.html',
            '/activity/index.html',
            '/users.html',
            '/users/index.html',
            '/threads/' . $threadId . '.html',
            '/threads/' . $threadId . '/index.html',
            '/posts/' . $postId . '.html',
            '/posts/' . $postId . '/index.html',
        ]);
    }

    public function invalidateProfile(string $profileSlug): void
    {
        $this->deletePaths([
            '/users.html',
            '/users/index.html',
            '/profiles/' . $profileSlug . '.html',
            '/profiles/' . $profileSlug . '/index.html',
        ]);
    }

    public function invalidateIdentityLink(string $profileSlug, string $threadId, string $postId): void
    {
        $this->deletePaths([
            '/instance.html',
            '/instance/index.html',
            '/activity.html',
            '/activity/index.html',
            '/users.html',
            '/users/index.html',
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
            '/instance.html',
            '/instance/index.html',
            '/activity.html',
            '/activity/index.html',
            '/users.html',
            '/users/index.html',
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

    /**
     * Approval can change old content and every directory's membership/counts.
     * Retire generated presentation only; PHP serves the current model immediately.
     * Always repeat on retries, even if the database already contains the approval.
     */
    public function invalidateApprovalSeed(): void
    {
        $paths = [];
        foreach ($this->artifactRoots as $root) {
            if (!file_exists($root)) {
                continue;
            }
            if (!is_dir($root) || !is_readable($root)) {
                throw new \RuntimeException('Unable to inspect approval artifacts: ' . $root);
            }
            foreach (glob($root . '/*.html') ?: [] as $path) {
                $paths[] = substr($path, strlen($root));
            }
            foreach (['threads', 'posts', 'profiles', 'users', 'tags', 'activity', 'instance', 'tools', 'about', 'docs', 'latest', 'top', 'leetness', 'qdb'] as $family) {
                $directory = $root . '/' . $family;
                if (is_link($directory)) {
                    throw new \RuntimeException('Approval artifact directory must not be a symlink: ' . $directory);
                }
                if (!is_dir($directory)) {
                    continue;
                }
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
                foreach ($iterator as $file) {
                    if ($file->isLink() && $file->isDir()) {
                        throw new \RuntimeException('Approval artifact directory must not be a symlink: ' . $file->getPathname());
                    }
                    if ($file->getExtension() === 'html') {
                        $paths[] = substr($file->getPathname(), strlen($root));
                    }
                }
            }
        }
        // Drop served snapshot copies too; the existing snapshot endpoint can
        // create a fresh copy from the current model without rebuilding it.
        $paths = array_merge($paths, ['/offline/snapshot.sqlite3', '/offline/update.sqlite3', '/offline/manifest.json', '/offline/update.sqlite3.manifest.json']);
        $this->deletePaths(array_values(array_unique($paths)), true);
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
    private function deletePaths(array $paths, bool $strict = false): void
    {
        foreach ($this->artifactRoots as $root) {
            // A release is an all-or-nothing snapshot. Removing its pointer is
            // safer than trying to edit files inside it after a canonical write.
            // The next anonymous request uses PHP until a new release is built.
            $activeReleasePointer = $root . '/current';
            if ($strict && file_exists($activeReleasePointer) && !is_link($activeReleasePointer)) {
                throw new \RuntimeException('Cannot retire static release: current must be a symlink at ' . $activeReleasePointer);
            }
            if (is_link($activeReleasePointer)) {
                if (!@unlink($activeReleasePointer) && $strict) {
                    throw new \RuntimeException('Unable to retire static release: ' . $activeReleasePointer);
                }
            }

            foreach ($paths as $path) {
                $absolutePath = $root . $path;
                if ($strict) {
                    clearstatcache(true, $absolutePath);
                    if ((file_exists($absolutePath) || is_link($absolutePath)) && !@unlink($absolutePath)) {
                        throw new \RuntimeException('Unable to invalidate approval artifact: ' . $absolutePath);
                    }
                } elseif (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
        }
    }
}
