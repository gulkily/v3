<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\SourcePathValidator;
use ForumRewrite\ReadModel\ProfileRepository;

/** Readable evidence for existing repository-owned root approval seeds. */
final class RootApprovalPageController
{
    public function __construct(private readonly RouteServices $services, private readonly string $repositoryRoot) {}

    public function show(string $keySlug): void
    {
        $keySlug = rawurldecode($keySlug);
        if (preg_match('/^openpgp-([a-f0-9]{40})$/D', $keySlug, $match) !== 1) {
            $this->unavailable();
            return;
        }
        $path = CanonicalPathResolver::approvalSeed($match[1]);
        if (!SourcePathValidator::currentPathExists($this->repositoryRoot, $path)
            || !is_readable($this->repositoryRoot . '/' . $path)) {
            $this->unavailable();
            return;
        }
        try {
            $seed = (new CanonicalRecordRepository($this->repositoryRoot))->loadApprovalSeed($path);
        } catch (CanonicalRecordParseException) {
            $this->unavailable();
            return;
        }
        $this->services->sendHtml($this->services->renderPageTemplate('root_approval.php', [
            'seed' => $seed,
            'keySlug' => $keySlug,
            'profile' => ProfileRepository::byIdentityId($this->services->pdo(), $seed->approvedIdentityId),
            'sourcePath' => $path,
            'sourceHref' => $this->services->activityService()->sourcePathHref($path, ''),
        ], 'Root approval', 'profiles'), 200);
    }

    private function unavailable(): void
    {
        $this->services->sendHtml($this->services->renderMessagePage('Root approval unavailable',
            'Root approval unavailable', 'No valid root approval record is available for this key.', 'profiles'), 404);
    }
}
