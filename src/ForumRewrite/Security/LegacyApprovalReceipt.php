<?php

declare(strict_types=1);
namespace ForumRewrite\Security;

use RuntimeException;

/** Removable legacy storage adapter. Signed digest verification does not depend on it. */
final class LegacyApprovalReceipt
{
    public static function decode(string $bytes): array
    {
        $data = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new RuntimeException('Invalid legacy approval receipt.');
        ApprovalReceiptText::assertShape($data);
        // This schema has no repeated property names, arrays or nested context values.
        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"\s*:/', $bytes, $matches);
        $seen = [];
        foreach ($matches[1] as $encoded) {
            $key = json_decode('"' . $encoded . '"', true, 32, JSON_THROW_ON_ERROR);
            if (isset($seen[$key])) throw new RuntimeException('Duplicate legacy approval receipt field.');
            $seen[$key] = true;
        }
        return $data;
    }
}
