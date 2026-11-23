<?php

declare(strict_types=1);

namespace ForumRewrite\Contacts;

use RuntimeException;

final class LinkedInConnectionsReader
{
    public const MAX_CSV_BYTES = 16 * 1024 * 1024;
    public const MAX_ARCHIVE_BYTES = 64 * 1024 * 1024;
    private const MAX_ROWS = 30000;
    private const MAX_ROW_BYTES = 65536;
    private const HEADERS = [
        'first name' => 'first_name',
        'last name' => 'last_name',
        'url' => 'url',
        'email address' => 'email',
        'company' => 'company',
        'position' => 'position',
        'job title' => 'position',
        'connected on' => 'connected_on',
    ];

    public function readUpload(array $file): array
    {
        if (in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || ($file['size'] ?? 0) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('This file is too large. Extract Connections.csv and try it directly.', 413);
        }
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new RuntimeException('Choose a LinkedIn Connections.csv file and try again.', 422);
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
            throw new RuntimeException('Choose a LinkedIn Connections.csv file.', 422);
        }
        if ($file['size'] > self::MAX_CSV_BYTES) {
            throw new RuntimeException('Connections.csv exceeds the 16 MB import limit.', 413);
        }
        $input = fopen($file['tmp_name'], 'rb');
        try {
            return $this->readCsv($input);
        } finally {
            fclose($input);
        }
    }

    private function readCsv($input): array
    {
        [$columns, $width] = $this->readHeader($input);
        $contacts = $skipped = [];
        $rowNumber = 0;
        while (!feof($input)) {
            $start = ftell($input);
            $row = fgetcsv($input, null, ',', '"', '');
            if ($row === false || $row === [null]) {
                continue;
            }
            if (++$rowNumber > self::MAX_ROWS) {
                throw new RuntimeException('Connections.csv exceeds the 30,000-row import limit.', 413);
            }
            $length = ftell($input) - $start;
            if ($length > self::MAX_ROW_BYTES) {
                throw new RuntimeException('A row exceeds the Contacts import size limit.', 413);
            }
            fseek($input, $start);
            $raw = fread($input, $length);
            $field = '(?:"(?:[^"]++|"")*+"|[^",\r\n]*+)';
            if (count($row) !== $width || !preg_match('~\A' . $field . '(?:,' . $field . ')*\r?\n?\z~D', $raw)) {
                throw new RuntimeException('Connections.csv has an invalid CSV row. Check the file and retry.', 422);
            }
            if (!preg_match('//u', $raw) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $raw)) {
                $skipped[$rowNumber] = 'Invalid text';
                continue;
            }
            $contact = [];
            foreach ($columns as $index => $name) {
                $contact[$name] = trim(str_replace(["\r\n", "\n", "\r"], ' ', $row[$index]));
            }
            if ($contact['first_name'] === '' && $contact['last_name'] === '') {
                $skipped[$rowNumber] = 'Missing name';
                continue;
            }
            $contacts[$rowNumber] = $contact;
        }
        return ['contacts' => $contacts, 'skipped' => $skipped];
    }

    private function readHeader($input): array
    {
        for ($lineNumber = 1; $lineNumber <= 20 && ($line = fgets($input, self::MAX_ROW_BYTES)) !== false; $lineNumber++) {
            $line = str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
            $header = str_getcsv(rtrim($line, "\r\n"), ',', '"', '');
            $keys = array_map(static fn ($column) => strtolower(trim((string) $column)), $header);
            if (!in_array('first name', $keys, true) || !in_array('last name', $keys, true)) {
                continue;
            }
            $columns = [];
            foreach ($keys as $index => $key) {
                if (isset(self::HEADERS[$key])) {
                    $columns[$index] = self::HEADERS[$key];
                }
            }
            if (count(array_unique($columns)) !== count($columns)) {
                break;
            }
            return [$columns, count($header)];
        }
        throw new RuntimeException('Connections.csv must have First Name and Last Name columns with unique headers.', 422);
    }

}
