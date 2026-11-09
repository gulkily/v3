<?php
require_once __DIR__ . '/SingleRequestHttpTest.php';

use ForumRewrite\ReadModel\ReadModelConnection;

/** A locked read model gives visitors the busy page quickly instead of a hang. */
final class ReadModelBusyPageTest
{
    public function testLockedReadModelShowsBusyPageWithinWaitLimit(): void
    {
        [$server, , $db] = SingleRequestHttpTest::server();
        try {
            $before = hash_file('sha256', $db);
            $holder = new PDO('sqlite:' . $db);
            $holder->exec('BEGIN EXCLUSIVE');
            try {
                $started = microtime(true);
                [$headers, $body] = self::get($server, '/');
                $elapsed = microtime(true) - $started;
            } finally {
                $holder->exec('ROLLBACK');
                $holder = null;
            }

            assertStringContains('503', $headers[0]);
            assertTrue(in_array('Retry-After: 10', $headers, true), 'busy page sends Retry-After: 10');
            assertStringContains('<meta http-equiv="refresh" content="10">', $body);
            assertTrue($elapsed < 4.0, sprintf('busy page took %.2fs; expected about the 2s wait limit', $elapsed));
            assertSame($before, hash_file('sha256', $db), 'a locked read model is not rebuilt by the request');

            [$headers] = self::get($server, '/');
            assertStringContains('200', $headers[0]);
        } finally {
            $server->close();
        }
    }

    public function testLockedErrorIsRecognizedThroughWrappers(): void
    {
        $locked = new PDOException('SQLSTATE[HY000]: General error: 5 database is locked');
        assertTrue(ReadModelConnection::isLocked(new RuntimeException('Page failed', 0, $locked)));
        assertTrue(!ReadModelConnection::isLocked(new RuntimeException('no such column: threads.vote_count')));
    }

    /** @return array{0: list<string>, 1: string} */
    private static function get(FrontendTestServer $server, string $path): array
    {
        $context = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 40]]);
        $body = (string) file_get_contents($server->url . $path, false, $context);

        return [$http_response_header, $body];
    }
}
