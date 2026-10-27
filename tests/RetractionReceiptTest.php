<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\{AcceptedRetraction, PostRecordParser, PostRetractionRecordParser, RetractionReceipt};

final class RetractionReceiptTest
{
    private function record(string $author = 'openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', string $signature = "signature\n"): array
    {
        $post = (new PostRecordParser())->parse("Post-ID: root\nCreated-At: 2026-10-25T12:00:00Z\nAuthor-Identity-ID: $author\nBoard-Tags: general\n\nsecret prose\n");
        $request = "Record-ID: post-retraction-root\nCreated-At: 2026-10-25T13:00:00Z\nPost-ID: root\nOperation: retract\nAuthor-Identity-ID: $author\n\n";
        return AcceptedRetraction::prepare($post, (new PostRetractionRecordParser())->parse($request), $request, $signature, 'author');
    }

    public function testReadableReceiptBindsExactEvidence(): void
    {
        $r = $this->record(); $text = RetractionReceipt::encode($r);
        assertSame(true, str_starts_with($text, "Version: 1\nRecord-ID: post-retraction-root\n"));
        assertSame(false, str_contains($text, 'secret prose'));
        assertSame(false, str_contains($text, 'signature\\n'));
        assertSame($r, RetractionReceipt::decode($text, 'root', $r['signed_request'], $r['signature']));
    }

    public function testMalformedOrSubstitutedEvidenceIsRejected(): void
    {
        $r = $this->record(); $text = RetractionReceipt::encode($r);
        foreach ([
            [$text . "body\n", 'root', $r['signed_request'], $r['signature']],
            [str_replace('Version: 1', "Version: 1\nVersion: 1", $text), 'root', $r['signed_request'], $r['signature']],
            [str_replace('Version: 1', "Version: 1\nExtra: value", $text), 'root', $r['signed_request'], $r['signature']],
            [str_replace('2026-10-25T12', '2026-02-31T12', $text), 'root', $r['signed_request'], $r['signature']],
            [$text, 'other', $r['signed_request'], $r['signature']],
            [$text, 'root', $r['signed_request'] . "\n", $r['signature']],
            [$text, 'root', $r['signed_request'], ''],
            [$text, 'root', $r['signed_request'], "replacement\n"],
        ] as $args) {
            $rejected = false;
            try { RetractionReceipt::decode(...$args); } catch (RuntimeException) { $rejected = true; }
            assertSame(true, $rejected);
        }
        $r['post']['body'] = 'secret prose';
        try { RetractionReceipt::encode($r); throw new LogicException('Accepted prose'); }
        catch (RuntimeException $e) { assertSame('Unexpected retraction metadata.', $e->getMessage()); }
    }

    public function testRealDetachedSignatureSurvivesReceiptRoundTrip(): void
    {
        $home = sys_get_temp_dir() . '/receipt-signature-' . bin2hex(random_bytes(6)); mkdir($home, 0700);
        $run = static function (array $args) use ($home): string {
            $pipes = []; $p = proc_open(array_merge(['gpg', '--homedir', $home, '--batch', '--pinentry-mode', 'loopback', '--passphrase', ''], $args), [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
            $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            if (proc_close($p) !== 0) throw new RuntimeException($err);
            return $out;
        };
        $run(['--quick-generate-key', 'Receipt test <receipt@example.invalid>', 'ed25519', 'sign', '0']);
        preg_match('/^fpr:::::::::([A-F0-9]+):/m', $run(['--with-colons', '--list-keys']), $match);
        $r = $this->record('openpgp:' . strtolower($match[1]));
        file_put_contents($home . '/request.txt', $r['signed_request']);
        $run(['--armor', '--detach-sign', $home . '/request.txt']);
        $r['signature'] = file_get_contents($home . '/request.txt.asc');
        $read = RetractionReceipt::decode(RetractionReceipt::encode($r), 'root', $r['signed_request'], $r['signature']);
        file_put_contents($home . '/roundtrip.txt', $read['signed_request']);
        file_put_contents($home . '/roundtrip.asc', $read['signature']);
        $run(['--verify', $home . '/roundtrip.asc', $home . '/roundtrip.txt']);
        assertSame($r, $read);
        $run(['--yes', '--delete-secret-and-public-key', $match[1]]);
    }
}
