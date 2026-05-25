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

    public function testRejectsBidirectionalControls(): void
    {
        $policy = new UnicodeTextPolicy();

        try {
            $policy->normalizeBody("abc\u{202E}txt", 'body');
        } catch (RuntimeException $exception) {
            assertStringContains('control or format', $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected bidirectional control rejection.');
    }

    public function testRejectsPrivateUseCharacters(): void
    {
        $policy = new UnicodeTextPolicy();

        try {
            $policy->normalizeBody("private \u{E000}", 'body');
        } catch (RuntimeException $exception) {
            assertStringContains('control or format', $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected private-use character rejection.');
    }

    public function testRejectsNoncharacters(): void
    {
        $policy = new UnicodeTextPolicy();

        try {
            $policy->normalizeBody("noncharacter \u{FDD0}", 'body');
        } catch (RuntimeException $exception) {
            assertStringContains('noncharacters', $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected noncharacter rejection.');
    }

    public function testRejectsInvalidUtf8(): void
    {
        $policy = new UnicodeTextPolicy();

        try {
            $policy->normalizeBody("invalid \xC3\x28", 'body');
        } catch (RuntimeException $exception) {
            assertStringContains('valid UTF-8', $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected invalid UTF-8 rejection.');
    }

    public function testRejectsSubjectLineBreaksAndBodyControls(): void
    {
        $policy = new UnicodeTextPolicy();

        try {
            $policy->normalizeLine("hello\nworld", 'subject');
        } catch (RuntimeException $exception) {
            assertStringContains('single line', $exception->getMessage());
        }

        try {
            $policy->normalizeBody("hello\x01world", 'body');
        } catch (RuntimeException $exception) {
            assertStringContains('control or format', $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected body control rejection.');
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
