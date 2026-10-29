<?php

declare(strict_types=1);
namespace ForumRewrite\Security;

use RuntimeException;

/** Readable storage; context header order preserves the signed JSON digest preimage. */
final class ApprovalReceiptText
{
    public const FIELDS = [
        'actor_identity_id', 'actor_username', 'target_identity_id', 'target_username',
        'target_profile_slug', 'target_thread_id', 'target_post_id', 'existing_key_count',
        'classification', 'actor_is_operator', 'cross_account_allowed', 'allowed',
    ];
    private const TOP = ['version', 'post_id', 'record_sha256', 'accepted_at', 'kind', 'context'];

    public static function assertShape(array $data): void
    {
        $keys = array_keys($data); sort($keys); $expected = self::TOP; sort($expected);
        if ($keys !== $expected || ($data['version'] ?? null) !== 1 || !is_array($data['context'])) {
            throw new RuntimeException('Unsupported approval receipt fields or version.');
        }
        $keys = array_keys($data['context']); sort($keys); $expected = self::FIELDS; sort($expected);
        if ($keys !== $expected) throw new RuntimeException('Unsupported approval context fields; preserve the original receipt.');
        foreach ($data['context'] as $key => $value) {
            if (in_array($key, ['actor_is_operator', 'cross_account_allowed', 'allowed'], true)) {
                if (!is_bool($value)) throw new RuntimeException('Invalid approval boolean.');
            } elseif ($key === 'existing_key_count') {
                if (!is_int($value) || $value < 0) throw new RuntimeException('Invalid approval key count.');
            } else self::scalar($value);
        }
        foreach (['post_id', 'record_sha256', 'accepted_at', 'kind'] as $key) self::scalar($data[$key]);
    }

    private static function scalar(mixed $value): void
    {
        if (!is_string($value) || $value === '' || strlen($value) > 4096
            || preg_match('/[\x00-\x1f\x7f]/', $value) || preg_match('//u', $value) !== 1) {
            throw new RuntimeException('Invalid approval text value; preserve the original receipt.');
        }
    }

    private static function header(string $key): string
    {
        return implode('-', array_map('ucfirst', explode('_', $key)));
    }

    public static function encode(array $data): string
    {
        self::assertShape($data);
        $text = "Format: approval-acceptance-v2\n";
        foreach (['post_id', 'record_sha256', 'accepted_at', 'kind'] as $key) {
            $text .= self::header($key) . ': ' . $data[$key] . "\n";
        }
        foreach ($data['context'] as $key => $value) {
            $text .= 'Context-' . self::header($key) . ': ' . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "\n";
        }
        if (strlen($text) > 16384) throw new RuntimeException('Approval receipt exceeds size limit.');
        return $text;
    }

    public static function decode(string $text): array
    {
        if (strlen($text) > 16384 || !str_ends_with($text, "\n")) throw new RuntimeException('Invalid approval receipt text.');
        $headers = [];
        foreach (explode("\n", substr($text, 0, -1)) as $line) {
            if (!preg_match('/^([A-Za-z0-9-]+): (.*)$/D', $line, $m) || isset($headers[$m[1]])) {
                throw new RuntimeException('Malformed or duplicate approval receipt header.');
            }
            $headers[$m[1]] = $m[2];
        }
        if (($headers['Format'] ?? '') !== 'approval-acceptance-v2') throw new RuntimeException('Unsupported approval receipt format.');
        unset($headers['Format']);
        $data = ['version' => 1];
        foreach (['post_id', 'record_sha256', 'accepted_at', 'kind'] as $key) {
            $name = self::header($key);
            if (!isset($headers[$name])) throw new RuntimeException('Missing approval receipt header.');
            $data[$key] = $headers[$name]; unset($headers[$name]);
        }
        $context = [];
        foreach ($headers as $name => $value) {
            $key = array_search($name, array_combine(self::FIELDS, array_map(fn ($f) => 'Context-' . self::header($f), self::FIELDS)), true);
            if ($key === false) throw new RuntimeException('Unknown approval receipt header.');
            if (in_array($key, ['actor_is_operator', 'cross_account_allowed', 'allowed'], true)) {
                if (!in_array($value, ['true', 'false'], true)) throw new RuntimeException('Invalid approval boolean.');
                $value = $value === 'true';
            } elseif ($key === 'existing_key_count') {
                if (!preg_match('/^(0|[1-9][0-9]*)$/D', $value) || (string)(int)$value !== $value) throw new RuntimeException('Invalid approval count.');
                $value = (int)$value;
            }
            $context[$key] = $value;
        }
        $data['context'] = $context; self::assertShape($data);
        return $data;
    }
}
