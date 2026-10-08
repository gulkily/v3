<?php

declare(strict_types=1);

namespace ForumRewrite\Offline;

/** Ensures first-time public setup has a snapshot without republishing one. */
final class OfflineSnapshotBootstrap
{
    public function __construct(
        private readonly string $staticHtmlRoot,
        private readonly OfflineSnapshotLocator $locator = new OfflineSnapshotLocator(),
    ) {
    }

    /**
     * @return array{status:'published'|'already_available'|'unavailable',path:?string}
     */
    public function ensure(string $databasePath, bool $publicationAllowed): array
    {
        if (!$publicationAllowed) {
            return ['status' => 'unavailable', 'path' => null];
        }

        $existingPath = $this->locator->servedSnapshotPath($this->staticHtmlRoot);
        if ($existingPath !== null) {
            return ['status' => 'already_available', 'path' => $existingPath];
        }

        $result = (new OfflineSnapshotPublisher($this->staticHtmlRoot))->publish($databasePath);

        return ['status' => 'published', 'path' => $result['path']];
    }
}
