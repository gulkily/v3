<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Messaging\PrivateMessageDatabaseConfig;

final class PrivateMessageDatabaseConfigTest
{
    public function testDefaultPathIsPrivateToProjectState(): void
    {
        assertSame('/tmp/forum/state/private/messages.sqlite3', PrivateMessageDatabaseConfig::path('/tmp/forum'));
    }

    public function testPrivateConfigOverridesDefaultPath(): void
    {
        assertSame('/srv/forum/private/messages.sqlite3', PrivateMessageDatabaseConfig::path('/tmp/forum', [
            'PRIVATE_MESSAGE_DATABASE_PATH' => '/srv/forum/private/messages.sqlite3',
        ]));
    }
}
