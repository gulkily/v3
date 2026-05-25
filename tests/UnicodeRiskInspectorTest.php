<?php

declare(strict_types=1);

use ForumRewrite\Analysis\UnicodeRiskInspector;

final class UnicodeRiskInspectorTest
{
    public function testPlainAsciiHasNoRiskLabels(): void
    {
        $inspection = (new UnicodeRiskInspector())->inspectPost('Hello', "Plain body\n");

        assertSame([], $inspection['fields']['subject']['risk_labels']);
        assertSame(['Latin'], $inspection['fields']['body']['scripts_present']);
        assertSame(false, $inspection['fields']['body']['has_multiple_scripts']);
        assertSame(false, $inspection['fields']['body']['unsafe_rejected']['rejected']);
    }

    public function testPlainCyrillicIsReadableWithoutMixedScriptRisk(): void
    {
        $inspection = (new UnicodeRiskInspector())->inspectPost('Привет', "Привет мир\n");

        assertSame(['Cyrillic'], $inspection['fields']['subject']['scripts_present']);
        assertSame([], $inspection['fields']['subject']['risk_labels']);
        assertSame(false, $inspection['fields']['subject']['unsafe_rejected']['rejected']);
    }

    public function testMixedLatinAndCyrillicIdentifierLikeTextIsFlagged(): void
    {
        $inspection = (new UnicodeRiskInspector())->inspectPost('Look at раypal.com', 'Body');
        $labels = $inspection['fields']['subject']['risk_labels'];

        assertSame(true, in_array('mixed_script', $labels, true));
        assertSame(true, in_array('confusable_identifier_like_text', $labels, true));
    }

    public function testRightToLeftTextIsFlaggedWithoutBeingRejected(): void
    {
        $inspection = (new UnicodeRiskInspector())->inspectPost('שלום', 'Body');
        $subject = $inspection['fields']['subject'];

        assertSame(['Hebrew'], $subject['scripts_present']);
        assertSame(true, $subject['has_right_to_left']);
        assertSame(true, in_array('directionality_risk', $subject['risk_labels'], true));
        assertSame(false, $subject['unsafe_rejected']['rejected']);
    }

    public function testUnsafeInvisibleCharactersAreSummarizedByCodePoint(): void
    {
        $inspection = (new UnicodeRiskInspector())->inspectPost('Hello', "hello\u{200B}world");
        $body = $inspection['fields']['body'];

        assertSame(true, $body['unsafe_rejected']['rejected']);
        assertSame(true, in_array('unsafe_rejected', $body['risk_labels'], true));
        assertSame(true, in_array('invisible_or_spacing_risk', $body['risk_labels'], true));
        assertSame('U+200B', $body['suspicious_code_points'][0]['code_point']);
        assertSame(true, in_array('zero_width', $body['suspicious_code_points'][0]['labels'], true));
    }

    public function testCombiningMarksSurfaceNormalizationRiskWhenNormalizerIsUnavailable(): void
    {
        $inspection = (new UnicodeRiskInspector())->inspectPost("Cafe\u{0301}", 'Body');
        $subject = $inspection['fields']['subject'];

        assertSame(true, in_array('normalization_risk', $subject['risk_labels'], true));
        assertSame('U+0301', $subject['suspicious_code_points'][0]['code_point']);
        assertSame(true, in_array('combining_mark', $subject['suspicious_code_points'][0]['labels'], true));
    }
}
