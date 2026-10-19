<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Activity\ActivityService;

final class ActivityItemLinkTest
{
    private function service(): ActivityService
    {
        return new ActivityService(static fn () => throw new RuntimeException('unused'), '/nonexistent', '');
    }

    public function testSignedFeatureFlagChangeLinksToChangePage(): void
    {
        $link = $this->service()->activityItemLink(['kind' => 'site_feature_flag', 'source_path' => 'records/feature-flag-changes/ffc-001.txt', 'source_commit_sha' => str_repeat('a', 40)]);
        assertSame('/tools/feature-flags/changes/ffc-001', $link['href']);
        assertSame('site feature flags', $link['label']);
    }

    public function testLegacyFeatureFlagChangeLinksToCommitPage(): void
    {
        $sha = str_repeat('b', 40);
        $link = $this->service()->activityItemLink(['kind' => 'site_feature_flag', 'source_path' => 'records/instance/feature-flags.txt', 'source_commit_sha' => $sha]);
        assertSame('/source/commits/' . $sha, $link['href']);
    }

    public function testFeatureFlagChangeWithoutUsableSourceStillLinksSomewhere(): void
    {
        $link = $this->service()->activityItemLink(['kind' => 'site_feature_flag', 'source_path' => 'records/feature-flag-changes/../x.txt', 'source_commit_sha' => '']);
        assertSame('/tools/feature-flags/', $link['href']);
    }

    public function testOtherKindsKeepTheirExistingLinks(): void
    {
        $service = $this->service();
        assertSame('/threads/t1', $service->activityItemLink(['kind' => 'thread_label_add', 'thread_id' => 't1'])['href']);
        assertSame('/posts/p1', $service->activityItemLink(['kind' => 'post', 'post_id' => 'p1'])['href']);
        $profile = $service->activityItemLink(['kind' => 'profile_name_change', 'author_profile_slug' => 'a b', 'author_label' => 'Al']);
        assertSame('/profiles/a%20b', $profile['href']);
        assertSame('Al', $profile['label']);
    }
}
