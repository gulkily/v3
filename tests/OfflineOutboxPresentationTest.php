<?php

declare(strict_types=1);

final class OfflineOutboxPresentationTest
{
    public function testOutboxUsesCompactExpandableRowsWithBothTimestampMeanings(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const source = fs.readFileSync(process.argv[1], 'utf8');
process.stdout.write(JSON.stringify({
  details: source.includes('document.createElement("details")'),
  summary: source.includes('document.createElement("summary")'),
  actionTime: source.includes('Action time (signed)'),
  integrationTime: source.includes('Integration time '),
  privatePayload: !source.includes('item.payload')
}));
NODE;
        $command = sprintf('node -e %s %s', escapeshellarg($script), escapeshellarg(__DIR__ . '/../public/assets/outbox.js'));
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Outbox compact-presentation check failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame(['details' => true, 'summary' => true, 'actionTime' => true, 'integrationTime' => true, 'privatePayload' => true], $result);
    }
}
