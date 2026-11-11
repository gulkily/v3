<?php
require_once __DIR__ . '/../autoload.php';
use ForumRewrite\Write\SignedPostBundleValidator;
final class SignedPostBundleValidatorTest
{
    private function record(string $body = "Hello\n"): string
    {
        return 'Post-ID: thread-20261030000000-' . str_repeat('a',32) . "\nCreated-At: 2026-10-30T00:00:00Z\nBoard-Tags: general\nAuthor-Identity-ID: openpgp:" . str_repeat('b',40) . "\nSubject: Hello\n\n" . $body;
    }
    public function testStrictSubsetAndExactBytes(): void
    {
        $v = new SignedPostBundleValidator(true);
        assertSame("Café — 5 µg, “quoted”, Привет\n", $v->validateRecord($this->record("Café — 5 µg, “quoted”, Привет\n"))->body);
        foreach ([str_replace('Board-Tags: general','Board-Tags: identity approval',$this->record()), str_replace("Subject: Hello", "Imported-Score-Seed: 20\nSubject: Hello", $this->record()), $this->record(" hi\n"), $this->record("🙂\n"), str_replace("Subject: Hello", "Subject: Hello\nSubject: Hello", $this->record()), str_replace('20261030','20261031',$this->record())] as $invalid) {
            try { $v->validateRecord($invalid); throw new LogicException('Expected rejection'); } catch (RuntimeException) {}
        }
    }
    public function testReorderedHeadersAreAcceptedAsSigned(): void
    {
        $v = new SignedPostBundleValidator(true);
        $reordered = 'Subject: Hello' . "\nAuthor-Identity-ID: openpgp:" . str_repeat('b',40) . "\nBoard-Tags: general\nCreated-At: 2026-10-30T00:00:00Z\nPost-ID: thread-20261030000000-" . str_repeat('a',32) . "\n\nHello\n";
        assertSame('Hello', $v->validateRecord($reordered)->subject);
        foreach ([
            str_replace("Subject: Hello", "Imported-Score-Seed: 20\nSubject: Hello", $reordered),
            str_replace("\n\nHello\n", "\n\nHello again\n", $reordered) . 'x',
            str_replace("Board-Tags: general\n", '', $reordered),
        ] as $invalid) {
            try { $v->validateRecord($invalid); throw new LogicException('Expected rejection'); } catch (RuntimeException) {}
        }
    }
    public function testOptionalThemeHeaderFollowsBodyFormat(): void
    {
        $v = new SignedPostBundleValidator(true);
        assertSame('midnight', $v->validateRecord(str_replace("Subject: Hello\n", "Subject: Hello\nBody-Format: markdown\nTheme: midnight\n", $this->record()))->theme);
        assertSame('none', $v->validateRecord(str_replace("Subject: Hello\n", "Subject: Hello\nTheme: none\n", $this->record()))->theme);
        $reply = str_replace(['thread-20261030000000-' . str_repeat('a', 32), "Board-Tags: general\n", "Subject: Hello\n"], ['reply-20261030000000-' . str_repeat('c', 32), "Board-Tags: general\nThread-ID: root-001\nParent-ID: root-001\n", "Body-Format: plain\nTheme: word97\n"], $this->record());
        assertSame('word97', $v->validateRecord($reply)->theme);
        // Header order is not checked: every reader parses headers in any order.
        foreach ([
            str_replace("Subject: Hello\n", "Subject: Hello\nTheme: midnight\nBody-Format: markdown\n", $this->record()),
            str_replace("Subject: Hello\n", "Theme: midnight\nSubject: Hello\n", $this->record()),
        ] as $reordered) {
            assertSame('midnight', $v->validateRecord($reordered)->theme);
        }
        foreach ([
            str_replace("Subject: Hello\n", "Subject: Hello\nTheme: midnight\nTheme: midnight\n", $this->record()),
            str_replace("Subject: Hello\n", "Subject: Hello\nTheme: Midnight\n", $this->record()),
        ] as $invalid) {
            try { $v->validateRecord($invalid); throw new LogicException('Expected rejection'); } catch (RuntimeException) {}
        }
    }
    public function testBootstrapAndTimeBoundary(): void
    {
        $v = new SignedPostBundleValidator();
        $text = str_replace(['thread-', 'Board-Tags: general','Subject: Hello',"\n\nHello\n"], ['bootstrap-', 'Board-Tags: identity internal','Subject: account bootstrap',"\n\nAutomatic account bootstrap anchor.\n"],$this->record());
        $r=$v->validateRecord($text,true);
        if (\ForumRewrite\Canonical\RecordReceipt::isSkewed($r->createdAt, strtotime($r->createdAt)+600, 600)) throw new LogicException('600 seconds is in the window');
        if (!\ForumRewrite\Canonical\RecordReceipt::isSkewed($r->createdAt, strtotime($r->createdAt)+601, 600)) throw new LogicException('601 seconds needs a receipt');
        try { $v->validateRecord($text); throw new LogicException('Expected ordinary rejection'); } catch (RuntimeException) {}
    }
}
