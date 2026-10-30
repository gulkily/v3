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
        foreach ([str_replace('Board-Tags: general','Board-Tags: identity approval',$this->record()), str_replace("Subject: Hello", "Imported-Score-Seed: 20\nSubject: Hello", $this->record()), $this->record(" hi\n"), $this->record("→\n"), str_replace("Subject: Hello", "Subject: Hello\nSubject: Hello", $this->record()), str_replace('20261030','20261031',$this->record())] as $invalid) {
            try { $v->validateRecord($invalid); throw new LogicException('Expected rejection'); } catch (RuntimeException) {}
        }
    }
    public function testBootstrapAndTimeBoundary(): void
    {
        $v = new SignedPostBundleValidator();
        $text = str_replace(['thread-', 'Board-Tags: general','Subject: Hello',"\n\nHello\n"], ['bootstrap-', 'Board-Tags: identity internal','Subject: account bootstrap',"\n\nAutomatic account bootstrap anchor.\n"],$this->record());
        $r=$v->validateRecord($text,true); $v->assertFresh($r,strtotime($r->createdAt)+600);
        try { $v->assertFresh($r,strtotime($r->createdAt)+601); throw new LogicException('Expected clock rejection'); } catch (RuntimeException) {}
        try { $v->validateRecord($text); throw new LogicException('Expected ordinary rejection'); } catch (RuntimeException) {}
    }
}
