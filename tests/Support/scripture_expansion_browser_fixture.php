<?php

declare(strict_types=1);
require dirname(__DIR__) . '/WriteApiSmokeTest.php';
if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void {
        if ($expected !== $actual) { throw new RuntimeException('Expansion fixture setup failed.'); }
    }
}
$destination = $argv[1] ?? '';
if ($destination === '' || !is_dir($destination)) { throw new RuntimeException('Fixture directory required.'); }
$test = new WriteApiSmokeTest();
(new ReflectionMethod($test, 'withScriptureExpansionFixture'))->invoke($test,
    function ($app, $pdo, $store, $source, $initial, $root, $db, $artifacts, $directory) use ($test, $destination): void {
        $render = new ReflectionMethod($test, 'renderMethod');
        $save = function (string $state) use ($render, $test, $app, $source, $initial, $destination): void {
            foreach (['thread' => '/threads/' . $source, 'post' => '/posts/' . $initial] as $surface => $path) {
                file_put_contents($destination . '/' . $state . '-' . $surface . '.html', $render->invoke($test, $app, 'GET', $path));
            }
        };
        $save('available');
        $render->invoke($test, $app, 'POST', '/api/expand_agent_reply?post_id=' . $initial);
        $save('pending');
        $claimed = $store->claimRequestedForPost($initial);
        $done = $app->fulfillAgentReplyRequest($claimed);
        $save('generated');
        (new \ForumRewrite\Offline\PublicOfflineSnapshotBuilder())->build($db, $destination . '/snapshot.sqlite3');
        $pdo->exec('UPDATE posts SET is_hidden = 1 WHERE post_id = ' . $pdo->quote($source));
        (new \ForumRewrite\Offline\PublicOfflineSnapshotBuilder())->build($db, $destination . '/unavailable.sqlite3');
        $pdo->exec('UPDATE posts SET is_hidden = 0 WHERE post_id = ' . $pdo->quote($source));
        file_put_contents($destination . '/fixture.json', json_encode(compact('source', 'initial', 'root', 'db', 'artifacts', 'directory', 'done'), JSON_THROW_ON_ERROR));
    });
