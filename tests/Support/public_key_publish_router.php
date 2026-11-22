<?php
declare(strict_types=1);

file_put_contents(__DIR__ . '/requests', $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);
[$status, $body, $type] = unserialize(file_get_contents(__DIR__ . '/response'), ['allowed_classes' => false]);
http_response_code($status);
header('Content-Type: ' . $type);
if ($status === 302) header('Location: /other-instance');
echo $body;
