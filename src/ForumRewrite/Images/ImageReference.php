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
            || strlen($description) > 500 || preg_match('/[\x00-\x1f\x7f]/', $description)
            || !mb_check_encoding($description, 'UTF-8')) {
            throw new RuntimeException('Invalid image reference or description (at most 500 bytes).');
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
        return self::PREFIX . ' ' . implode(' ', [$v['sha256'], $v['type'], $v['bytes'], $v['width'], $v['height']]) . ($v['description'] === '' ? '' : ' ' . rawurlencode($v['description']));
    }

    public static function fromBody(string $body): ?array
    {
        $found = null;
        foreach (explode("\n", $body) as $line) {
            if (!str_starts_with($line, self::PREFIX)) {
                continue;
            }
            if ($found !== null || !preg_match('/^Image-v1: ([a-f0-9]{64}) (png|jpg) ([0-9]+) ([0-9]+) ([0-9]+)(?: (\S+))?$/D', $line, $m)) {
                throw new RuntimeException('A post supports one well-formed Image-v1 reference.');
            }
            $found = self::validate(['sha256' => $m[1], 'type' => $m[2], 'bytes' => $m[3], 'width' => $m[4], 'height' => $m[5], 'description' => rawurldecode($m[6] ?? '')]);
            if (self::line($found) !== $line) {
                throw new RuntimeException('Image reference must use canonical encoding.');
            }
        }
        return $found;
    }

    /** Photo details are `Photo-<Label>: value` lines; only those directly after the Image-v1 line count. */
    public const PHOTO_DETAIL_LABELS = ['Camera', 'Lens', 'Exposure', 'Taken', 'Location'];

    /**
     * Splits the text that follows an Image-v1 line into its photo details and the rest.
     *
     * @return array{details: list<array{label: string, value: string, display: string}>, rest: string}
     */
    public static function photoDetails(string $after): array
    {
        $details = [];
        $pattern = '/\A\nPhoto-(' . implode('|', self::PHOTO_DETAIL_LABELS) . '): ([^\x00-\x1f\x7f]{1,120})(?=\n|\z)/u';
        while (preg_match($pattern, $after, $m) === 1) {
            $details[] = ['label' => $m[1], 'value' => $m[2], 'display' => self::photoDisplay($m[1], $m[2])];
            $after = substr($after, strlen($m[0]));
        }
        return ['details' => $details, 'rest' => $after];
    }

    /** The body without its image reference and photo details, for titles and excerpts. */
    public static function withoutReference(string $body): string
    {
        try { $image = self::fromBody($body); } catch (RuntimeException) { return $body; }
        if ($image === null) { return $body; }
        [$before, $after] = explode(self::line($image), $body, 2);
        return $before . self::photoDetails($after)['rest'];
    }

    private static function photoDisplay(string $label, string $value): string
    {
        if ($label === 'Taken' && preg_match('/^(\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d)(?:[+-]\d\d:\d\d)?$/D', $value, $m) === 1) {
            $taken = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $m[1], new \DateTimeZone('UTC'));
            if ($taken !== false && $taken->format('Y-m-d\TH:i:s') === $m[1]) { return $taken->format('M j, Y, g:i A'); }
        }
        if ($label === 'Location' && preg_match('/^(-?\d{1,2}\.\d+) (-?\d{1,3}\.\d+)$/D', $value, $m) === 1
            && abs((float) $m[1]) <= 90 && abs((float) $m[2]) <= 180) {
            $lat = (float) $m[1];
            $lon = (float) $m[2];
            return sprintf('%.4f° %s, %.4f° %s', abs($lat), $lat < 0 ? 'S' : 'N', abs($lon), $lon < 0 ? 'W' : 'E');
        }
        return $value;
    }

    public static function filename(array $reference): string
    {
        $r = self::validate($reference);
        return $r['sha256'] . '.' . $r['type'];
    }
}
