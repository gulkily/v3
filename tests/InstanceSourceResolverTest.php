<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Import\InstanceSourceResolver;

final class InstanceSourceResolverTest
{
    public function testExplicitAliasesAndHostnamesPreserveBasePaths(): void
    {
        $w = new ImportTestWorkspace();
        $aliases = $w->root . '/sources.json';
        $w->put($aliases, json_encode(['community' => 'https://forum.example/base/']));
        $resolver = new InstanceSourceResolver();
        assertSame('https://forum.example/base', $resolver->resolve('community', $aliases));
        assertSame('https://forum.example/base', $resolver->resolve('forum.example/base'));
        assertSame('https://forum.example/base', $resolver->resolve('https://forum.example/base/'));
        assertSame('http://localhost:8000', $resolver->resolve('http://localhost:8000'));
        assertSame('https://[::1]:8000', $resolver->resolve('[::1]:8000'));
    }

    public function testUnknownNamesMalformedMappingsAndUnsafeUrlsFail(): void
    {
        $resolver = new InstanceSourceResolver();
        foreach (['chouse', 'unknown', 'https://user:secret@forum.example', 'https://forum.example/?token=x', "https://forum.example/\n"] as $input) {
            $failed = false;
            try { $resolver->resolve($input); } catch (RuntimeException) { $failed = true; }
            assertTrue($failed);
        }
        $w = new ImportTestWorkspace();
        foreach (['[]', '{"community":5}', '{"community":"file:///etc/passwd"}'] as $json) {
            $w->put($w->root . '/aliases.json', $json);
            $failed = false;
            try { $resolver->resolve('community', $w->root . '/aliases.json'); } catch (RuntimeException) { $failed = true; }
            assertTrue($failed);
        }
    }
}
