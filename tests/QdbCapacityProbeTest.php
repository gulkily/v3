<?php

declare(strict_types=1);

final class QdbCapacityProbeTest
{
    public function testProbeCoversStaticAndPhpFallbackReadsWithoutWrites(): void
    {
        $probe = (string) file_get_contents(__DIR__ . '/../scripts/qdb_capacity_probe.js');

        assertStringContains("'/'", $probe);
        assertStringContains("'/latest'", $probe);
        assertStringContains("'/top'", $probe);
        assertStringContains("'/leetness'", $probe);
        assertStringContains("'/search?search=qdb'", $probe);
        assertStringContains("Cookie: 'qdb_capacity_probe=1'", $probe);
        assertStringContains('http.get(', $probe);
        assertStringNotContains('http.post(', $probe);
        assertStringNotContains('http.put(', $probe);
        assertStringNotContains('http.patch(', $probe);
        assertStringNotContains('http.del(', $probe);
    }
}
