<?php

declare(strict_types=1);

final class PrivateMessageListTest
{
    public function testConversationListBrowserBehavior(): void
    {
        exec('node ' . escapeshellarg(__DIR__ . '/browser/private_message_list.cjs') . ' 2>&1', $output, $exitCode);
        assertSame(0, $exitCode, implode("\n", $output));
    }
}
