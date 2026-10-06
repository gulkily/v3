<?php

declare(strict_types=1);

use ForumRewrite\Write\LocalWriteService;

require __DIR__ . '/../autoload.php';

final class IdentityBootstrapDiagnosticsTest
{
    public function testDiagnosticContextIsOptionalButValidatedWhenPresent(): void
    {
        $service = (new ReflectionClass(LocalWriteService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(LocalWriteService::class, 'identityBootstrapDiagnosticContext');
        $method->setAccessible(true);

        assertSame([], $method->invoke($service, []));
        assertSame([
            'bootstrap_attempt_id' => '0b60dfd0-0161-4d34-9e89-bc4089bb23c4',
            'bootstrap_retry_index' => 1,
            'openpgp_bundle_version' => 'v6.3.0',
        ], $method->invoke($service, [
            'bootstrap_attempt_id' => '0b60dfd0-0161-4d34-9e89-bc4089bb23c4',
            'bootstrap_retry_index' => '1',
            'openpgp_bundle_version' => 'v6.3.0',
        ]));

        try {
            $method->invoke($service, [
                'bootstrap_attempt_id' => 'not safe',
                'bootstrap_retry_index' => '1',
                'openpgp_bundle_version' => 'v6.3.0',
            ]);
            throw new RuntimeException('Expected invalid bootstrap attempt ID to be rejected.');
        } catch (RuntimeException $exception) {
            assertSame('bootstrap_attempt_id is invalid.', $exception->getMessage());
        }
    }

    public function testDiagnosticKeepsOnlySafeVerificationMetadata(): void
    {
        $method = new ReflectionMethod(LocalWriteService::class, 'identityBootstrapVerificationDiagnostic');
        $method->setAccessible(true);

        $diagnostic = (string) $method->invoke(null, [
            'ok' => false,
            'fingerprint' => 'abc123',
            'status' => 'signature_verification_failed',
            'details' => "[GNUPG:] NEWSIG\n[GNUPG:] BADSIG ABC123 Example User\n-----BEGIN PGP SIGNATURE-----\nprivate signature material",
            'timings' => [],
            'diagnostics' => [
                'import_exit_code' => 0,
                'import_accepted' => true,
                'import_result_counts' => [0, 0, 0],
                'import_debug_output' => ['[GNUPG:] IMPORT_RES 0 0 0', 'gpg: no valid OpenPGP data found.'],
                'gpg_import_started_at_epoch' => 1791310529,
                'public_key_packet_inspection_exit_code' => 0,
                'public_key_creation_epoch' => 1791310537,
                'public_key_creation_offset_seconds' => 8,
                'post_import_key_lookup_exit_code' => 2,
                'post_import_key_lookup_found' => false,
                'verification_exit_code' => 1,
                'validsig_present' => false,
                'gpg_status_codes' => ['NEWSIG', 'BADSIG'],
            ],
        ], 'def456', [
            'bootstrap_attempt_id' => '0b60dfd0-0161-4d34-9e89-bc4089bb23c4',
            'bootstrap_retry_index' => 1,
            'openpgp_bundle_version' => 'v6.3.0',
        ]);

        $payload = json_decode($diagnostic, true, flags: JSON_THROW_ON_ERROR);
        assertSame('identity_bootstrap_signature_verification_failed', $payload['event']);
        assertSame('signature_verification_failed', $payload['status']);
        assertSame('DEF456', $payload['expected_fingerprint']);
        assertSame('ABC123', $payload['reported_fingerprint']);
        assertSame(0, $payload['import_exit_code']);
        assertSame(true, $payload['import_accepted']);
        assertSame([0, 0, 0], $payload['import_result_counts']);
        assertSame(['[GNUPG:] IMPORT_RES 0 0 0', 'gpg: no valid OpenPGP data found.'], $payload['import_debug_output']);
        assertSame(1791310529, $payload['gpg_import_started_at_epoch']);
        assertSame(0, $payload['public_key_packet_inspection_exit_code']);
        assertSame(1791310537, $payload['public_key_creation_epoch']);
        assertSame(8, $payload['public_key_creation_offset_seconds']);
        assertSame(2, $payload['post_import_key_lookup_exit_code']);
        assertSame(false, $payload['post_import_key_lookup_found']);
        assertSame(1, $payload['verification_exit_code']);
        assertSame(false, $payload['validsig_present']);
        assertSame(['NEWSIG', 'BADSIG'], $payload['gpg_status_codes']);
        assertSame('0b60dfd0-0161-4d34-9e89-bc4089bb23c4', $payload['bootstrap_attempt_id']);
        assertSame(1, $payload['bootstrap_retry_index']);
        assertSame('v6.3.0', $payload['openpgp_bundle_version']);
        assertStringNotContains('Example User', $diagnostic);
        assertStringNotContains('BEGIN PGP SIGNATURE', $diagnostic);
        assertStringNotContains('private signature material', $diagnostic);
    }

    public function testSuccessfulVerificationDiagnosticUsesTheSameSafeCorrelationFields(): void
    {
        $method = new ReflectionMethod(LocalWriteService::class, 'identityBootstrapVerificationDiagnostic');
        $method->setAccessible(true);

        $diagnostic = (string) $method->invoke(null, [
            'ok' => true,
            'fingerprint' => 'abc123',
            'status' => 'ok',
            'details' => '',
            'timings' => [],
            'diagnostics' => [
                'import_exit_code' => 0,
                'import_accepted' => true,
                'import_result_counts' => null,
                'import_debug_output' => [],
                'gpg_import_started_at_epoch' => 1791310540,
                'public_key_packet_inspection_exit_code' => 0,
                'public_key_creation_epoch' => 1791310537,
                'public_key_creation_offset_seconds' => -3,
                'post_import_key_lookup_exit_code' => 0,
                'post_import_key_lookup_found' => true,
                'verification_exit_code' => 0,
                'validsig_present' => true,
                'gpg_status_codes' => ['IMPORT_OK', 'NEWSIG', 'VALIDSIG'],
            ],
        ], 'abc123', [
            'bootstrap_attempt_id' => '0b60dfd0-0161-4d34-9e89-bc4089bb23c4',
            'bootstrap_retry_index' => 2,
            'openpgp_bundle_version' => 'v6.3.0',
        ], 'identity_bootstrap_signature_verified');

        $payload = json_decode($diagnostic, true, flags: JSON_THROW_ON_ERROR);
        assertSame('identity_bootstrap_signature_verified', $payload['event']);
        assertSame('ok', $payload['status']);
        assertSame('ABC123', $payload['expected_fingerprint']);
        assertSame('ABC123', $payload['reported_fingerprint']);
        assertSame(0, $payload['import_exit_code']);
        assertSame(true, $payload['import_accepted']);
        assertSame(null, $payload['import_result_counts']);
        assertSame([], $payload['import_debug_output']);
        assertSame(1791310540, $payload['gpg_import_started_at_epoch']);
        assertSame(0, $payload['public_key_packet_inspection_exit_code']);
        assertSame(1791310537, $payload['public_key_creation_epoch']);
        assertSame(-3, $payload['public_key_creation_offset_seconds']);
        assertSame(0, $payload['post_import_key_lookup_exit_code']);
        assertSame(true, $payload['post_import_key_lookup_found']);
        assertSame(0, $payload['verification_exit_code']);
        assertSame(true, $payload['validsig_present']);
        assertSame(['IMPORT_OK', 'NEWSIG', 'VALIDSIG'], $payload['gpg_status_codes']);
        assertSame('0b60dfd0-0161-4d34-9e89-bc4089bb23c4', $payload['bootstrap_attempt_id']);
        assertSame(2, $payload['bootstrap_retry_index']);
        assertSame('v6.3.0', $payload['openpgp_bundle_version']);
    }
}
