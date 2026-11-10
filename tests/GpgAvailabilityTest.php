<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Support\GpgAvailability;

final class GpgAvailabilityTest
{
    public function testNoWarningWhenAnExecutableGpgIsOnPath(): void
    {
        $directory = $this->tempDirectory();
        file_put_contents($directory . '/gpg', "#!/bin/sh\n");
        chmod($directory . '/gpg', 0755);

        try {
            assertSame([], GpgAvailability::warnings('/nonexistent' . PATH_SEPARATOR . $directory));
        } finally {
            unlink($directory . '/gpg');
            rmdir($directory);
        }
    }

    public function testWarnsWhenGpgIsMissingOrNotExecutable(): void
    {
        $directory = $this->tempDirectory();
        file_put_contents($directory . '/gpg', "not executable\n");
        chmod($directory . '/gpg', 0644);

        try {
            foreach (['', '/nonexistent', $directory] as $path) {
                $warnings = GpgAvailability::warnings($path);
                assertSame(1, count($warnings));
                assertSame(true, str_contains($warnings[0], 'gpg was not found on PATH'));
            }
        } finally {
            unlink($directory . '/gpg');
            rmdir($directory);
        }
    }

    private function tempDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/v3-gpg-availability-' . bin2hex(random_bytes(6));
        mkdir($directory, 0755, true);

        return $directory;
    }
}
