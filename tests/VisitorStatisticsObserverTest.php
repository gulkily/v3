<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Statistics\VisitorStatisticsObserver;

final class VisitorStatisticsObserverTest
{
    public function testOnlyEligibleGetPagesAreObserved(): void
    {
        $observer = new VisitorStatisticsObserver('/tmp/forum');

        assertTrue($observer->isEligiblePageRequest('GET', '/threads/'));
        assertFalse($observer->isEligiblePageRequest('POST', '/threads/'));
        assertFalse($observer->isEligiblePageRequest('GET', '/api/get_thread'));
        assertFalse($observer->isEligiblePageRequest('GET', '/assets/site.css'));
        assertFalse($observer->isEligiblePageRequest('GET', '/tools/visitor-statistics/'));
    }

    public function testRecorderFailureIsFailOpen(): void
    {
        $observer = new VisitorStatisticsObserver('/dev/null');

        $observer->record('GET', '/threads/');

        assertTrue(true);
    }
}
