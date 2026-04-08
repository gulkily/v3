<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class GenericTextRecordParser
{
    public function parse(string $contents): GenericTextRecord
    {
        $this->assertAscii($contents);
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

    private function assertAscii(string $contents): void
    {
        if (!preg_match('//u', $contents) || preg_match('/[^\x09\x0A\x0D\x20-\x7E]/', $contents)) {
            throw new CanonicalRecordParseException('Canonical text record must be ASCII only.');
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
