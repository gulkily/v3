<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

use ForumRewrite\Support\UnicodeTextPolicy;
use RuntimeException;

final class UnicodeRiskInspector
{
    private const MAX_FINDINGS_PER_FIELD = 20;

    /** @return array<string, mixed> */
    public function inspectPost(string $subject, string $body): array
    {
        return [
            'schema_version' => 1,
            'fields' => [
                'subject' => $this->inspectField('subject', $subject, false),
                'body' => $this->inspectField('body', $body, true),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function inspectField(string $field, string $value, bool $allowNewlines): array
    {
        $normalized = $this->nfcNormalizedValue($value);
        $analysisValue = is_string($normalized['value']) ? $normalized['value'] : $value;
        $characters = $this->characters($analysisValue);
        $scripts = $this->scriptsPresent($characters);
        $classCounts = $this->classCounts($characters);
        $suspicious = $this->suspiciousCodePoints($characters);
        $unsafe = $this->unsafeRejected($field, $value, $allowNewlines);

        return [
            'field' => $field,
            'normalized_form' => 'NFC',
            'normalization_available' => $normalized['available'],
            'changed_under_normalization' => $normalized['changed'],
            'character_count' => count($characters),
            'scripts_present' => $scripts,
            'class_counts' => $classCounts,
            'has_right_to_left' => $this->hasRightToLeft($characters),
            'has_multiple_scripts' => count($scripts) > 1,
            'unsafe_rejected' => $unsafe,
            'suspicious_code_points' => array_values($suspicious),
            'suspicious_code_points_truncated' => count($suspicious) >= self::MAX_FINDINGS_PER_FIELD,
            'risk_labels' => $this->riskLabels($scripts, $characters, $suspicious, $unsafe, $normalized),
        ];
    }

    /** @return array{available: bool, changed: bool|null, value: string|null} */
    private function nfcNormalizedValue(string $value): array
    {
        if (!class_exists(\Normalizer::class)) {
            return ['available' => false, 'changed' => null, 'value' => null];
        }

        $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
        if (!is_string($normalized)) {
            return ['available' => true, 'changed' => null, 'value' => null];
        }

        return ['available' => true, 'changed' => $normalized !== $value, 'value' => $normalized];
    }

    /** @return list<string> */
    private function characters(string $value): array
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            return [];
        }

        return preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** @param list<string> $characters @return list<string> */
    private function scriptsPresent(array $characters): array
    {
        $scripts = [];
        foreach ($characters as $character) {
            $script = $this->scriptFor($character);
            if ($script === null) {
                continue;
            }
            $scripts[$script] = true;
        }

        $names = array_keys($scripts);
        sort($names);
        return $names;
    }

    private function scriptFor(string $character): ?string
    {
        $scripts = [
            'Arabic',
            'Armenian',
            'Bengali',
            'Cyrillic',
            'Devanagari',
            'Georgian',
            'Greek',
            'Han',
            'Hangul',
            'Hebrew',
            'Hiragana',
            'Katakana',
            'Latin',
            'Thai',
        ];

        foreach ($scripts as $script) {
            if (preg_match('/^\p{' . $script . '}$/u', $character) === 1) {
                return $script;
            }
        }

        return null;
    }

    /** @param list<string> $characters @return array<string, int> */
    private function classCounts(array $characters): array
    {
        $counts = [
            'letters' => 0,
            'marks' => 0,
            'numbers' => 0,
            'punctuation' => 0,
            'separators' => 0,
            'controls_or_format' => 0,
            'symbols' => 0,
            'other' => 0,
        ];

        foreach ($characters as $character) {
            $counts[$this->broadClass($character)]++;
        }

        return $counts;
    }

    private function broadClass(string $character): string
    {
        if (preg_match('/^\p{L}$/u', $character) === 1) {
            return 'letters';
        }
        if (preg_match('/^\p{M}$/u', $character) === 1) {
            return 'marks';
        }
        if (preg_match('/^\p{N}$/u', $character) === 1) {
            return 'numbers';
        }
        if (preg_match('/^\p{P}$/u', $character) === 1) {
            return 'punctuation';
        }
        if (preg_match('/^\p{Z}$/u', $character) === 1) {
            return 'separators';
        }
        if (preg_match('/^\p{C}$/u', $character) === 1) {
            return 'controls_or_format';
        }
        if (preg_match('/^\p{S}$/u', $character) === 1) {
            return 'symbols';
        }

        return 'other';
    }

    /** @param list<string> $characters @return array<string, array<string, mixed>> */
    private function suspiciousCodePoints(array $characters): array
    {
        $findings = [];
        foreach ($characters as $character) {
            $labels = $this->codePointLabels($character);
            if ($labels === []) {
                continue;
            }

            $codePoint = $this->codePointNotation($character);
            if (!isset($findings[$codePoint])) {
                if (count($findings) >= self::MAX_FINDINGS_PER_FIELD) {
                    break;
                }
                $findings[$codePoint] = [
                    'code_point' => $codePoint,
                    'count' => 0,
                    'labels' => $labels,
                ];
            }
            $findings[$codePoint]['count']++;
        }

        return $findings;
    }

    /** @return list<string> */
    private function codePointLabels(string $character): array
    {
        $labels = [];

        if ($this->isNoncharacter($character)) {
            $labels[] = 'noncharacter';
        }
        if (preg_match('/^\p{C}$/u', $character) === 1) {
            $labels[] = 'control_or_format';
        }
        if (preg_match('/^\p{Z}$/u', $character) === 1 && $character !== ' ') {
            $labels[] = 'unusual_spacing';
        }
        if ($this->isBidiControl($character)) {
            $labels[] = 'bidirectional_control';
        }
        if ($this->isZeroWidth($character)) {
            $labels[] = 'zero_width';
        }
        if (preg_match('/^\p{M}$/u', $character) === 1) {
            $labels[] = 'combining_mark';
        }
        if (preg_match('/^\p{S}$/u', $character) === 1) {
            $labels[] = 'symbol';
        }

        return array_values(array_unique($labels));
    }

    private function isNoncharacter(string $character): bool
    {
        return preg_match('/^[\x{FDD0}-\x{FDEF}\x{FFFE}\x{FFFF}\x{1FFFE}\x{1FFFF}\x{2FFFE}\x{2FFFF}\x{3FFFE}\x{3FFFF}\x{4FFFE}\x{4FFFF}\x{5FFFE}\x{5FFFF}\x{6FFFE}\x{6FFFF}\x{7FFFE}\x{7FFFF}\x{8FFFE}\x{8FFFF}\x{9FFFE}\x{9FFFF}\x{AFFFE}\x{AFFFF}\x{BFFFE}\x{BFFFF}\x{CFFFE}\x{CFFFF}\x{DFFFE}\x{DFFFF}\x{EFFFE}\x{EFFFF}\x{FFFFE}\x{FFFFF}\x{10FFFE}\x{10FFFF}]$/u', $character) === 1;
    }

    private function isBidiControl(string $character): bool
    {
        return preg_match('/^[\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]$/u', $character) === 1;
    }

    private function isZeroWidth(string $character): bool
    {
        return preg_match('/^[\x{200B}-\x{200D}\x{2060}\x{FEFF}]$/u', $character) === 1;
    }

    /** @param list<string> $characters */
    private function hasRightToLeft(array $characters): bool
    {
        foreach ($characters as $character) {
            if (preg_match('/^[\p{Arabic}\p{Hebrew}]$/u', $character) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return array{rejected: bool, reason: string} */
    private function unsafeRejected(string $field, string $value, bool $allowNewlines): array
    {
        try {
            $policy = new UnicodeTextPolicy();
            if ($allowNewlines) {
                $policy->normalizeBody($value, $field);
            } else {
                $policy->normalizeLine($value, $field);
            }
        } catch (RuntimeException $exception) {
            return ['rejected' => true, 'reason' => $exception->getMessage()];
        }

        return ['rejected' => false, 'reason' => ''];
    }

    /**
     * @param list<string> $scripts
     * @param list<string> $characters
     * @param array<string, array<string, mixed>> $suspicious
     * @param array{rejected: bool, reason: string} $unsafe
     * @param array{available: bool, changed: bool|null, value: string|null} $normalized
     * @return list<string>
     */
    private function riskLabels(array $scripts, array $characters, array $suspicious, array $unsafe, array $normalized): array
    {
        $labels = [];

        if ($unsafe['rejected']) {
            $labels[] = 'unsafe_rejected';
        }
        if (count($scripts) > 1) {
            $labels[] = 'mixed_script';
        }
        if ($this->hasRightToLeft($characters)) {
            $labels[] = 'directionality_risk';
        }
        foreach ($suspicious as $finding) {
            $findingLabels = is_array($finding['labels'] ?? null) ? $finding['labels'] : [];
            if (array_intersect($findingLabels, ['zero_width', 'unusual_spacing', 'control_or_format']) !== []) {
                $labels[] = 'invisible_or_spacing_risk';
            }
            if (in_array('combining_mark', $findingLabels, true)) {
                $labels[] = 'normalization_risk';
            }
        }
        if ($normalized['changed'] === true) {
            $labels[] = 'normalization_risk';
        }
        if ($this->hasConfusableIdentifierLikeText($characters, $scripts)) {
            $labels[] = 'confusable_identifier_like_text';
        }

        return array_values(array_unique($labels));
    }

    /** @param list<string> $characters @param list<string> $scripts */
    private function hasConfusableIdentifierLikeText(array $characters, array $scripts): bool
    {
        if (count($scripts) < 2 || !in_array('Latin', $scripts, true)) {
            return false;
        }

        $text = implode('', $characters);
        if (preg_match('/[@#\/\\\\._:-]|https?:|www\.|[A-Za-z0-9][\p{L}\p{N}._:-]{2,}/u', $text) !== 1) {
            return false;
        }

        return true;
    }

    private function codePointNotation(string $character): string
    {
        $codePoint = mb_ord($character, 'UTF-8');
        return sprintf('U+%04X', $codePoint);
    }
}
