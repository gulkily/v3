<?php
declare(strict_types=1);

file_put_contents(__DIR__ . '/requests', $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);
if (is_file(__DIR__ . '/delay')) usleep(1500000);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && is_file(__DIR__ . '/source-change')) {
    [$path, $bytes] = unserialize(file_get_contents(__DIR__ . '/source-change'), ['allowed_classes' => false]);
    unlink(__DIR__ . '/source-change');
    file_put_contents($path, $bytes);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_ends_with(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/api/link_identity')) {
    require file_get_contents(__DIR__ . '/project') . '/autoload.php';
    $fp = (new \ForumRewrite\Security\OpenPgpKeyInspector())->inspectPublicOnly($_POST['public_key'] ?? '')['fingerprint'];
    file_put_contents(__DIR__ . '/posted', serialize($_POST));
    $mode = is_file(__DIR__ . '/register-mode') ? trim(file_get_contents(__DIR__ . '/register-mode')) : 'accept';
    if (is_file(__DIR__ . '/deny-fp') && trim(file_get_contents(__DIR__ . '/deny-fp')) === $fp) $mode = 'deny';
    header('Content-Type: text/plain');
    if ($mode === 'deny') { http_response_code(403); echo "error=denied\n"; return; }
    if (!is_dir(__DIR__ . '/registered')) mkdir(__DIR__ . '/registered');
    $name = (new \ForumRewrite\Security\OpenPgpKeyInspector())->inspect($_POST['public_key'])['username'];
    file_put_contents(__DIR__ . '/registered/' . $fp, 'Profile-Slug: openpgp-' . strtolower($fp) . "\nIdentity-ID: openpgp:" . strtolower($fp) . "\nUsername: $name\nApproved: no\nRetired: no\n");
    if ($mode === 'drop') { http_response_code(500); return; }
    if ($mode === 'readback-failure') file_put_contents(__DIR__ . '/fail-reads', 'yes');
    if ($mode === 'race') { http_response_code(400); echo "error=Identity already exists for this fingerprint.\n"; return; }
    echo "status=ok\nidentity_id=openpgp:" . strtolower($fp) . "\n"; return;
}
if (is_file(__DIR__ . '/fail-reads')) { http_response_code(503); header('Content-Type: text/plain'); echo 'Unavailable'; return; }
$fp = strtoupper(substr((string) ($_GET['profile_slug'] ?? ''), strlen('openpgp-')));
if (preg_match('/^[A-F0-9]{40}$/D', $fp) && is_file(__DIR__ . '/registered/' . $fp)) {
    header('Content-Type: text/plain'); echo file_get_contents(__DIR__ . '/registered/' . $fp); return;
}
[$status, $body, $type] = unserialize(file_get_contents(__DIR__ . '/response'), ['allowed_classes' => false]);
http_response_code($status);
header('Content-Type: ' . $type);
if ($status === 302) header('Location: /other-instance');
echo $body;
