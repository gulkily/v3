<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use RuntimeException;

/** Positions always refer to Unicode code points in the original submitted field. */
final class TextValidationException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field, public readonly array $chars)
    {
        parent::__construct($message);
    }

    public function details(): array
    {
        return ['field' => $this->field, 'chars' => $this->chars];
    }

    /** Map normalized character offsets back through trim, newline conversion and NFC. */
    public static function positions(string $original, bool $body, bool $unicode): array
    {
        $trimmed = trim($original);
        $offset = mb_strlen(substr($original, 0, strlen($original) - strlen(ltrim($original))), 'UTF-8');
        $chars = preg_split('//u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $text = ''; $map = [];
        foreach ($chars as $i => $char) {
            if ($body && $char === "\r" && (($chars[$i + 1] ?? '') === "\n" || $unicode)) {
                if (($chars[$i + 1] ?? '') === "\n") continue;
                $char = "\n";
            }
            $text .= $char;
            $map[] = $offset + $i;
        }
        if (!$unicode || !class_exists(\Normalizer::class)) return $map;
        preg_match_all('/\X/u', $text, $groups);
        $result = []; $at = 0;
        foreach ($groups[0] as $group) {
            $normalized = \Normalizer::normalize($group, \Normalizer::FORM_C);
            $length = mb_strlen($group, 'UTF-8');
            $decomposedMap = [];
            foreach (preg_split('//u', $group, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $j => $char) {
                $decomposed = \Normalizer::normalize($char, \Normalizer::FORM_D);
                for ($k = 0; $k < mb_strlen($decomposed, 'UTF-8'); $k++) $decomposedMap[] = $map[$at + $j];
            }
            $position = 0;
            foreach (preg_split('//u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
                $result[] = $decomposedMap[$position];
                $position += mb_strlen(\Normalizer::normalize($char, \Normalizer::FORM_D), 'UTF-8');
            }
            $at += $length;
        }
        return $result;
    }

    public static function character(string $char, int $index): array
    {
        return ['char' => $char, 'codepoint' => sprintf('U+%04X', mb_ord($char, 'UTF-8')), 'index' => $index];
    }

    public static function ascii(string $original, string $normalized, string $field, bool $body, string $message): void
    {
        if (!mb_check_encoding($original, 'UTF-8')) throw new RuntimeException("{$field} must be valid UTF-8.");
        if (preg_match($body ? '/[^\x0A\x20-\x7E]/' : '/[^\x20-\x7E]/', $normalized) === 0) return;
        $map = self::positions($original, $body, false); $bad = [];
        foreach (preg_split('//u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $i => $char) {
            if (preg_match($body ? '/^[\x0A\x20-\x7E]$/' : '/^[\x20-\x7E]$/', $char) !== 1) {
                $bad[] = self::character($char, $map[$i]);
            }
        }
        if ($bad !== []) throw new self($message, $field, $bad);
    }
}
