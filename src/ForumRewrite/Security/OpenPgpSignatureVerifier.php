<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

final class OpenPgpSignatureVerifier
{
    private const MAX_PREPARED_HOMES = 64;

    private \ForumRewrite\Support\OperationBudget $budget;
    private int $outputBytes = 0;
    /** @var array<string, array{home:string,diagnostics:array}> Each home holds exactly one imported key, keyed by its bytes and expected fingerprint. */
    private array $preparedHomes = [];
    /** Parent of the prepared homes, named with the owning process ID so a killed process's homes can be found. */
    private ?string $keysRoot = null;
    private ?int $keysRootPid = null;

    /**
     * With $reuseKeyHomes, a key's first check imports it into a home of its own, and later checks
     * with the same key bytes and expected fingerprint run only `gpg --verify` there. The homes
     * are removed by releasePreparedKeys() or when the verifier is dropped.
     */
    public function __construct(
        private readonly array $gpgCommand = ['gpg'],
        private readonly float $seconds = 10.0,
        private readonly bool $reuseKeyHomes = false,
    ) {
        if (!is_finite($seconds) || $seconds <= 0 || $seconds > 10) throw new \InvalidArgumentException('Verification budget must be positive and at most 10 seconds.');
    }

    public function __destruct()
    {
        $this->releasePreparedKeys();
    }

    public function releasePreparedKeys(): void
    {
        if ($this->keysRoot !== null && $this->keysRootPid === getmypid()) {
            $this->cleanup($this->keysRoot);
        }
        // After a fork the homes belong to the other process, which removes them itself.
        $this->preparedHomes = [];
        $this->keysRoot = null;
        $this->keysRootPid = null;
    }

    /** A new home for one key under this process's parent folder, removing folders left by dead processes first. */
    private function newPreparedHome(): string
    {
        if ($this->keysRoot !== null && $this->keysRootPid !== getmypid()) {
            $this->releasePreparedKeys();
        }
        if ($this->keysRoot === null) {
            $prefix = sys_get_temp_dir() . '/forum-rewrite-gpg-keys-';
            if (function_exists('posix_kill')) {
                foreach (glob($prefix . '*', GLOB_ONLYDIR) ?: [] as $stale) {
                    if (preg_match('/-([0-9]+)-[a-f0-9]{12}$/D', $stale, $match) === 1 && (int) $match[1] !== getmypid()
                        && !@posix_kill((int) $match[1], 0) && posix_get_last_error() === 3) {
                        $this->cleanup($stale);
                    }
                }
            }
            $root = $prefix . getmypid() . '-' . bin2hex(random_bytes(6));
            mkdir($root, 0700, true);
            $this->keysRoot = $root;
            $this->keysRootPid = getmypid();
        }

        return $this->keysRoot . '/' . bin2hex(random_bytes(6));
    }

    /**
     * @return array{ok:bool,fingerprint:?string,status:string,details:string,timings:array<string, float>,diagnostics:array{import_exit_code:?int,import_accepted:?bool,import_result_counts:?list<int>,import_debug_output:list<string>,gpg_import_started_at_epoch:?int,public_key_packet_inspection_exit_code:?int,public_key_creation_epoch:?int,public_key_creation_offset_seconds:?int,post_import_key_lookup_exit_code:?int,post_import_key_lookup_found:?bool,verification_exit_code:?int,validsig_present:bool,gpg_status_codes:list<string>}}
     */
    public function verifyDetached(
        string $armoredPublicKey,
        string $signedText,
        string $armoredDetachedSignature,
        string $expectedFingerprint
    ): array {
        $this->budget = new \ForumRewrite\Support\OperationBudget($this->seconds);
        $this->outputBytes = 0;
        foreach ([$armoredPublicKey, $armoredDetachedSignature] as $armor) {
            if (strlen($armor) > \ForumRewrite\Canonical\RecordLimits::ARMOR) throw new \ForumRewrite\Support\OperationLimitException('Signature verification: armor exceeds 1 MiB limit.');
        }
        if (strlen($signedText) > \ForumRewrite\Canonical\RecordLimits::TEXT) throw new \ForumRewrite\Support\OperationLimitException('Signature verification: text exceeds 16 MiB limit.');
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

        $homeKey = hash('sha256', $expectedFingerprint . "\0" . $armoredPublicKey);
        $prepared = $this->reuseKeyHomes && $this->keysRootPid === getmypid() ? ($this->preparedHomes[$homeKey] ?? null) : null;
        if ($prepared !== null && !is_file($prepared['home'] . '/key.asc')) {
            // Someone removed the home; import the key again rather than report a false failure.
            unset($this->preparedHomes[$homeKey]);
            $prepared = null;
        }
        if ($prepared === null && $this->reuseKeyHomes && count($this->preparedHomes) >= self::MAX_PREPARED_HOMES) {
            $this->releasePreparedKeys();
        }
        $tempDir = $prepared['home'] ?? ($this->reuseKeyHomes ? $this->newPreparedHome() : sys_get_temp_dir() . '/forum-rewrite-gpg-verify-' . bin2hex(random_bytes(6)));
        // A kept home holds only the key; each check's text and signature are removed afterwards.
        $keep = $prepared !== null;
        $textPath = $tempDir . '/signed-' . bin2hex(random_bytes(6)) . '.txt';
        $signaturePath = $textPath . '.asc';

        try {
            if ($prepared !== null) {
                $diagnostics = $prepared['diagnostics'];
            } else {
                mkdir($tempDir, 0700, true);
                $failure = $this->importKey($tempDir, $armoredPublicKey, $expectedFingerprint, $timings, $diagnostics);
                if ($failure !== null) {
                    return $failure;
                }
                if ($this->reuseKeyHomes) {
                    $this->preparedHomes[$homeKey] = ['home' => $tempDir, 'diagnostics' => $diagnostics];
                    $keep = true;
                }
            }
            file_put_contents($textPath, $signedText);
            file_put_contents($signaturePath, $armoredDetachedSignature);

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
            if ($keep) {
                @unlink($textPath);
                @unlink($signaturePath);
            } else {
                $this->cleanup($tempDir);
            }
        }
    }

    /** Imports one key into an empty home; returns a failure result when GPG rejects it. */
    private function importKey(string $tempDir, string $armoredPublicKey, string $expectedFingerprint, array &$timings, array &$diagnostics): ?array
    {
        $keyPath = $tempDir . '/key.asc';
        file_put_contents($keyPath, $armoredPublicKey);

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

        return null;
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
        $command = [...$this->gpgCommand, '--batch', '--no-tty', '--no-options', '--no-autostart', '--homedir', $homedir, ...$arguments];
        $result = \ForumRewrite\Support\BoundedProcess::run($command, $this->budget,
            1048576 - $this->outputBytes, 'Signature verification');
        $this->outputBytes += $result['bytes'];
        $output = preg_split('/\\r?\\n/', trim($result['stdout'] . "\n" . $result['stderr'])) ?: [];
        $exitCode = $result['exit_code'];

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
        GpgHomeCleaner::dispose($path, stopAgents: false);
    }
}
