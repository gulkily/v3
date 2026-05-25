<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use RuntimeException;

final class UnicodeTextPolicy
{
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

        foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $this->assertVisibleCharacter($character, $field, $allowNewlines);
        }

        return $value;
    }

    private function assertVisibleCharacter(string $character, string $field, bool $allowNewlines): void
    {
        if ($character === "\n" && $allowNewlines) {
            return;
        }

        if (preg_match('/^[\x20-\x7E]$/', $character) === 1) {
            return;
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

        if (preg_match('/^[\p{L}\p{M}\p{N}\p{P}]$/u', $character) !== 1) {
            throw new RuntimeException("{$field} contains unsupported Unicode symbols.");
        }
    }
}
