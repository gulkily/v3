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
}
