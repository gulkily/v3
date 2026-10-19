<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\SourcePathValidator;
use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Tools\ToolsPageSupport;

/** Readable page for one signed feature-flag change record. */
final class FeatureFlagChangePageController
{
    public function __construct(private readonly RouteServices $services, private readonly string $repositoryRoot) {}

    public function show(string $recordId): void
    {
        $recordId = rawurldecode($recordId);
        if (preg_match('/^[A-Za-z0-9._-]+$/D', $recordId) !== 1 || str_contains($recordId, '..')) {
            $this->unavailable();
            return;
        }
        $path = CanonicalPathResolver::featureFlagChange($recordId);
        if (!SourcePathValidator::currentPathExists($this->repositoryRoot, $path)
            || !is_readable($this->repositoryRoot . '/' . $path)) {
            $this->unavailable();
            return;
        }
        try {
            $record = (new CanonicalRecordRepository($this->repositoryRoot))->loadFeatureFlagChange($path);
        } catch (CanonicalRecordParseException) {
            $this->unavailable();
            return;
        }
        $pdo = $this->services->pdo();
        $stmt = $pdo->prepare("SELECT id FROM activity WHERE kind = 'site_feature_flag' AND source_path = :path");
        $stmt->execute(['path' => $path]);
        $activityId = $stmt->fetchColumn();
        $activity = $this->services->activityService();
        $item = $activityId !== false ? $activity->fetchActivityDetail((int) $activityId) : null;
        $definition = (new FeatureFlagRegistry())->get($record->flagKey);
        $this->services->sendHtml($this->services->renderPageTemplate('feature_flag_change.php', [
            'record' => $record,
            'flagLabel' => $definition?->label ?? $record->flagKey,
            'operator' => ProfileRepository::byIdentityId($pdo, $record->operatorIdentityId),
            'sourcePath' => $path,
            'sourceItem' => $item ?? ['source_path' => $path, 'source_path_href' => $activity->sourcePathHref($path, '')],
            'toolNavOptions' => ToolsPageSupport::navOptions('feature-flags'),
        ], 'Feature flag change', 'tools'), 200);
    }

    private function unavailable(): void
    {
        $this->services->sendHtml($this->services->renderMessagePage('Feature flag change unavailable',
            'Feature flag change unavailable', 'No valid feature flag change record exists for this link.', 'tools'), 404);
    }
}
