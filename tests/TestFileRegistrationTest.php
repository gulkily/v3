<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

/**
 * tests/run.php lists test files explicitly. A *Test.php file missing from
 * that list never runs and never fails, so check that every one is listed.
 */
final class TestFileRegistrationTest
{
    public function testEveryTestFileIsListedInTheRunner(): void
    {
        $runner = (string) file_get_contents(__DIR__ . '/run.php');
        $missing = [];
        foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
            $name = basename($file);
            if (!preg_match('/\/' . preg_quote($name, '/') . '[\'"]/', $runner)) {
                $missing[] = $name;
            }
        }

        assertSame([], $missing);
    }
}
