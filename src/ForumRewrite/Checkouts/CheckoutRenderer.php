<?php

declare(strict_types=1);

namespace ForumRewrite\Checkouts;

final class CheckoutRenderer
{
    public function __construct(private int $columns = 80)
    {
        $this->columns = max(20, min(240, $columns));
    }

    public function render(array $rows): array
    {
        if ($rows === []) {
            return ['lines' => [], 'abridged' => false];
        }
        $narrow = $this->columns < 40;
        $checkout = $narrow ? max(4, (int) ($this->columns / 4)) : min(24, (int) ($this->columns / 5));
        $branch = $narrow ? max(4, (int) ($this->columns / 4)) : min(30, (int) ($this->columns / 4));
        $action = $narrow ? $this->columns - $checkout - $branch - 4 : min(18, (int) ($this->columns / 4));
        $widths = [$checkout, $branch, $action];
        if (!$narrow) {
            $widths[] = $this->columns - $checkout - $branch - $action - 6;
        }
        $abridged = false;
        $labels = [];
        foreach ($rows as $index => $row) {
            $parts = explode('/', trim($row['path'], '/'));
            $label = $row['path'];
            for ($depth = 1; $depth <= count($parts); $depth++) {
                $candidate = implode('/', array_slice($parts, -$depth));
                $matches = array_filter($rows, fn ($other) => $other['path'] === $candidate || str_ends_with($other['path'], '/' . $candidate));
                if (count($matches) === 1) {
                    $label = $candidate;
                    break;
                }
            }
            $labels[$index] = $this->fit($this->clean($label), $checkout, $abridged, true);
        }
        $duplicates = array_count_values($labels);
        foreach ($labels as $index => $label) {
            if ($duplicates[$label] > 1) {
                $suffix = '#' . ($index + 1);
                $labels[$index] = $this->fit($label, max(0, $checkout - strlen($suffix)), $abridged, true) . $suffix;
                $abridged = true;
            }
        }
        $lines = [$this->line(array_slice(['CHECKOUT', 'BRANCH', 'ACTION', 'HINT'], 0, count($widths)), $widths, $abridged)];
        foreach ($rows as $index => $row) {
            $hint = $this->hint($row);
            if ($narrow && $hint !== '') {
                $abridged = true;
            }
            $fields = [$labels[$index], $row['snapshot']['branch'] ?? '[' . $row['role'] . ']', $row['action'] ?? $row['remote'] . '/' . $row['target'], $hint];
            $lines[] = $this->line(array_slice($fields, 0, count($widths)), $widths, $abridged);
        }
        return ['lines' => $lines, 'abridged' => $abridged];
    }

    private function hint(array $row): string
    {
        if (isset($row['last_error'])) {
            return 'stale: refresh failed';
        }
        return match ($row['action'] ?? '') {
            'unchecked' => 'awaiting refresh',
            'dirty' => 'preserve changes',
            'busy' => 'finish/abort Git op',
            'conflict' => 'resolve/continue or abort',
            'migration-required' => str_starts_with($row['reason'] ?? '', 'Divergent') ? 'reconcile divergence' : 'reconcile rewrite',
            'diverged' => 'reconcile main',
            'excluded' => str_starts_with($row['reason'] ?? '', 'Not selected') ? 'not selected' : ($row['reason'] ?? ''),
            'current', 'ahead', 'fast-forward', 'merge', 'updated' => '',
            default => $row['reason'] ?? '',
        };
    }

    private function clean(string $value): string
    {
        return preg_replace('/[\x00-\x1f\x7f-\x9f\x{202a}-\x{202e}\x{2066}-\x{2069}]/u', ' ', $value)
            ?? preg_replace('/[^\x20-\x7e]/', '?', $value);
    }

    private function characters(string $value): array
    {
        return preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function width(string $value): int
    {
        $width = 0;
        foreach ($this->characters($value) as $char) {
            $width += preg_match('/[\p{Mn}\p{Me}\x{200d}]/u', $char) ? 0
                : (preg_match('/[\x{1100}-\x{115f}\x{2329}\x{232a}\x{2e80}-\x{a4cf}\x{ac00}-\x{d7a3}\x{f900}-\x{faff}\x{fe10}-\x{fe19}\x{fe30}-\x{fe6f}\x{ff00}-\x{ff60}\x{ffe0}-\x{ffe6}\x{1f300}-\x{1faff}\x{20000}-\x{3fffd}]/u', $char) ? 2 : 1);
        }
        return $width;
    }

    private function fit(string $value, int $width, bool &$abridged, bool $tail = false): string
    {
        if ($this->width($value) <= $width) {
            return $value;
        }
        $abridged = true;
        if ($width < 1) {
            return '';
        }
        $chars = $this->characters($value);
        if ($tail) {
            $chars = array_reverse($chars);
        }
        $result = '';
        $used = 0;
        foreach ($chars as $char) {
            $size = $this->width($char);
            if ($used + $size > $width - 1) {
                break;
            }
            $result = $tail ? $char . $result : $result . $char;
            $used += $size;
        }
        return $tail ? '…' . $result : $result . '…';
    }

    private function line(array $fields, array $widths, bool &$abridged): string
    {
        $result = [];
        foreach ($fields as $index => $field) {
            $text = $this->fit($this->clean($field), $widths[$index], $abridged);
            $result[] = $text . str_repeat(' ', max(0, $widths[$index] - $this->width($text)));
        }
        return rtrim(implode('  ', $result));
    }
}
