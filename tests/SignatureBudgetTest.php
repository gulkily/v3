<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';
use ForumRewrite\Security\OpenPgpSignatureVerifier;
use ForumRewrite\Support\OperationLimitException;
final class SignatureBudgetTest
{
    public function testTimeoutAndAggregateOutputAreOperationalFailures(): void
    {
        $w = new ImportTestWorkspace();
        foreach (['sleep(30);', 'echo str_repeat("x",600000);'] as $body) {
            $script = $w->root . '/fake.php'; $w->put($script, '<?php ' . $body);
            $verifier = new OpenPgpSignatureVerifier([PHP_BINARY, $script], 0.3);
            $start = microtime(true);
            try {
                $verifier->verifyDetached(file_get_contents(__DIR__ . '/fixtures/openpgp_signature/reaction-public-key.asc'),
                    file_get_contents(__DIR__ . '/fixtures/openpgp_signature/signed-label.txt'),
                    file_get_contents(__DIR__ . '/fixtures/openpgp_signature/signed-label.txt.asc'), '066236E516A3B74EC51B3B423A26034600DE67FD');
                throw new LogicException('Expected operational failure');
            } catch (OperationLimitException $e) { assertStringContains('Signature verification', $e->getMessage()); }
            assertTrue(microtime(true) - $start < 2);
        }
    }
    public function testPlannerDoesNotConvertVerifierExhaustionIntoRejectedContent(): void
    {
        $w = new ImportTestWorkspace(); $source = $w->repository('source', true); $target = $w->repository('target');
        $path = $w->signedLabel($source);
        file_put_contents($source . '/' . $path . '.asc', "\n", FILE_APPEND);
        $w->put($w->root . '/bin/gpg', "#!/bin/sh\nexec /usr/bin/php -r 'echo str_repeat(\"x\",1100000);'\n");
        chmod($w->root . '/bin/gpg', 0700);
        $old = getenv('PATH'); putenv('PATH=' . $w->root . '/bin:' . $old);
        try {
            try { (new \ForumRewrite\Import\ContentImportPlanner())->plan($source, $target); throw new LogicException('Expected resource failure'); }
            catch (OperationLimitException $e) { assertStringContains('output exceeds', $e->getMessage()); }
        } finally { putenv('PATH=' . $old); }
    }
}
