<?php

declare(strict_types=1);

$testFiles = [
    __DIR__ . '/AgentIdentityServiceTest.php',
    __DIR__ . '/ApplicationServerTimingTest.php',
    __DIR__ . '/BrowserSigningNormalizationTest.php',
    __DIR__ . '/CanonicalRecordParsersTest.php',
    __DIR__ . '/DedalusPostAnalyzerTest.php',
    __DIR__ . '/LocalAppSmokeTest.php',
    __DIR__ . '/ReadModelBuilderTimingTest.php',
    __DIR__ . '/ReadModelThreadLabelsTest.php',
    __DIR__ . '/VersionCheckBehaviorTest.php',
    __DIR__ . '/WriteApiSmokeTest.php',
];

$failures = [];

foreach ($testFiles as $testFile) {
    require_once $testFile;
}

$declared = get_declared_classes();
foreach ($declared as $class) {
    if (!str_ends_with($class, 'Test')) {
        continue;
    }

    $testObject = new $class();
    $methods = get_class_methods($testObject);

    foreach ($methods as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }

        try {
            $testObject->{$method}();
            fwrite(STDOUT, "PASS {$class}::{$method}\n");
        } catch (Throwable $throwable) {
            $failures[] = "{$class}::{$method} - {$throwable->getMessage()}";
            fwrite(STDERR, "FAIL {$class}::{$method} - {$throwable->getMessage()}\n");
        }
    }
}

if ($failures !== []) {
    exit(1);
}

fwrite(STDOUT, "All tests passed.\n");
