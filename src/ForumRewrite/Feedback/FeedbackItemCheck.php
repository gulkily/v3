<?php

declare(strict_types=1);

namespace ForumRewrite\Feedback;

use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\GenericTextRecordParser;

/**
 * Loads feedback item files and reports problems (docs/specs/feedback_item_record_v1.md).
 */
final class FeedbackItemCheck
{
    private const REQUIRED_HEADERS = ['ID', 'Title', 'Kind', 'Workflow', 'Created'];
    private const OPTIONAL_HEADERS = ['Evidence', 'Estimate', 'Aliases', 'Duplicate-Of', 'Superseded-By'];
    private const SECTIONS = ['Request:', 'Sources:', 'Done when:', 'Notes:'];
    private const REQUIRED_SECTIONS = ['Request:', 'Sources:'];

    /** @var list<FeedbackItem> */
    public array $items = [];

    /** @var list<string> "file: problem" lines; any problem fails the check */
    public array $problems = [];

    /** @var list<string> listed for review; they do not fail the check */
    public array $warnings = [];

    public static function run(string $directory): self
    {
        $check = new self();
        if (!is_dir($directory)) {
            $check->problems[] = $directory . ': items folder not found';
            return $check;
        }
        $paths = glob(rtrim($directory, '/') . '/*.txt') ?: [];
        sort($paths, SORT_STRING);
        foreach ($paths as $path) {
            $item = $check->loadFile($path);
            if ($item !== null) {
                $check->items[] = $item;
            }
        }
        $check->checkAcrossItems();
        return $check;
    }

    private function loadFile(string $path): ?FeedbackItem
    {
        $name = basename($path);
        $contents = @file_get_contents($path);
        if ($contents === false) {
            $this->problems[] = $name . ': unreadable';
            return null;
        }
        try {
            $record = (new GenericTextRecordParser())->parse($contents);
        } catch (CanonicalRecordParseException $e) {
            $this->problems[] = $name . ': ' . $e->getMessage();
            return null;
        }

        $before = count($this->problems);
        $headers = $record->headers;
        foreach (self::REQUIRED_HEADERS as $header) {
            if (($headers[$header] ?? '') === '') {
                $this->problems[] = $name . ': missing header ' . $header;
            }
        }
        foreach (array_keys($headers) as $header) {
            if (!in_array($header, self::REQUIRED_HEADERS, true) && !in_array($header, self::OPTIONAL_HEADERS, true)) {
                $this->problems[] = $name . ': unknown header ' . $header;
            }
        }

        $id = $headers['ID'] ?? '';
        $kind = $headers['Kind'] ?? '';
        if ($id !== '' && preg_match('/^PF-\d{8}-([FBO])\d{2,}$/', $id, $m) !== 1) {
            $this->problems[] = $name . ': ID must look like PF-YYYYMMDD-F01';
        } elseif ($id !== '' && isset(FeedbackItem::KINDS[$kind]) && FeedbackItem::KINDS[$kind] !== $m[1]) {
            $this->problems[] = $name . ': ID letter ' . $m[1] . ' does not match Kind ' . $kind;
        }
        if ($id !== '' && $name !== $id . '.txt') {
            $this->problems[] = $name . ': filename must be ' . $id . '.txt';
        }
        if ($kind !== '' && !isset(FeedbackItem::KINDS[$kind])) {
            $this->problems[] = $name . ': Kind must be one of ' . implode(', ', array_keys(FeedbackItem::KINDS));
        }
        $workflow = $headers['Workflow'] ?? '';
        if ($workflow !== '' && !in_array($workflow, FeedbackItem::WORKFLOWS, true)) {
            $this->problems[] = $name . ': Workflow must be one of ' . implode(', ', FeedbackItem::WORKFLOWS);
        }
        if (($headers['Created'] ?? '') !== '' && !$this->isDate($headers['Created'])) {
            $this->problems[] = $name . ': Created must be YYYY-MM-DD';
        }
        if (isset($headers['Evidence']) && !in_array($headers['Evidence'], FeedbackItem::EVIDENCE, true)) {
            $this->problems[] = $name . ': Evidence must be one of ' . implode(', ', FeedbackItem::EVIDENCE);
        }
        if (isset($headers['Estimate']) && $headers['Estimate'] !== 'none' && preg_match('/^\d+(\.\d+)?$/', $headers['Estimate']) !== 1) {
            $this->problems[] = $name . ': Estimate must be a nonnegative decimal or none';
        }
        foreach (['duplicate' => 'Duplicate-Of', 'superseded' => 'Superseded-By'] as $state => $header) {
            if ($workflow === $state && ($headers[$header] ?? '') === '') {
                $this->problems[] = $name . ': Workflow ' . $state . ' needs ' . $header;
            }
            if ($workflow !== $state && isset($headers[$header])) {
                $this->problems[] = $name . ': ' . $header . ' is only for Workflow ' . $state;
            }
        }

        [$sections, $sectionProblems] = $this->sections($record->body);
        foreach ($sectionProblems as $problem) {
            $this->problems[] = $name . ': ' . $problem;
        }
        $sources = [];
        foreach (explode("\n", $sections['Sources:'] ?? '') as $line) {
            if (trim($line) === '') {
                continue;
            }
            $url = str_starts_with($line, '- ') ? substr($line, 2) : '';
            if (FeedbackItem::sourceId($url) === null) {
                $this->problems[] = $name . ': source must be "- <site>/posts/<id>" or "/threads/<id>": ' . $line;
                continue;
            }
            $sources[] = $url;
        }
        if (isset($sections['Sources:']) && $sources === [] && count($this->problems) === $before) {
            $this->problems[] = $name . ': Sources: lists no source';
        }

        return count($this->problems) === $before ? new FeedbackItem($path, $headers, $sections, $sources) : null;
    }

    /**
     * @return array{0: array<string, string>, 1: list<string>}
     */
    private function sections(string $body): array
    {
        $sections = [];
        $problems = [];
        $current = null;
        $lastIndex = -1;
        foreach (explode("\n", $body) as $line) {
            if (in_array($line, self::SECTIONS, true)) {
                $index = array_search($line, self::SECTIONS, true);
                if (isset($sections[$line])) {
                    $problems[] = 'section ' . $line . ' appears twice';
                } elseif ($index < $lastIndex) {
                    $problems[] = 'section ' . $line . ' is out of order';
                }
                $lastIndex = max($lastIndex, (int) $index);
                $current = $line;
                $sections[$current] = '';
                continue;
            }
            if ($current === null) {
                if (trim($line) !== '') {
                    $problems[] = 'text before the first section';
                    $current = '';
                }
                continue;
            }
            if ($current !== '') {
                $sections[$current] .= $line . "\n";
            }
        }
        foreach ($sections as $label => $text) {
            $sections[$label] = trim($text, "\n");
        }
        foreach (self::REQUIRED_SECTIONS as $label) {
            if (trim($sections[$label] ?? '') === '') {
                $problems[] = 'missing or empty section ' . $label;
            }
        }
        unset($sections['']);
        return [$sections, array_values(array_unique($problems))];
    }

    private function checkAcrossItems(): void
    {
        $byId = [];
        foreach ($this->items as $item) {
            $byId[$item->id()][] = basename($item->path);
        }
        foreach ($byId as $id => $files) {
            if (count($files) > 1) {
                $this->problems[] = implode(', ', $files) . ': duplicate ID ' . $id;
            }
        }
        $claims = [];
        foreach ($this->items as $item) {
            foreach (['Duplicate-Of', 'Superseded-By'] as $header) {
                $target = $item->header($header);
                if ($target !== '' && !isset($byId[$target])) {
                    $this->problems[] = basename($item->path) . ': ' . $header . ' ' . $target . ' does not exist';
                }
            }
            if (in_array($item->header('Workflow'), ['duplicate', 'superseded'], true)) {
                continue;
            }
            foreach (array_unique($item->sourceIds()) as $sourceId) {
                $claims[$sourceId][] = $item->id();
            }
        }
        foreach ($claims as $sourceId => $ids) {
            if (count($ids) > 1) {
                $this->warnings[] = implode(', ', $ids) . ': share source ' . $sourceId . ' (fine for a split request; otherwise attach or mark duplicate)';
            }
        }
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
