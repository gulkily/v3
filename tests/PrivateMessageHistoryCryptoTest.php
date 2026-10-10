<?php
declare(strict_types=1);
final class PrivateMessageHistoryCryptoTest
{
    public function testBothBundledVersionsTransferAndVerifyOriginals(): void
    {
        foreach (['openpgp.min.js', 'openpgp.v5.11.3.min.js'] as $bundle) {
            $process = proc_open(['node', 'tests/browser/private_message_history_crypto.cjs', $bundle], [1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname(__DIR__));
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            assertSame(0, proc_close($process), $output);
        }
    }
    public function testAutomaticDonorVisitKeepsSecretsInBrowser(): void
    {
        $process=proc_open(['node','tests/browser/private_message_history_donor.cjs'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
        $output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        assertSame(0,proc_close($process),$output);
    }
}
