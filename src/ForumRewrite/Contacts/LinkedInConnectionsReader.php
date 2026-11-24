<?php

declare(strict_types=1);

namespace ForumRewrite\Contacts;

use RuntimeException;
use ZipArchive;

final class LinkedInConnectionsReader
{
    public const MAX_CSV_BYTES = 16 * 1024 * 1024;
    public const MAX_ARCHIVE_BYTES = 64 * 1024 * 1024;
    private const MAX_ROWS = 30000;
    private const MAX_ROW_BYTES = 65536;
    private const MAX_ZIP_ENTRIES = 4096;
    private const ZIP_RECOVERY = 'Extract Connections.csv on your computer, then import that CSV directly.';
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
        $format = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($format === 'zip') {
            return $this->readZip($file['tmp_name']);
        }
        if ($format !== 'csv') {
            throw new RuntimeException('Choose a LinkedIn ZIP or Connections.csv file.', 422);
        }
        if ($file['size'] > self::MAX_CSV_BYTES) {
            throw new RuntimeException('Connections.csv exceeds the 16 MB import limit.', 413);
        }
        $input = @fopen($file['tmp_name'], 'rb');
        if ($input === false) {
            throw new RuntimeException('Could not read Connections.csv. Choose the file again.', 422);
        }
        try {
            return $this->readCsv($input);
        } finally {
            fclose($input);
        }
    }

    private function readZip(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZIP import is unavailable on this PHP installation. ' . self::ZIP_RECOVERY, 422);
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Could not open the ZIP. ' . self::ZIP_RECOVERY, 422);
        }
        try {
            $entry = $this->connectionsEntry($zip);
            $member = $zip->getStream($entry['name']);
            if ($member === false) {
                throw new RuntimeException('Could not open Connections.csv in the ZIP. ' . self::ZIP_RECOVERY, 422);
            }
            try {
                $csv = tmpfile();
                if ($csv === false) {
                    throw new RuntimeException('Could not prepare the CSV from the ZIP. ' . self::ZIP_RECOVERY, 422);
                }
                try {
                    $copied = @stream_copy_to_stream($member, $csv, self::MAX_CSV_BYTES + 1);
                    if ($copied === false || $copied !== $entry['size'] || !feof($member)) {
                        throw new RuntimeException('Could not read the complete Connections.csv. ' . self::ZIP_RECOVERY, 422);
                    }
                    rewind($csv);
                    return $this->readCsv($csv);
                } finally {
                    fclose($csv);
                }
            } finally {
                fclose($member);
            }
        } finally {
            $zip->close();
        }
    }

    private function connectionsEntry(ZipArchive $zip): array
    {
        if ($zip->numFiles > self::MAX_ZIP_ENTRIES) {
            throw new RuntimeException('The ZIP has too many entries. ' . self::ZIP_RECOVERY, 413);
        }
        $entry = null;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if ($name === false) {
                throw new RuntimeException('Could not read the ZIP inventory. ' . self::ZIP_RECOVERY, 422);
            }
            if (strcasecmp(basename(str_replace('\\', '/', $name)), 'Connections.csv') !== 0) {
                continue;
            }
            $hasAttributes = $zip->getExternalAttributesIndex($index, $system, $attributes);
            $unixType = $hasAttributes && $system === ZipArchive::OPSYS_UNIX ? (($attributes >> 16) & 0170000) : 0;
            if ($entry !== null || !in_array($unixType, [0, 0100000], true)
                || str_contains($name, '\\') || str_contains($name, "\0")
                || array_intersect(explode('/', $name), ['', '.', '..'])) {
                throw new RuntimeException('The ZIP needs exactly one regular Connections.csv file. ' . self::ZIP_RECOVERY, 422);
            }
            $entry = $zip->statIndex($index);
            if ($entry === false) {
                throw new RuntimeException('Could not read the ZIP inventory. ' . self::ZIP_RECOVERY, 422);
            }
        }
        if ($entry === null) {
            throw new RuntimeException('The ZIP does not have a Connections.csv file. ' . self::ZIP_RECOVERY, 422);
        }
        if ($entry['size'] > self::MAX_CSV_BYTES) {
            throw new RuntimeException('Connections.csv exceeds the 16 MB import limit.', 413);
        }
        return $entry;
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
