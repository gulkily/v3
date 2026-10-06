<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

final class OpenPgpSignatureVerifier
{
    /**
     * @return array{ok:bool,fingerprint:?string,status:string,details:string,timings:array<string, float>,diagnostics:array{import_exit_code:?int,import_accepted:?bool,import_result_counts:?list<int>,import_debug_output:list<string>,gpg_import_started_at_epoch:?int,public_key_packet_inspection_exit_code:?int,public_key_creation_epoch:?int,public_key_creation_offset_seconds:?int,post_import_key_lookup_exit_code:?int,post_import_key_lookup_found:?bool,verification_exit_code:?int,validsig_present:bool,gpg_status_codes:list<string>}}
     */
    public function verifyDetached(
        string $armoredPublicKey,
        string $signedText,
        string $armoredDetachedSignature,
        string $expectedFingerprint
    ): array {
        $timings = [];
        $diagnostics = $this->emptyDiagnostics();
        $expectedFingerprint = strtoupper(trim($expectedFingerprint));
        if (!$this->looksLikeArmoredPublicKey($armoredPublicKey)) {
            return $this->failure('invalid_public_key', null, 'Public key is not ASCII-armored OpenPGP.', $timings, $diagnostics);
        }

        if (!$this->looksLikeArmoredDetachedSignature($armoredDetachedSignature)) {
            return $this->failure('invalid_signature', null, 'Detached signature is not ASCII-armored OpenPGP.', $timings, $diagnostics);
        }

        if ($expectedFingerprint === '' || preg_match('/^[A-F0-9]+$/', $expectedFingerprint) !== 1) {
            return $this->failure('invalid_expected_fingerprint', null, 'Expected fingerprint is invalid.', $timings, $diagnostics);
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

            $keyPacketInspection = $this->runGpg($tempDir, ['--list-packets', $keyPath]);
            $timings['gpg_public_key_packet_inspection'] = $keyPacketInspection['duration_ms'];
            $diagnostics['public_key_packet_inspection_exit_code'] = $keyPacketInspection['exit_code'];
            $diagnostics['public_key_creation_epoch'] = $this->publicKeyCreationEpoch($keyPacketInspection['output']);
            $diagnostics['gpg_import_started_at_epoch'] = time();
            if ($diagnostics['public_key_creation_epoch'] !== null) {
                $diagnostics['public_key_creation_offset_seconds'] = $diagnostics['public_key_creation_epoch'] - $diagnostics['gpg_import_started_at_epoch'];
            }

            $import = $this->runGpg($tempDir, ['--status-fd', '1', '--import', $keyPath]);
            $timings['gpg_public_key_import'] = $import['duration_ms'];
            $diagnostics['import_exit_code'] = $import['exit_code'];
            $diagnostics['import_accepted'] = $import['exit_code'] === 0 || $this->hasSuccessfulImport($import['output']);
            $diagnostics['gpg_status_codes'] = $this->statusCodes($import['output']);
            if ($import['exit_code'] === 0 && !$this->hasSuccessfulImport($import['output'])) {
                $diagnostics['import_result_counts'] = $this->importResultCounts($import['output']);
                $diagnostics['import_debug_output'] = $this->limitedOutput($import['output']);
            }
            $keyLookup = $this->runGpg($tempDir, ['--with-colons', '--list-keys', $expectedFingerprint]);
            $timings['gpg_post_import_key_lookup'] = $keyLookup['duration_ms'];
            $diagnostics['post_import_key_lookup_exit_code'] = $keyLookup['exit_code'];
            $diagnostics['post_import_key_lookup_found'] = $this->keyListingIncludesFingerprint($keyLookup['output'], $expectedFingerprint);
            if (!$diagnostics['import_accepted']) {
                return $this->failure('public_key_import_failed', null, $this->compactOutput($import['output']), $timings, $diagnostics);
            }

            $verification = $this->runGpg($tempDir, ['--status-fd', '1', '--verify', $signaturePath, $textPath]);
            $timings['gpg_signature_verify'] = $verification['duration_ms'];
            $fingerprint = $this->validSignatureFingerprint($verification['output']);
            $diagnostics['verification_exit_code'] = $verification['exit_code'];
            $diagnostics['validsig_present'] = $fingerprint !== null;
            $diagnostics['gpg_status_codes'] = array_values(array_unique(array_merge(
                $diagnostics['gpg_status_codes'],
                $this->statusCodes($verification['output']),
            )));
            if ($verification['exit_code'] !== 0 || $fingerprint === null) {
                return $this->failure('signature_verification_failed', $fingerprint, $this->compactOutput($verification['output']), $timings, $diagnostics);
            }

            if ($fingerprint !== $expectedFingerprint) {
                return $this->failure('signer_fingerprint_mismatch', $fingerprint, 'Signature was made by a different key.', $timings, $diagnostics);
            }

            return [
                'ok' => true,
                'fingerprint' => $fingerprint,
                'status' => 'ok',
                'details' => '',
                'timings' => $timings,
                'diagnostics' => $diagnostics,
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
     * @return array{exit_code:int,output:list<string>,duration_ms:float}
     */
    private function runGpg(string $homedir, array $arguments): array
    {
        $startedAt = hrtime(true);
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
            'duration_ms' => round((hrtime(true) - $startedAt) / 1000000, 1),
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
    private function keyListingIncludesFingerprint(array $output, string $expectedFingerprint): bool
    {
        foreach ($output as $line) {
            $parts = explode(':', $line);
            if (($parts[0] ?? '') === 'fpr' && strtoupper((string) ($parts[9] ?? '')) === $expectedFingerprint) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $output
     */
    private function publicKeyCreationEpoch(array $output): ?int
    {
        $insidePublicKeyPacket = false;
        foreach ($output as $line) {
            $trimmed = trim($line);
            if ($trimmed === ':public key packet:') {
                $insidePublicKeyPacket = true;
                continue;
            }
            if ($insidePublicKeyPacket && preg_match('/\bcreated ([0-9]+),/', $trimmed, $matches) === 1) {
                return (int) $matches[1];
            }
            if ($insidePublicKeyPacket && str_starts_with($trimmed, ':')) {
                $insidePublicKeyPacket = false;
            }
        }

        return null;
    }

    /**
     * @param list<string> $output
     * @return ?list<int>
     */
    private function importResultCounts(array $output): ?array
    {
        foreach ($output as $line) {
            if (preg_match('/^\[GNUPG:\]\s+IMPORT_RES(?:\s+(.+))?$/', $line, $matches) !== 1) {
                continue;
            }

            $values = preg_split('/\s+/', trim((string) ($matches[1] ?? ''))) ?: [];
            if ($values === [] || array_filter($values, static fn (string $value): bool => preg_match('/^[0-9]+$/', $value) !== 1) !== []) {
                return null;
            }

            return array_map('intval', $values);
        }

        return null;
    }

    /**
     * Import anomalies are local debugging evidence. GnuPG does not echo key
     * material for an import, but bound its output in case a malformed key
     * causes unusually verbose diagnostics.
     *
     * @param list<string> $output
     * @return list<string>
     */
    private function limitedOutput(array $output): array
    {
        $lines = [];
        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $lines[] = substr($line, 0, 512);
            if (count($lines) === 20) {
                break;
            }
        }

        return $lines;
    }

    /**
     * @param list<string> $output
     * @return list<string>
     */
    private function statusCodes(array $output): array
    {
        $codes = [];
        foreach ($output as $line) {
            if (preg_match('/^\[GNUPG:\]\s+([A-Z_]+)/', $line, $matches) === 1) {
                $codes[] = $matches[1];
            }
        }

        return array_values(array_unique($codes));
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
     * @param array<string, float> $timings
     * @param array{import_exit_code:?int,import_accepted:?bool,import_result_counts:?list<int>,import_debug_output:list<string>,gpg_import_started_at_epoch:?int,public_key_packet_inspection_exit_code:?int,public_key_creation_epoch:?int,public_key_creation_offset_seconds:?int,post_import_key_lookup_exit_code:?int,post_import_key_lookup_found:?bool,verification_exit_code:?int,validsig_present:bool,gpg_status_codes:list<string>} $diagnostics
     * @return array{ok:bool,fingerprint:?string,status:string,details:string,timings:array<string, float>,diagnostics:array{import_exit_code:?int,import_accepted:?bool,import_result_counts:?list<int>,import_debug_output:list<string>,gpg_import_started_at_epoch:?int,public_key_packet_inspection_exit_code:?int,public_key_creation_epoch:?int,public_key_creation_offset_seconds:?int,post_import_key_lookup_exit_code:?int,post_import_key_lookup_found:?bool,verification_exit_code:?int,validsig_present:bool,gpg_status_codes:list<string>}}
     */
    private function failure(string $status, ?string $fingerprint, string $details, array $timings, array $diagnostics): array
    {
        return [
            'ok' => false,
            'fingerprint' => $fingerprint,
            'status' => $status,
            'details' => $details,
            'timings' => $timings,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{import_exit_code:?int,import_accepted:?bool,import_result_counts:?list<int>,import_debug_output:list<string>,gpg_import_started_at_epoch:?int,public_key_packet_inspection_exit_code:?int,public_key_creation_epoch:?int,public_key_creation_offset_seconds:?int,post_import_key_lookup_exit_code:?int,post_import_key_lookup_found:?bool,verification_exit_code:?int,validsig_present:bool,gpg_status_codes:list<string>}
     */
    private function emptyDiagnostics(): array
    {
        return [
            'import_exit_code' => null,
            'import_accepted' => null,
            'import_result_counts' => null,
            'import_debug_output' => [],
            'gpg_import_started_at_epoch' => null,
            'public_key_packet_inspection_exit_code' => null,
            'public_key_creation_epoch' => null,
            'public_key_creation_offset_seconds' => null,
            'post_import_key_lookup_exit_code' => null,
            'post_import_key_lookup_found' => null,
            'verification_exit_code' => null,
            'validsig_present' => false,
            'gpg_status_codes' => [],
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
