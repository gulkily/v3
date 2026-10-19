<?php

declare(strict_types=1);

use ForumRewrite\Security\GpgHomeCleaner;

require __DIR__ . '/../autoload.php';

final class GpgHomeCleanerTest
{
    public function testDisposeStopsAgentAndRemovesHome(): void
    {
        $home = sys_get_temp_dir() . '/forum-rewrite-gpg-cleaner-test-' . bin2hex(random_bytes(6));
        mkdir($home, 0700, true);
        exec('gpg --batch --no-tty --homedir ' . escapeshellarg($home)
            . ' --pinentry-mode loopback --passphrase "" --quick-generate-key cleaner-test ed25519 sign 0 2>&1');

        GpgHomeCleaner::dispose($home);

        assertTrue(!is_dir($home), 'Temporary GPG home should be removed, including nested key directories.');
        $agents = [];
        exec('pgrep -f ' . escapeshellarg(preg_quote($home, '/')) . ' 2>/dev/null', $agents);
        assertSame([], $agents);
        $sockets = [];
        exec('gpgconf --homedir ' . escapeshellarg($home) . ' --list-dirs agent-socket 2>/dev/null', $sockets);
        $socket = $sockets[0] ?? '';
        assertTrue($socket === '' || !file_exists($socket), 'Agent socket should be gone after dispose.');
    }

    public function testDisposeIgnoresMissingHome(): void
    {
        GpgHomeCleaner::dispose(sys_get_temp_dir() . '/forum-rewrite-gpg-cleaner-missing-' . bin2hex(random_bytes(6)));
        assertTrue(true);
    }
}
