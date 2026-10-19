<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Support\FrontendTargetResolver;

final class FrontendTargetResolverTest
{
    public function testExplicitTargetWinsAndEnvironmentPersistsAcrossCalls(): void
    {
        $before = getenv('FORUM_STATUS_URL');
        try {
            putenv('FORUM_STATUS_URL=http://127.0.0.1:80');
            $resolver = new FrontendTargetResolver('/nonexistent-checkout', '/nonexistent-repository');
            assertSame(['url' => 'http://127.0.0.1:8001', 'source' => '--url'], $resolver->resolve('http://127.0.0.1:8001'));
            assertSame(['url' => 'http://127.0.0.1:80', 'source' => 'FORUM_STATUS_URL'], $resolver->resolve());
            putenv('FORUM_STATUS_URL=invalid');
            assertThrowsRuntime(static fn () => $resolver->resolve(), 'Frontend target must be an http(s) URL without credentials, fragments, or whitespace.');
        } finally {
            $before === false ? putenv('FORUM_STATUS_URL') : putenv('FORUM_STATUS_URL=' . $before);
        }
    }

    public function testRejectsUnsafeUrls(): void
    {
        foreach (['file:///tmp/test', 'http://user:secret@localhost', "http://localhost/\r\nX: x", 'http://localhost/#fragment'] as $url) {
            assertThrowsRuntime(static fn () => FrontendTargetResolver::validateUrl($url), 'Frontend target must be an http(s) URL without credentials, fragments, or whitespace.');
        }
    }
}
