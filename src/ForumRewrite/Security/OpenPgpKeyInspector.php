<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use RuntimeException;

final class OpenPgpKeyInspector
{
    /**
     * @return array{fingerprint:string,username:string}
     */
    public function inspect(string $armoredPublicKey): array
    {
        $tempDir = sys_get_temp_dir() . '/forum-rewrite-gpg-' . bin2hex(random_bytes(6));
        mkdir($tempDir, 0700, true);
        $keyPath = $tempDir . '/key.asc';
        file_put_contents($keyPath, $armoredPublicKey);

        $command = sprintf(
            'gpg --batch --no-tty --homedir %s --with-colons --import-options show-only --import %s 2>/dev/null',
            escapeshellarg($tempDir),
            escapeshellarg($keyPath),
        );
        exec($command, $output, $exitCode);
        $this->cleanup($tempDir);

        if ($exitCode !== 0) {
            throw new RuntimeException('Unable to inspect OpenPGP public key.');
        }

        $fingerprint = null;
        $username = null;

        foreach ($output as $line) {
            $parts = explode(':', $line);
            if (($parts[0] ?? '') === 'fpr' && $fingerprint === null) {
                $fingerprint = $parts[9] ?? null;
            }

            if (($parts[0] ?? '') === 'uid' && $username === null) {
                $username = $parts[9] ?? null;
            }
        }

        if ($fingerprint === null || $username === null) {
            throw new RuntimeException('OpenPGP public key is missing a fingerprint or user ID.');
        }

        return [
            'fingerprint' => strtoupper(trim($fingerprint)),
            'username' => $this->normalizeUsername($username),
        ];
    }

    private function normalizeUsername(string $username): string
    {
        $normalized = strtolower(trim($username));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'guest';
    }

    private function cleanup(string $tempDir): void
    {
        foreach (glob($tempDir . '/*') ?: [] as $path) {
            @unlink($path);
        }

        @rmdir($tempDir);
    }
}
