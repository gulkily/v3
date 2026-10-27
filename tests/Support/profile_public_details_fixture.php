<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/autoload.php';
if (PHP_SAPI !== 'cli') exit(1);
$root = $argv[1];
$project = dirname(__DIR__, 2);
symlink($project . '/templates', $root . '/templates');
symlink($project . '/public', $root . '/public');
mkdir($root . '/sessions', 0700, true);
$source = __DIR__ . '/../fixtures/parity_minimal_v1';
mkdir($root . '/repository');
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
    $target = $root . '/repository/' . substr($file->getPathname(), strlen($source) + 1);
    if ($file->isDir()) mkdir($target); else copy($file->getPathname(), $target);
}
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', new \ForumRewrite\Canonical\CanonicalRecordRepository($root . '/repository')))->rebuild();
$pdo = new PDO('sqlite:' . $root . '/read.sqlite3');
$profile = $pdo->query('SELECT * FROM profiles LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$pdo->exec('UPDATE profiles SET is_approved=0');
$post = $pdo->query('SELECT * FROM posts LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$thread = $pdo->query('SELECT * FROM threads LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$insert = static function (string $table, array $row) use ($pdo): void {
    $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
};
for ($i = 0; $i < 55; $i++) {
    $id = sprintf('profile-page-%03d', $i);
    $insert('posts', array_replace($post, ['post_id' => $id, 'thread_id' => $id, 'parent_id' => null, 'author_profile_slug' => $profile['profile_slug'],
        'author_identity_id' => $profile['identity_id'], 'is_hidden' => 0, 'retracted_at' => null,
        'board_tags_json' => '["general"]', 'agent_response_json' => null,
        'subject' => 'Profile contribution ' . $i . ' ' . str_repeat('LongValue', 20)]));
    $insert('threads', array_replace($thread, ['root_post_id' => $id, 'last_post_id' => $id,
        'board_tags_json' => '["general"]', 'retracted_at' => null]));
}

$insert('profiles', array_replace($profile, ['identity_id' => 'openpgp:empty-profile', 'profile_slug' => 'openpgp-empty-profile', 'public_key' => '', 'is_approved' => 0]));
mkdir($root . '/static');
(new \ForumRewrite\Host\StaticArtifactBuilder($root, $root . '/repository', $root . '/read.sqlite3', $root . '/generated'))->buildSingleRoute('/profiles/' . $profile['profile_slug']);
symlink($root . '/generated', $root . '/static/current');
file_put_contents($root . '/generated/profiles/' . $profile['profile_slug'] . '.html', '<!-- PROFILE STATIC FIRST -->', FILE_APPEND);
echo json_encode(['slug' => $profile['profile_slug'], 'key' => $profile['public_key'], 'themes' => array_values(array_filter(\ForumRewrite\SiteProfileRegistry::active()['permittedThemes'], static fn (string $theme): bool => $theme !== 'auto'))]);
