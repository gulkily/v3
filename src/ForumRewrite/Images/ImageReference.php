<?php

declare(strict_types=1);

namespace ForumRewrite\Images;

use RuntimeException;

/** Portable text carried inside the signed post body; never a storage URL. */
final class ImageReference
{
    public const PREFIX = 'Image-v1:';
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_SIDE = 4096;

    public static function validate(array $value): array
    {
        $hash = (string) ($value['sha256'] ?? '');
        $type = (string) ($value['type'] ?? '');
        $description = (string) ($value['description'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $hash) || !in_array($type, ['png', 'jpg'], true)
            || trim($description) === '' || strlen($description) > 500 || preg_match('/[\x00-\x1f\x7f]/', $description)
            || !mb_check_encoding($description, 'UTF-8')) {
            throw new RuntimeException('Invalid image reference or description (1–500 bytes).');
        }
        foreach (['bytes' => self::MAX_BYTES, 'width' => self::MAX_SIDE, 'height' => self::MAX_SIDE] as $key => $max) {
            if (!isset($value[$key]) || filter_var($value[$key], FILTER_VALIDATE_INT) === false || (int) $value[$key] < 1 || (int) $value[$key] > $max) {
                throw new RuntimeException('Image exceeds 5 MiB or 4096 pixels per side.');
            }
        }
        return ['sha256' => $hash, 'type' => $type, 'bytes' => (int) $value['bytes'], 'width' => (int) $value['width'], 'height' => (int) $value['height'], 'description' => $description];
    }

    public static function line(array $value): string
    {
        $v = self::validate($value);
        return self::PREFIX . ' ' . implode(' ', [$v['sha256'], $v['type'], $v['bytes'], $v['width'], $v['height'], rawurlencode($v['description'])]);
    }

    public static function fromBody(string $body): ?array
    {
        $found = null;
        foreach (explode("\n", $body) as $line) {
            if (!str_starts_with($line, self::PREFIX)) {
                continue;
            }
            if ($found !== null || !preg_match('/^Image-v1: ([a-f0-9]{64}) (png|jpg) ([0-9]+) ([0-9]+) ([0-9]+) (\S+)$/D', $line, $m)) {
                throw new RuntimeException('A post supports one well-formed Image-v1 reference.');
            }
            $found = self::validate(['sha256' => $m[1], 'type' => $m[2], 'bytes' => $m[3], 'width' => $m[4], 'height' => $m[5], 'description' => rawurldecode($m[6])]);
            if (self::line($found) !== $line) {
                throw new RuntimeException('Image reference must use canonical encoding.');
            }
        }
        return $found;
    }

    public static function filename(array $reference): string
    {
        $r = self::validate($reference);
        return $r['sha256'] . '.' . $r['type'];
    }
}
