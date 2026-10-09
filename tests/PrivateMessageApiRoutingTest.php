<?php

declare(strict_types=1);

final class PrivateMessageApiRoutingTest
{
    public function testApplicationRoutesAllMailboxApisThroughTheAuthenticatedController(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../src/ForumRewrite/Application.php');

        assertStringContains("'/api/private_messages'", $source);
        assertStringContains("'/api/private_messages/inbox'", $source);
        assertStringContains("'/api/private_messages/sent'", $source);
        assertStringContains('privateMessageApiController()->send($method, $query)', $source);
        assertStringContains('privateMessageApiController()->inbox($method, $query)', $source);
        assertStringContains('privateMessageApiController()->sent($method, $query)', $source);
    }
}
