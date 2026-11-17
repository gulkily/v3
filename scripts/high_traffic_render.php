<?php
/** Internal worker. Parent invokes with an argument array; visitor state is never inherited. */
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
exit(\ForumRewrite\Host\SharedRenderWorker::run($argv, \ForumRewrite\Host\SharedRenderWorker::HIGH_TRAFFIC));
