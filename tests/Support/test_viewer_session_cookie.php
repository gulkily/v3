<?php

declare(strict_types=1);

// Usage: php tests/Support/test_viewer_session_cookie.php <state-dir> <signed-in|lobby-only> <identity-id>
// Creates that session where a test server started on <state-dir> will find it, and prints
// the cookie a browser holding it would send, as "name=value".
require dirname(__DIR__, 2) . '/autoload.php';
require __DIR__ . '/TestViewerSession.php';

[, $stateDirectory, $mode, $identityId] = $argv + [null, '', '', ''];
TestViewerSession::useStateDirectory($stateDirectory);
match ($mode) {
    'signed-in' => TestViewerSession::signIn($identityId),
    'lobby-only' => TestViewerSession::lobbyOnly($identityId),
    default => throw new InvalidArgumentException('Unknown mode: ' . $mode),
};
[$name, $value] = TestViewerSession::cookie();
echo $name, '=', $value, "\n";
