<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class GenericTextRecordParser
{
    public function parse(string $contents): GenericTextRecord
    {
        $this->assertValidCanonicalText($contents);
        $this->assertLfLineEndings($contents);
        $this->assertTrailingLf($contents);

        $separator = strpos($contents, "\n\n");
        if ($separator === false) {
            throw new CanonicalRecordParseException('Canonical text record must contain a blank line between headers and body.');
        }

        $headerBlock = substr($contents, 0, $separator);
        $body = substr($contents, $separator + 2);

        if ($headerBlock === '') {
            throw new CanonicalRecordParseException('Canonical text record must contain at least one header.');
        }

        $headers = [];
        foreach (explode("\n", $headerBlock) as $line) {
            if (!preg_match('/^([A-Za-z0-9-]+): (.*)$/', $line, $matches)) {
                throw new CanonicalRecordParseException('Invalid header line: ' . $line);
            }

            $name = $matches[1];
            $value = $matches[2];
            if (array_key_exists($name, $headers)) {
                throw new CanonicalRecordParseException('Duplicate header: ' . $name);
            }

            $headers[$name] = $value;
        }

        return new GenericTextRecord($headers, $body);
    }

    private function assertValidCanonicalText(string $contents): void
    {
        if (!mb_check_encoding($contents, 'UTF-8')) {
            throw new CanonicalRecordParseException('Canonical text record must be valid UTF-8.');
        }

        foreach (preg_split('//u', $contents, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if ($character === "\n" || $character === "\t" || preg_match('/^[\x20-\x7E]$/', $character) === 1) {
                continue;
            }

            if (preg_match('/[\p{C}]/u', $character) === 1) {
                throw new CanonicalRecordParseException('Canonical text record must not contain Unicode control or format characters.');
            }
        }
    }

    private function assertLfLineEndings(string $contents): void
    {
        if (str_contains($contents, "\r")) {
            throw new CanonicalRecordParseException('Canonical text record must use LF line endings.');
        }
    }

    private function assertTrailingLf(string $contents): void
    {
        if ($contents === '' || !str_ends_with($contents, "\n")) {
            throw new CanonicalRecordParseException('Canonical text record must end with a trailing LF.');
        }
    }
}
