<?php

declare(strict_types=1);

use ForumRewrite\Support\UnicodeTextPolicy;

final class UnicodeTextPolicyTest
{
    public function testAllowsReadableCyrillicLineAndBody(): void
    {
        $policy = new UnicodeTextPolicy();

        assertSame('Привет', $policy->normalizeLine(' Привет ', 'subject'));
        assertSame("Привет мир\n", $policy->normalizeBody(" Привет мир ", 'body'));
    }

    public function testRejectsZeroWidthFormatCharacters(): void
    {
        $policy = new UnicodeTextPolicy();

        try {
            $policy->normalizeBody("hello\u{200B}world", 'body');
        } catch (RuntimeException $exception) {
            assertStringContains('control or format', $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected zero-width character rejection.');
    }

    public function testRejectsEmojiSymbols(): void
    {
        $policy = new UnicodeTextPolicy();

        try {
            $policy->normalizeBody('hello 🙂', 'body');
        } catch (RuntimeException $exception) {
            assertStringContains('unsupported Unicode symbols', $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected emoji rejection.');
    }
}
