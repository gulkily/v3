<?php

declare(strict_types=1);

use ForumRewrite\Support\UnicodeTextPolicy;

final class UnicodeTextPolicyTest
{
    public function testDiagnosticsUseOriginalCodePointPositions(): void
    {
        $prefix = class_exists(\Normalizer::class) ? "  Cafe\u{0301}\r\n" : "  Café\r\n";
        foreach (["→" => 'symbols', "\u{00A0}" => 'spacing', "\u{200B}" => 'control'] as $char => $message) {
            $input = $prefix . 'x' . $char . 'y' . $char . 'z ';
            try { (new UnicodeTextPolicy())->normalizeBody($input, 'body'); }
            catch (\ForumRewrite\Support\TextValidationException $error) {
                assertSame('body', $error->field);
                assertStringContains($message, $error->getMessage());
                assertSame(mb_strlen($prefix) + 1, $error->chars[0]['index']);
                assertSame(mb_strlen($prefix) + 3, $error->chars[1]['index']);
                assertSame($char, $error->chars[0]['char']);
                continue;
            }
            throw new RuntimeException('Expected detailed rejection.');
        }
        try { \ForumRewrite\Support\TextValidationException::ascii("  a\r\né→ ", "a\né→", 'body', true, 'body must be ASCII only.'); }
        catch (\ForumRewrite\Support\TextValidationException $error) {
            assertSame([5, 6], array_column($error->chars, 'index'));
            assertSame(['U+00E9', 'U+2192'], array_column($error->chars, 'codepoint'));
            return;
        }
        throw new RuntimeException('Expected ASCII diagnostics.');
    }

    public function testCombiningMarksRequireNormalizationSupport(): void
    {
        $policy = new UnicodeTextPolicy();
        if (class_exists(\Normalizer::class)) {
            assertSame('Café', $policy->normalizeLine("Cafe\u{0301}", 'subject'));
            return;
        }
        try {
            $policy->normalizeLine("Cafe\u{0301}", 'subject');
        } catch (RuntimeException $exception) {
            assertStringContains('normalization support is unavailable', $exception->getMessage());
            return;
        }
        throw new RuntimeException('Expected combining-mark rejection without intl.');
    }

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

    public function testAllowsEmojiSymbolsWhenEnabled(): void
    {
        $policy = new UnicodeTextPolicy(allowEmoji: true);

        assertSame("hello 🙂\n", $policy->normalizeBody('hello 🙂', 'body'));
    }

    public function testRejectsStandaloneEmojiJoinersWhenEmojiIsEnabled(): void
    {
        $policy = new UnicodeTextPolicy(allowEmoji: true);

        try {
            $policy->normalizeBody("hello\u{200D}world", 'body');
        } catch (RuntimeException $exception) {
            assertStringContains('control or format', $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected standalone emoji joiner rejection.');
    }
}
