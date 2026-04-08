<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class PostRecordParser
{
    private const REQUIRED_HEADERS = [
        'Post-ID',
        'Board-Tags',
    ];

    private const TASK_ROOT_HEADERS = [
        'Task-Status',
        'Task-Presentability-Impact',
        'Task-Implementation-Difficulty',
        'Task-Depends-On',
        'Task-Sources',
    ];

    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): PostRecord
    {
        $record = $this->parser->parse($contents);

        foreach (self::REQUIRED_HEADERS as $header) {
            if (!isset($record->headers[$header]) || $record->headers[$header] === '') {
                throw new CanonicalRecordParseException('Missing required post header: ' . $header);
            }
        }

        $threadId = $record->headers['Thread-ID'] ?? null;
        $parentId = $record->headers['Parent-ID'] ?? null;
        $threadType = $record->headers['Thread-Type'] ?? null;

        $boardTags = array_values(array_filter(explode(' ', $record->headers['Board-Tags']), static fn (string $tag): bool => $tag !== ''));
        if ($boardTags === []) {
            throw new CanonicalRecordParseException('Board-Tags must include at least one tag.');
        }

        $isReply = $threadId !== null || $parentId !== null;
        if ($isReply && ($threadId === null || $parentId === null)) {
            throw new CanonicalRecordParseException('Replies must include both Thread-ID and Parent-ID.');
        }

        if ($isReply && $threadType !== null) {
            throw new CanonicalRecordParseException('Replies must not include Thread-Type.');
        }

        if ($isReply) {
            foreach (self::TASK_ROOT_HEADERS as $header) {
                if (isset($record->headers[$header])) {
                    throw new CanonicalRecordParseException('Replies must not include typed root header: ' . $header);
                }
            }
        }

        $taskStatus = null;
        $taskPresentabilityImpact = null;
        $taskImplementationDifficulty = null;
        $taskDependsOn = [];
        $taskSources = [];

        if ($threadType !== null) {
            if ($isReply) {
                throw new CanonicalRecordParseException('Replies must not include typed root metadata.');
            }

            if ($threadType !== 'task') {
                throw new CanonicalRecordParseException('Unsupported Thread-Type: ' . $threadType);
            }

            foreach (['Task-Status', 'Task-Presentability-Impact', 'Task-Implementation-Difficulty'] as $header) {
                if (!isset($record->headers[$header]) || $record->headers[$header] === '') {
                    throw new CanonicalRecordParseException('Task roots must include ' . $header . '.');
                }
            }

            $taskStatus = $record->headers['Task-Status'];
            $taskPresentabilityImpact = $this->parseUnitDecimal($record->headers['Task-Presentability-Impact'], 'Task-Presentability-Impact');
            $taskImplementationDifficulty = $this->parseUnitDecimal($record->headers['Task-Implementation-Difficulty'], 'Task-Implementation-Difficulty');
            $taskDependsOn = $this->splitSpaceSeparated($record->headers['Task-Depends-On'] ?? '');
            $taskSources = $this->splitSemicolonSeparated($record->headers['Task-Sources'] ?? '');
        }

        return new PostRecord(
            $record->headers['Post-ID'],
            $boardTags,
            $threadId,
            $parentId,
            $record->headers['Subject'] ?? null,
            $threadType,
            $taskStatus,
            $taskPresentabilityImpact,
            $taskImplementationDifficulty,
            $taskDependsOn,
            $taskSources,
            $record->body,
        );
    }

    private function parseUnitDecimal(string $value, string $header): float
    {
        if (!preg_match('/^(0(\.\d+)?|1(\.0+)?)$/', $value)) {
            throw new CanonicalRecordParseException($header . ' must be a decimal from 0 to 1.');
        }

        return (float) $value;
    }

    /**
     * @return string[]
     */
    private function splitSpaceSeparated(string $value): array
    {
        return array_values(array_filter(explode(' ', trim($value)), static fn (string $item): bool => $item !== ''));
    }

    /**
     * @return string[]
     */
    private function splitSemicolonSeparated(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(';', $value)), static fn (string $item): bool => $item !== ''));
    }
}
