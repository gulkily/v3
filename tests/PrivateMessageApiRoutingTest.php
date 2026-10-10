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
        assertStringContains("'/api/private_messages/conversation'", $source);
        assertStringContains("'/api/private_messages/conversations'", $source);
        assertStringContains('privateMessageApiController()->conversations($method, $query)', $source);
        assertStringContains('privateMessagePageController()->conversations($method)', $source);
        assertStringContains("'/api/private_messages/recipient_keys'", $source);
        assertStringContains('privateMessageApiController()->send($method, $query)', $source);
        assertStringContains('privateMessageApiController()->inbox($method, $query)', $source);
        assertStringContains('privateMessageApiController()->sent($method, $query)', $source);
        assertStringContains('privateMessageApiController()->conversation($method, $query)', $source);
        assertStringContains('privateMessageApiController()->recipientKeys($method, $query)', $source);
        assertStringContains("'/messages/inbox'", $source);
        assertStringContains("'/messages/sent'", $source);
        assertStringContains("'/messages/conversation/", $source);
        assertStringContains('privateMessagePageController()->inbox($method)', $source);
        assertStringContains('privateMessagePageController()->sent($method)', $source);
        assertStringContains('privateMessagePageController()->conversation($method, $matches[1])', $source);
    }
}
