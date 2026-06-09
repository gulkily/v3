<?php

declare(strict_types=1);

use ForumRewrite\Security\OpenPgpKeyInspector;

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
}
