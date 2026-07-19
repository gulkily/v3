<?php

declare(strict_types=1);

use ForumRewrite\Security\OpenPgpKeyInspector;
use ForumRewrite\Security\OpenPgpSignatureVerifier;

require __DIR__ . '/../autoload.php';

final class OpenPgpKeyInspectorTest
{
    public function testFallbackArmorParserExtractsFingerprintAndUsername(): void
    {
        $publicKey = (string) file_get_contents(__DIR__ . '/fixtures/parity_minimal_v1/records/public-keys/openpgp-0168FF20EB09C3EA6193BD3C92A73AA7D20A0954.asc');
        $inspector = new OpenPgpKeyInspector();
        $method = new ReflectionMethod(OpenPgpKeyInspector::class, 'inspectArmoredPublicKey');
        $method->setAccessible(true);

        $result = $method->invoke($inspector, $publicKey);

        assertSame('0168FF20EB09C3EA6193BD3C92A73AA7D20A0954', $result['fingerprint']);
        assertSame('forum-user', $result['username']);
    }

    public function testInspectorReportsGpgRecordShapeWhenFieldsAreMissing(): void
    {
        $inspector = new OpenPgpKeyInspector();
        $method = new ReflectionMethod(OpenPgpKeyInspector::class, 'compactGpgRecordSummary');
        $method->setAccessible(true);

        $result = $method->invoke($inspector, [
            'gpg: keybox ignored',
            'pub:-:255:22:F67D1E533FD2002F:1781031724:::-:::scESC:::::ed25519:::0:',
            'sub:-:255:18:AC4B2E27015C53D4:1781031724::::::e:::::cv25519::',
        ]);

        assertSame('pub(22/F67D1E533FD2002F), sub(18/AC4B2E27015C53D4)', $result);
    }

    public function testDetachedSignatureVerifierAcceptsValidSignature(): void
    {
        $verifier = new OpenPgpSignatureVerifier();

        $result = $verifier->verifyDetached(
            $this->readSignatureFixture('public-key.asc'),
            $this->readSignatureFixture('signed-post.txt'),
            $this->readSignatureFixture('signed-post.txt.asc'),
            '15c2ef95baee78f4c635a43323b6ae7ec7af9919'
        );

        assertSame(true, $result['ok']);
        assertSame('15C2EF95BAEE78F4C635A43323B6AE7EC7AF9919', $result['fingerprint']);
        assertSame('ok', $result['status']);
    }

    public function testDetachedSignatureVerifierRejectsTamperedText(): void
    {
        $verifier = new OpenPgpSignatureVerifier();

        $result = $verifier->verifyDetached(
            $this->readSignatureFixture('public-key.asc'),
            str_replace('Signed body.', 'Tampered body.', $this->readSignatureFixture('signed-post.txt')),
            $this->readSignatureFixture('signed-post.txt.asc'),
            '15c2ef95baee78f4c635a43323b6ae7ec7af9919'
        );

        assertSame(false, $result['ok']);
        assertSame('signature_verification_failed', $result['status']);
    }

    public function testDetachedSignatureVerifierRejectsWrongExpectedFingerprint(): void
    {
        $verifier = new OpenPgpSignatureVerifier();

        $result = $verifier->verifyDetached(
            $this->readSignatureFixture('public-key.asc'),
            $this->readSignatureFixture('signed-post.txt'),
            $this->readSignatureFixture('signed-post.txt.asc'),
            '0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'
        );

        assertSame(false, $result['ok']);
        assertSame('signer_fingerprint_mismatch', $result['status']);
        assertSame('15C2EF95BAEE78F4C635A43323B6AE7EC7AF9919', $result['fingerprint']);
    }

    public function testDetachedSignatureVerifierRejectsMalformedSignature(): void
    {
        $verifier = new OpenPgpSignatureVerifier();

        $result = $verifier->verifyDetached(
            $this->readSignatureFixture('public-key.asc'),
            $this->readSignatureFixture('signed-post.txt'),
            'not a signature',
            '15c2ef95baee78f4c635a43323b6ae7ec7af9919'
        );

        assertSame(false, $result['ok']);
        assertSame('invalid_signature', $result['status']);
    }

    private function readSignatureFixture(string $filename): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/openpgp_signature/' . $filename);
    }
}
