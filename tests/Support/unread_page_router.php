<?php
// A php -S router for tests/browser/inbox_catch_up.mjs: serves the Unread fixture
// page and assets, and answers Caught up the way InboxPageController does
// (303 back to /unread, or 409 with the section's alert). State lives in the
// Key: value file named by UNREAD_ROUTER_STATE so the test can steer and read it.
declare(strict_types=1);

$stateFile = (string) getenv('UNREAD_ROUTER_STATE');
$read = static function () use ($stateFile): array {
    $state = [];
    foreach (file($stateFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        [$key, $value] = array_pad(explode(': ', $line, 2), 2, '');
        $state[$key] = $value;
    }
    return $state;
};
$write = static function (array $state) use ($stateFile): void {
    file_put_contents($stateFile, implode("\n", array_map(static fn ($k, $v) => $k . ': ' . $v, array_keys($state), $state)) . "\n");
};
$render = static function (array $state, ?string $failed = null): string {
    $args = [$state['Replies'], $state['New']];
    if ($failed !== null) $args[] = $failed;
    return (string) shell_exec('php ' . escapeshellarg(__DIR__ . '/unread_page_fixture.php') . ' ' . implode(' ', array_map('escapeshellarg', $args)));
};

$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$state = $read();
if (str_starts_with($path, '/assets/')) {
    $file = dirname(__DIR__, 2) . '/public' . preg_replace('/\.[0-9a-f]{12}\./', '.', $path);
    if (!is_file($file)) { http_response_code(404); return true; }
    header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : 'application/javascript'));
    readfile($file);
    return true;
}
if ($path === '/api/inbox/unread') {
    $state['Count calls'] = (string) ((int) ($state['Count calls'] ?? 0) + 1);
    $write($state);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'unread_count' => $state['Replies'] === '-' ? 0 : 2]);
    return true;
}
if (str_starts_with($path, '/api/')) { http_response_code(404); echo '{}'; return true; }
if ($path === '/unread' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = (string) ($_POST['section'] ?? '');
    $state['Posts'] = trim(($state['Posts'] ?? '') . ' ' . $section . '=' . implode(',', (array) ($_POST['post_ids'] ?? [])));
    $write($state);
    if (($state['Next'] ?? 'ok') === 'refuse') {
        http_response_code(409);
        echo $render($state, $section);
        return true;
    }
    $state[$section === 'replies' ? 'Replies' : 'New'] = '-';
    $write($state);
    header('Location: /unread', true, 303);
    return true;
}
header('Content-Type: text/html; charset=utf-8');
echo $render($state);
return true;
