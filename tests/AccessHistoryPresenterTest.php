<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Activity\AccessHistoryPresenter;

final class AccessHistoryPresenterTest
{
    public function testReceiptFactsTakePrecedenceOverHistoricalLabels(): void
    {
        $summary = AccessHistoryPresenter::present([
            'kind' => 'approval', 'label' => 'unreliable legacy text', 'author_label' => 'old label',
            'security_account' => 'old target',
            'security_review' => ['context' => ['target_username' => 'alice', 'actor_username' => 'morgan', 'classification' => 'own_account_key']],
        ]);
        assertSame('Account key approved for alice', $summary['title']);
        assertSame('By morgan · own account key', $summary['byline']);
    }

    public function testMissingReceiptDoesNotInventAcceptanceClassification(): void
    {
        $summary = AccessHistoryPresenter::present(['kind' => 'approval', 'author_label' => '', 'label' => 'own_account_key: old prose']);
        assertSame('Account key approved', $summary['title']);
        assertSame('By unknown', $summary['byline']);
        assertSame('', $summary['created_at']);
    }

    public function testInvitationAndPolicySummariesUseExistingFacts(): void
    {
        $summary = AccessHistoryPresenter::present(['kind' => 'invitation', 'security_account' => 'casey', 'author_label' => 'casey']);
        assertSame('Invitation accepted by casey', $summary['title']);
        assertSame('By casey', $summary['byline']);
        foreach (['true' => 'enabled', 'false' => 'restricted'] as $value => $word) {
            $summary = AccessHistoryPresenter::present(['kind' => 'site_feature_flag', 'label' => 'FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=' . $value]);
            assertSame('Cross-account approvals ' . $word, $summary['title']);
        }
        assertSame('Unknown policy record', AccessHistoryPresenter::present(['kind' => 'site_feature_flag', 'label' => 'Unknown policy record'])['title']);
    }
}
