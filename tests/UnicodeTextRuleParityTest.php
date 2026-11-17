<?php

declare(strict_types=1);

use ForumRewrite\Support\TextValidationException;
use ForumRewrite\Support\UnicodeTextPolicy;

/**
 * The compose-time check in public/assets/browser_signing.js is a hand-synced twin of the server rules
 * (UnicodeTextPolicy, and the ASCII check in LocalWriteService::normalizeAsciiBody). This test runs one
 * corpus through both and fails when they drift apart: the browser must never block text the server
 * accepts, and the server must accept whatever the browser lets through, with or without removal.
 *
 * The corpus uses only characters assigned by Unicode 14 (plus code points that stay unassigned), so the
 * result does not depend on the PCRE or ICU version. Characters added since Unicode 14 can still differ at
 * runtime; see docs/plans/unicode_text_rule_parity/unicode_text_rule_parity_one_shot.md.
 */
final class UnicodeTextRuleParityTest
{
    public function testBrowserAndServerAgreeInAsciiMode(): void
    {
        $this->assertModeAgrees('ascii');
    }

    public function testBrowserAndServerAgreeInUnicodeMode(): void
    {
        $this->assertModeAgrees('unicode');
    }

    public function testBrowserAndServerAgreeInUnicodeModeWithEmoji(): void
    {
        $this->assertModeAgrees('emoji');
    }

    private function assertModeAgrees(string $mode): void
    {
        $texts = $this->corpus();
        $browser = $this->browserResults($mode, $texts);
        $problems = [];
        foreach ($texts as $index => $text) {
            [$checked, $removed] = $browser[$index];
            $label = $this->describe($text);
            if ($checked['unsupportedCount'] > 0) {
                if ($this->serverAccepts($mode, $text)) $problems[] = "browser blocks text the server accepts: {$label}";
            } elseif (!$this->serverAccepts($mode, $checked['text'])) {
                $problems[] = "server rejects text the browser lets through: {$label}";
            }
            if ($removed['text'] !== '' && !$this->serverAccepts($mode, $removed['text'])) {
                $problems[] = "server rejects text after the browser removed unsupported characters: {$label}";
            }
        }
        assertSame([], $problems);
    }

    private function serverAccepts(string $mode, string $text): bool
    {
        if (trim($text) === '') return false;
        try {
            if ($mode === 'ascii') {
                // Same steps as LocalWriteService::normalizeAsciiBody, which is private.
                TextValidationException::ascii($text, str_replace("\r\n", "\n", trim($text)), 'body', true, 'body must be ASCII only.');
            } else {
                (new UnicodeTextPolicy($mode === 'emoji'))->normalizeBody($text, 'body');
            }
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @param list<string> $texts
     * @return list<array{0: array{text: string, unsupportedCount: int}, 1: array{text: string}}>
     */
    private function browserResults(string $mode, array $texts): array
    {
        $script = <<<'NODE'
global.window = {};
global.localStorage = { getItem(){ return ''; }, setItem(){}, removeItem(){} };
global.document = { addEventListener(){}, querySelector(){ return null; } };
global.navigator = {};
const fs = require('fs');
const vm = require('vm');
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
const normalize = window.__forumComposeNormalization.normalizeComposeText;
const mode = process.argv[2];
const options = { allowUnicodeAuthoredText: mode !== 'ascii', allowEmojiAuthoredText: mode === 'emoji' };
const texts = JSON.parse(fs.readFileSync(0, 'utf8'));
process.stdout.write(JSON.stringify(texts.map(text => [
  normalize(text, options),
  normalize(text, Object.assign({ removeUnsupported: true }, options)),
])));
NODE;
        $process = proc_open(
            ['node', '-e', $script, __DIR__ . '/../public/assets/browser_signing.js', $mode],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) throw new RuntimeException('Could not start node.');
        fwrite($pipes[0], json_encode($texts, JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Node helper execution failed: ' . $errors);

        return json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    private function corpus(): array
    {
        $codePoints = array_merge(
            range(0x00, 0x24F),     // ASCII, Latin-1, Latin Extended-A and -B, including every control
            range(0x2000, 0x206F),  // spaces, joiners, bidi controls, typographic punctuation; U+2065 stays unassigned
            range(0x2190, 0x21FF),  // arrows: text-default symbols, some emoji bases
            range(0x2600, 0x26FF),  // miscellaneous symbols: mixed emoji presentation
            [0x0410, 0x044F, 0x05D0, 0x0627, 0x0915, 0x0E01, 0x3042, 0x4E2D, 0xAC00, 0x1D400],  // letters in other scripts
            [0x0301, 0x0308, 0x093F],                  // combining marks
            [0x1680, 0x3000, 0xFEFF, 0xFFFC, 0xFFFD],  // ogham and ideographic space, BOM, replacement characters
            [0xE000, 0xF8FF, 0xF0000, 0x10FFFD],       // private use
            [0xFDD0, 0xFDEF, 0xFFFE, 0xFFFF, 0x1FFFE, 0x10FFFF],  // noncharacters
            [0xFE0E, 0xFE0F, 0x20E3, 0xE0001, 0xE0041],  // variation selectors, keycap, tags
            [0x1F600, 0x1F642, 0x1F44D, 0x1F3FD, 0x1F1FA, 0x2764, 0x00A9, 0x2122, 0x3030, 0x1FA70],  // emoji
        );
        $texts = [];
        foreach ($codePoints as $codePoint) {
            if (!class_exists(\Normalizer::class) && preg_match('/\p{M}/u', mb_chr($codePoint, 'UTF-8')) === 1) continue;
            $texts[] = 'a' . mb_chr($codePoint, 'UTF-8') . 'b';
        }

        $sequences = [
            "\u{1F44D}\u{1F3FD}",                        // skin tone modifier
            "\u{2764}\u{FE0F}",                          // emoji presentation selector
            "1\u{FE0F}\u{20E3}",                         // keycap
            "\u{1F469}\u{200D}\u{1F4BB}",                // joiner between two emoji
            "\u{1F1FA}\u{1F1F8}",                        // flag
            "a\u{200D}b", "\u{200D}", "\u{FE0F}", "\u{20E3}",  // joiners and selectors with nothing to join
            "\u{2192}\u{FE0F}",                          // text symbol forced to emoji
            "x\u{1F642}\u{200D}",                        // trailing joiner
        ];
        if (class_exists(\Normalizer::class)) $sequences[] = "Cafe\u{0301}";  // NFC composes to é
        foreach ($sequences as $sequence) $texts[] = 'a' . $sequence . 'b';

        // Line handling: CRLF and lone CR, tabs, and text that is only unsupported characters.
        array_push($texts, "line one\r\nline two", "line one\rline two", "a\tb", "\u{0003}04red\u{000F}", "\u{1F642}");

        return $texts;
    }

    private function describe(string $text): string
    {
        $codePoints = array_map(
            static fn (string $character): string => sprintf('U+%04X', mb_ord($character, 'UTF-8')),
            preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        );

        return implode(' ', $codePoints);
    }
}
