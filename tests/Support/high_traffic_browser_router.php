<?php
if (PHP_SAPI !== 'cli-server' || !getenv('HIGH_TRAFFIC_TEST_ROOT')) { http_response_code(404);exit; }
require dirname(__DIR__,2) . '/autoload.php';
$project=dirname(__DIR__,2);$root=getenv('HIGH_TRAFFIC_TEST_ROOT');
(new \ForumRewrite\Host\FrontController($project,$root . '/repository',$root . '/read.sqlite3',$root . '/static',$root . '/public'))
    ->handle($_SERVER['REQUEST_METHOD'],$_SERVER['REQUEST_URI'],$_COOKIE);
