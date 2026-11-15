<?php
/** Internal worker for tour snapshots. Same isolation as high_traffic_render.php. */
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
exit(\ForumRewrite\Host\SharedRenderWorker::run($argv, \ForumRewrite\Host\SharedRenderWorker::TOUR));
