<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

final class OpenPgpSignatureVerifier
{
    /**
     * @return array{ok:bool,fingerprint:?string,status:string,details:string}
     */
    public function verifyDetached(
        string $armoredPublicKey,
        string $signedText,
        string $armoredDetachedSignature,
        string $expectedFingerprint
    ): array {
        $expectedFingerprint = strtoupper(trim($expectedFingerprint));
        if (!$this->looksLikeArmoredPublicKey($armoredPublicKey)) {
            return $this->failure('invalid_public_key', null, 'Public key is not ASCII-armored OpenPGP.');
        }

        if (!$this->looksLikeArmoredDetachedSignature($armoredDetachedSignature)) {
            return $this->failure('invalid_signature', null, 'Detached signature is not ASCII-armored OpenPGP.');
        }

        if ($expectedFingerprint === '' || preg_match('/^[A-F0-9]+$/', $expectedFingerprint) !== 1) {
            return $this->failure('invalid_expected_fingerprint', null, 'Expected fingerprint is invalid.');
        }

        $tempDir = sys_get_temp_dir() . '/forum-rewrite-gpg-verify-' . bin2hex(random_bytes(6));
        mkdir($tempDir, 0700, true);

        try {
            $keyPath = $tempDir . '/key.asc';
            $textPath = $tempDir . '/signed.txt';
            $signaturePath = $tempDir . '/signed.txt.asc';
            file_put_contents($keyPath, $armoredPublicKey);
            file_put_contents($textPath, $signedText);
            file_put_contents($signaturePath, $armoredDetachedSignature);

            $import = $this->runGpg($tempDir, ['--status-fd', '1', '--import', $keyPath]);
            if ($import['exit_code'] !== 0 && !$this->hasSuccessfulImport($import['output'])) {
                return $this->failure('public_key_import_failed', null, $this->compactOutput($import['output']));
            }

            $verification = $this->runGpg($tempDir, ['--status-fd', '1', '--verify', $signaturePath, $textPath]);
            $fingerprint = $this->validSignatureFingerprint($verification['output']);
            if ($verification['exit_code'] !== 0 || $fingerprint === null) {
                return $this->failure('signature_verification_failed', $fingerprint, $this->compactOutput($verification['output']));
            }

            if ($fingerprint !== $expectedFingerprint) {
                return $this->failure('signer_fingerprint_mismatch', $fingerprint, 'Signature was made by a different key.');
            }

            return [
                'ok' => true,
                'fingerprint' => $fingerprint,
                'status' => 'ok',
                'details' => '',
            ];
        } finally {
            $this->cleanup($tempDir);
        }
    }

    private function looksLikeArmoredPublicKey(string $value): bool
    {
        return preg_match('/-----BEGIN PGP PUBLIC KEY BLOCK-----[\s\S]+-----END PGP PUBLIC KEY BLOCK-----/', $value) === 1;
    }

    private function looksLikeArmoredDetachedSignature(string $value): bool
    {
        return preg_match('/-----BEGIN PGP SIGNATURE-----[\s\S]+-----END PGP SIGNATURE-----/', $value) === 1;
    }

    /**
     * @param list<string> $arguments
     * @return array{exit_code:int,output:list<string>}
     */
    private function runGpg(string $homedir, array $arguments): array
    {
        $command = array_merge([
            'gpg',
            '--batch',
            '--no-tty',
            '--homedir',
            $homedir,
        ], $arguments);

        $output = [];
        $exitCode = 0;
        exec(implode(' ', array_map('escapeshellarg', $command)) . ' 2>&1', $output, $exitCode);

        return [
            'exit_code' => $exitCode,
            'output' => array_map('strval', $output),
        ];
    }

    /**
     * @param list<string> $output
     */
    private function hasSuccessfulImport(array $output): bool
    {
        foreach ($output as $line) {
            if (str_starts_with($line, '[GNUPG:] IMPORT_OK ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $output
     */
    private function validSignatureFingerprint(array $output): ?string
    {
        foreach ($output as $line) {
            if (preg_match('/^\[GNUPG:\] VALIDSIG ([A-Fa-f0-9]+)/', $line, $matches) === 1) {
                return strtoupper($matches[1]);
            }
        }

        return null;
    }

    /**
     * @param list<string> $output
     */
    private function compactOutput(array $output): string
    {
        $lines = [];
        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $lines[] = $line;
        }

        return implode(' ', array_slice($lines, 0, 6));
    }

    /**
     * @return array{ok:bool,fingerprint:?string,status:string,details:string}
     */
    private function failure(string $status, ?string $fingerprint, string $details): array
    {
        return [
            'ok' => false,
            'fingerprint' => $fingerprint,
            'status' => $status,
            'details' => $details,
        ];
    }

    private function cleanup(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . '/' . $item;
            if (is_dir($child)) {
                $this->cleanup($child);
                @rmdir($child);
                continue;
            }

            @unlink($child);
        }

        @rmdir($path);
    }
}
