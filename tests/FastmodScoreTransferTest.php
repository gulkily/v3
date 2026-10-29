<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
use ForumRewrite\Scoring\FastmodScoreTransfer;

final class FastmodScoreTransferTest
{
    public static function row(): array
    {
        return ['post_id' => 'post-1', 'content_hash' => str_repeat('a', 64), 'rubric_revision' => str_repeat('b', 64), 'created_at' => '2025-01-01T00:00:00+00:00', 'updated_at' => '2025-01-02T00:00:00+00:00', 'scores' => ['moderation' => 0.9, 'quality' => 0.000001]];
    }

    public function testRoundTripAndAllowlist(): void
    {
        $row = self::row();
        $text = FastmodScoreTransfer::encode([$row + ['secret' => 'never-export']]);
        assertSame(true, [$row] == FastmodScoreTransfer::decode($text));
        assertSame(false, str_contains($text, 'never-export'));
        assertSame([], FastmodScoreTransfer::decode(FastmodScoreTransfer::encode([])));
    }

    public function testRejectsWholeMalformedSupplement(): void
    {
        $valid = FastmodScoreTransfer::encode([self::row()]);
        foreach ([str_replace('v1', 'v2', $valid), $valid . "Secret: leak\n", str_replace('0.9', '1.2', $valid), str_replace('0.9', 'NaN', $valid), str_replace('2025-01-02', '2025-02-30', $valid), $valid . "Post: post-1\n", str_repeat('x', FastmodScoreTransfer::MAX_BYTES + 1)] as $text) {
            try { FastmodScoreTransfer::decode($text); throw new RuntimeException('Accepted invalid supplement'); }
            catch (InvalidArgumentException) {}
        }
    }
}
