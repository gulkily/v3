<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use RuntimeException;

final class UnicodeTextPolicy
{
    public function __construct(
        private readonly bool $allowEmoji = false,
    ) {
    }

    public function normalizeLine(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new RuntimeException("{$field} must be a single line.");
        }

        return $this->normalizeVisibleUnicode($value, $field, false);
    }

    public function normalizeBody(string $value, string $field): string
    {
        $value = str_replace("\r\n", "\n", trim($value));
        $value = str_replace("\r", "\n", $value);
        if ($value === '') {
            throw new RuntimeException("{$field} is required.");
        }

        return $this->normalizeVisibleUnicode($value, $field, true) . "\n";
    }

    private function normalizeVisibleUnicode(string $value, string $field, bool $allowNewlines): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw new RuntimeException("{$field} must be valid UTF-8.");
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if (!is_string($normalized)) {
                throw new RuntimeException("{$field} could not be normalized.");
            }
            $value = $normalized;
        } elseif (preg_match('/\p{M}/u', $value) === 1) {
            throw new RuntimeException("{$field} contains combining marks, but Unicode normalization support is unavailable.");
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($characters as $index => $character) {
            $this->assertVisibleCharacter(
                $character,
                $field,
                $allowNewlines,
                $characters[$index - 1] ?? null,
                $characters[$index + 1] ?? null,
            );
        }

        return $value;
    }

    private function assertVisibleCharacter(
        string $character,
        string $field,
        bool $allowNewlines,
        ?string $previousCharacter,
        ?string $nextCharacter,
    ): void
    {
        if ($character === "\n" && $allowNewlines) {
            return;
        }

        if (preg_match('/^[\x20-\x7E]$/', $character) === 1) {
            return;
        }

        if ($this->isEmojiSequenceCharacter($character)) {
            if ($this->allowEmoji && $this->isAllowedEmojiSequenceCharacter($character, $previousCharacter, $nextCharacter)) {
                return;
            }

            throw new RuntimeException("{$field} contains unsupported Unicode control or format characters.");
        }

        if (preg_match('/[\x{FDD0}-\x{FDEF}\x{FFFE}\x{FFFF}\x{1FFFE}\x{1FFFF}\x{2FFFE}\x{2FFFF}\x{3FFFE}\x{3FFFF}\x{4FFFE}\x{4FFFF}\x{5FFFE}\x{5FFFF}\x{6FFFE}\x{6FFFF}\x{7FFFE}\x{7FFFF}\x{8FFFE}\x{8FFFF}\x{9FFFE}\x{9FFFF}\x{AFFFE}\x{AFFFF}\x{BFFFE}\x{BFFFF}\x{CFFFE}\x{CFFFF}\x{DFFFE}\x{DFFFF}\x{EFFFE}\x{EFFFF}\x{FFFFE}\x{FFFFF}\x{10FFFE}\x{10FFFF}]/u', $character) === 1) {
            throw new RuntimeException("{$field} contains unsupported Unicode noncharacters.");
        }

        if (preg_match('/[\p{C}]/u', $character) === 1) {
            throw new RuntimeException("{$field} contains unsupported Unicode control or format characters.");
        }

        if (preg_match('/[\p{Z}]/u', $character) === 1) {
            throw new RuntimeException("{$field} contains unsupported Unicode spacing characters.");
        }

        if ($this->allowEmoji && $this->isEmojiBaseCharacter($character)) {
            return;
        }

        if (preg_match('/^[\p{L}\p{M}\p{N}\p{P}]$/u', $character) !== 1) {
            throw new RuntimeException("{$field} contains unsupported Unicode symbols.");
        }
    }

    private function isEmojiBaseCharacter(string $character): bool
    {
        return preg_match('/^[\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{21AA}\x{231A}-\x{231B}\x{2328}\x{23CF}\x{23E9}-\x{23F3}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}-\x{25AB}\x{25B6}\x{25C0}\x{25FB}-\x{25FE}\x{2600}-\x{27BF}\x{2934}-\x{2935}\x{2B05}-\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F000}-\x{1FAFF}]$/u', $character) === 1;
    }

    private function isEmojiSequenceCharacter(string $character): bool
    {
        return preg_match('/^[\x{200D}\x{20E3}\x{FE0F}]$/u', $character) === 1;
    }

    private function isAllowedEmojiSequenceCharacter(string $character, ?string $previousCharacter, ?string $nextCharacter): bool
    {
        if ($character === "\u{200D}") {
            return $previousCharacter !== null
                && $nextCharacter !== null
                && $this->isEmojiBaseCharacter($previousCharacter)
                && $this->isEmojiBaseCharacter($nextCharacter);
        }

        if ($character === "\u{20E3}") {
            return $previousCharacter !== null && preg_match('/^[0-9#*]$/', $previousCharacter) === 1;
        }

        return ($previousCharacter !== null && $this->isEmojiBaseCharacter($previousCharacter))
            || ($nextCharacter !== null && $this->isEmojiBaseCharacter($nextCharacter));
    }
}
