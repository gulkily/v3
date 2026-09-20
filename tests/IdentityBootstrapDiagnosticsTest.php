<?php

declare(strict_types=1);

use ForumRewrite\Write\LocalWriteService;

require __DIR__ . '/../autoload.php';

final class IdentityBootstrapDiagnosticsTest
{
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
        ], 'def456');

        $payload = json_decode($diagnostic, true, flags: JSON_THROW_ON_ERROR);
        assertSame('identity_bootstrap_signature_verification_failed', $payload['event']);
        assertSame('signature_verification_failed', $payload['status']);
        assertSame('DEF456', $payload['expected_fingerprint']);
        assertSame('ABC123', $payload['reported_fingerprint']);
        assertSame(['NEWSIG', 'BADSIG'], $payload['gpg_status_codes']);
        assertStringNotContains('Example User', $diagnostic);
        assertStringNotContains('BEGIN PGP SIGNATURE', $diagnostic);
        assertStringNotContains('private signature material', $diagnostic);
    }
}
