<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

final class TestingSelfApprovalTest
{
    public function testMarkerAndDirectPeerAreRequired(): void
    {
        $root = sys_get_temp_dir() . '/self-approval-' . bin2hex(random_bytes(6));
        mkdir($root);
        $server = $_SERVER;
        $policy = new \ForumRewrite\Support\TestingSelfApproval($root);
        try {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            assertSame(false, $policy->isEnabledForRequest());
            file_put_contents($root . '/.test-self-approval', 'contents ignored');
            assertSame(true, $policy->isEnabledForRequest());
            $_SERVER['REMOTE_ADDR'] = '::1';
            assertSame(true, $policy->isEnabledForRequest());
            $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
            $_SERVER['HTTP_HOST'] = 'localhost';
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1';
            assertSame(false, $policy->isEnabledForRequest());
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            unlink($root . '/.test-self-approval');
            assertSame(false, $policy->isEnabledForRequest());
            mkdir($root . '/.test-self-approval');
            assertSame(false, $policy->isEnabledForRequest());
            rmdir($root . '/.test-self-approval');
        } finally {
            $_SERVER = $server;
            @unlink($root . '/.test-self-approval');
            rmdir($root);
        }
    }
}
