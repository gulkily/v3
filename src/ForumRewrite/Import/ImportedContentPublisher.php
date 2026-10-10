<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use Closure;
use ForumRewrite\Host\StaticArtifactReleasePublisher;
use ForumRewrite\Offline\OfflineSnapshotPublisher;
use ForumRewrite\ReadModel\ReadModelCandidateBuilder;
use ForumRewrite\ReadModel\ReadModelCandidatePromoter;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;

final class ImportedContentPublisher
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly string $staticHtmlRoot,
        private readonly ?Closure $progress = null,
        private readonly ?Closure $checkpoint = null,
    ) {
    }

    /** Called only by ContentImportRunner while holding the destination's exclusive writer lock. */
    public function publishWhileLocked(): void
    {
        $candidate = null;
        try {
            $this->at('before_build');
            $candidate = (new ReadModelCandidateBuilder($this->repositoryRoot, $this->databasePath, 'instance_import', $this->progress))->build();
            $private = FeatureFlagEvaluator::forApplication($this->repositoryRoot, $this->projectRoot)
                ->evaluate(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY)->effectiveValue;
            $release = null;
            $publisher = new StaticArtifactReleasePublisher($this->projectRoot, $this->repositoryRoot, $this->staticHtmlRoot);
            if (!$private) {
                $release = $publisher->build($candidate, $this->progress);
            }
            $this->at('built');
            (new ReadModelCandidatePromoter($this->repositoryRoot, $this->databasePath, $this->progress))->promoteWhileLocked($candidate);
            $candidate = null;
            $this->at('promoted');
            if ($release !== null) {
                $publisher->activate($release);
                $this->at('activated');
                // Standalone snapshots take precedence over release snapshots on the host.
                $offline = new OfflineSnapshotPublisher($this->staticHtmlRoot);
                $offline->publish($this->databasePath);
                $this->at('offline_snapshot');
                $offline->publishUpdate($this->databasePath);
            } elseif ($this->progress !== null) {
                ($this->progress)('Private destination: public static/offline publication skipped; access gates remain in force.');
            }
            $this->at('complete');
        } finally {
            if ($candidate !== null && is_file($candidate)) {
                unlink($candidate);
            }
        }
    }

    private function at(string $phase): void
    {
        if ($this->checkpoint !== null) {
            ($this->checkpoint)($phase);
        }
    }
}
