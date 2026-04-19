<?php

declare(strict_types=1);

namespace ForumRewrite\Host;

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelConnection;
use PDO;
use RuntimeException;

final class StaticArtifactBuilder
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly string $artifactRoot,
    ) {
    }

    public function build(): void
    {
        $builder = new ReadModelBuilder(
            $this->repositoryRoot,
            $this->databasePath,
            new CanonicalRecordRepository($this->repositoryRoot),
        );
        $builder->rebuild();

        if (!is_dir($this->artifactRoot)) {
            mkdir($this->artifactRoot, 0777, true);
        }

        $application = $this->application();

        $this->writeRouteArtifact($application, '/', $this->artifactRoot . '/index.html');
        $this->writeRouteArtifact($application, '/instance/', $this->artifactRoot . '/instance.html');
        $this->writeRouteArtifact($application, '/activity/', $this->artifactRoot . '/activity.html');
        $this->writeRouteArtifact($application, '/users/', $this->artifactRoot . '/users.html');
        $this->writeRouteArtifact($application, '/tags/', $this->artifactRoot . '/tags.html');
        foreach ($this->fetchVisibleTagRoutes() as $route) {
            $this->writeRouteArtifact($application, $route, $this->artifactPathForRoute($route) ?? throw new RuntimeException('Unable to resolve artifact path for route: ' . $route));
        }

        foreach ($this->fetchVisibleThreadIds() as $threadId) {
            $this->writeRouteArtifact($application, '/threads/' . $threadId, $this->artifactRoot . '/threads/' . $threadId . '.html');
        }

        foreach ($this->fetchVisiblePostIds() as $postId) {
            $this->writeRouteArtifact($application, '/posts/' . $postId, $this->artifactRoot . '/posts/' . $postId . '.html');
        }

        foreach ($this->fetchIds('SELECT profile_slug FROM profiles ORDER BY profile_slug') as $profileSlug) {
            $this->writeRouteArtifact($application, '/profiles/' . $profileSlug, $this->artifactRoot . '/profiles/' . $profileSlug . '.html');
        }
    }

    public function buildSingleRoute(string $route): bool
    {
        $normalizedRoute = $this->normalizeRoute($route);
        if ($normalizedRoute === null) {
            return false;
        }

        $artifactPath = $this->artifactPathForRoute($normalizedRoute);
        if ($artifactPath === null) {
            return false;
        }

        return $this->writeRouteArtifactWithLock($this->application(), $normalizedRoute, $artifactPath);
    }

    /**
     * @return list<string>
     */
    private function fetchIds(string $sql): array
    {
        $pdo = (new ReadModelConnection($this->databasePath))->open();
        /** @var list<string> $rows */
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function fetchVisibleThreadIds(): array
    {
        $pdo = (new ReadModelConnection($this->databasePath))->open();
        $rows = $pdo->query('SELECT root_post_id, board_tags_json FROM threads ORDER BY root_post_id')->fetchAll();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['root_post_id'],
            array_filter($rows, fn (array $row): bool => !$this->isHiddenBootstrapBoardTagsJson((string) $row['board_tags_json']))
        ));
    }

    /**
     * @return list<string>
     */
    private function fetchVisiblePostIds(): array
    {
        $pdo = (new ReadModelConnection($this->databasePath))->open();
        $rows = $pdo->query('SELECT post_id, board_tags_json FROM posts ORDER BY post_id')->fetchAll();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['post_id'],
            array_filter($rows, fn (array $row): bool => !$this->isHiddenBootstrapBoardTagsJson((string) $row['board_tags_json']))
        ));
    }

    private function writeRouteArtifact(Application $application, string $route, string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        ob_start();
        $application->handle('GET', $route);
        $contents = (string) ob_get_clean();
        $temporaryPath = tempnam($directory, 'artifact-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create temporary artifact path in ' . $directory);
        }

        if (file_put_contents($temporaryPath, $contents) === false) {
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to write temporary artifact: ' . $temporaryPath);
        }

        if (!rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to move artifact into place: ' . $path);
        }
    }

    private function writeRouteArtifactWithLock(Application $application, string $route, string $path): bool
    {
        $lockPath = $this->lockPathForArtifact($path);
        $lockDirectory = dirname($lockPath);
        if (!is_dir($lockDirectory) && !mkdir($lockDirectory, 0777, true) && !is_dir($lockDirectory)) {
            throw new RuntimeException('Unable to create artifact lock directory: ' . $lockDirectory);
        }

        $lockHandle = fopen($lockPath, 'c+');
        if ($lockHandle === false) {
            throw new RuntimeException('Unable to open artifact lock file: ' . $lockPath);
        }

        try {
            if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
                return false;
            }

            try {
                if (is_file($path)) {
                    return true;
                }

                $this->writeRouteArtifact($application, $route, $path);
                return true;
            } finally {
                flock($lockHandle, LOCK_UN);
            }
        } finally {
            fclose($lockHandle);
        }
    }

    private function application(): Application
    {
        return new Application(
            $this->projectRoot,
            $this->repositoryRoot,
            $this->databasePath,
            null,
            'static-html',
        );
    }

    private function lockPathForArtifact(string $path): string
    {
        return $this->artifactRoot . '/.locks/' . sha1($path) . '.lock';
    }

    private function normalizeRoute(string $route): ?string
    {
        $path = parse_url($route, PHP_URL_PATH) ?: '/';
        $query = (string) (parse_url($route, PHP_URL_QUERY) ?? '');
        if ($query !== '') {
            return null;
        }

        if ($path === '' || $path === '/') {
            return '/';
        }

        if ($path === '/instance' || $path === '/instance/' || $path === '/backup' || $path === '/backup/') {
            return '/instance/';
        }

        if ($path === '/activity' || $path === '/activity/') {
            return '/activity/';
        }

        if ($path === '/users' || $path === '/users/') {
            return '/users/';
        }

        if ($path === '/tags' || $path === '/tags/') {
            return '/tags/';
        }

        if ($path === '/users/pending' || $path === '/users/pending/') {
            return null;
        }

        if (preg_match('#^/tags/([a-z0-9]+(?:-[a-z0-9]+)*)/?$#', $path, $matches) === 1) {
            return '/tags/' . $matches[1];
        }

        if (preg_match('#^/(threads|posts|profiles)/([^/]+)/?$#', $path, $matches) !== 1) {
            return null;
        }

        $identifier = (string) $matches[2];
        if ($identifier === '' || !preg_match('/^[A-Za-z0-9._:-]+$/', $identifier)) {
            return null;
        }

        $kind = (string) $matches[1];
        if ($kind === 'threads' && !$this->threadExists($identifier)) {
            return null;
        }

        if ($kind === 'posts' && !$this->postExists($identifier)) {
            return null;
        }

        if ($kind === 'profiles' && !$this->profileExists($identifier)) {
            return null;
        }

        return '/' . $kind . '/' . $identifier;
    }

    private function artifactPathForRoute(string $route): ?string
    {
        if ($route === '/') {
            return $this->artifactRoot . '/index.html';
        }

        if ($route === '/instance/') {
            return $this->artifactRoot . '/instance.html';
        }

        if ($route === '/activity/') {
            return $this->artifactRoot . '/activity.html';
        }

        if ($route === '/users/') {
            return $this->artifactRoot . '/users.html';
        }

        if ($route === '/tags/') {
            return $this->artifactRoot . '/tags.html';
        }

        if (preg_match('#^/(threads|posts|profiles)/([^/]+)$#', $route, $matches) === 1) {
            return $this->artifactRoot . '/' . $matches[1] . '/' . $matches[2] . '.html';
        }

        if (preg_match('#^/tags/([a-z0-9]+(?:-[a-z0-9]+)*)$#', $route, $matches) === 1) {
            return $this->artifactRoot . '/tags/' . $matches[1] . '.html';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function fetchVisibleTagRoutes(): array
    {
        $pdo = (new ReadModelConnection($this->databasePath))->open();
        $rows = $pdo->query('SELECT board_tags_json, thread_labels_json FROM threads ORDER BY root_post_id')->fetchAll();
        $routes = [];

        foreach ($rows as $row) {
            if ($this->isHiddenBootstrapBoardTagsJson((string) $row['board_tags_json'])) {
                continue;
            }

            $tags = array_values(array_unique(array_merge(
                $this->decodeStringList((string) $row['board_tags_json']),
                $this->decodeStringList((string) $row['thread_labels_json']),
            )));

            foreach ($tags as $tag) {
                $routes['/tags/' . $tag] = true;
            }
        }

        $paths = array_keys($routes);
        sort($paths);

        return $paths;
    }

    private function threadExists(string $threadId): bool
    {
        return $this->rowExists('SELECT 1 FROM threads WHERE root_post_id = :value LIMIT 1', $threadId);
    }

    private function postExists(string $postId): bool
    {
        return $this->rowExists('SELECT 1 FROM posts WHERE post_id = :value LIMIT 1', $postId);
    }

    private function profileExists(string $profileSlug): bool
    {
        return $this->rowExists('SELECT 1 FROM profiles WHERE profile_slug = :value LIMIT 1', $profileSlug);
    }

    private function rowExists(string $sql, string $value): bool
    {
        $pdo = (new ReadModelConnection($this->databasePath))->open();
        $statement = $pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare existence query.');
        }

        $statement->execute(['value' => $value]);

        return $statement->fetchColumn() !== false;
    }

    private function isHiddenBootstrapBoardTagsJson(string $boardTagsJson): bool
    {
        $boardTags = json_decode($boardTagsJson, true);
        if (!is_array($boardTags)) {
            return false;
        }

        return in_array('identity', $boardTags, true);
    }

    /**
     * @return list<string>
     */
    private function decodeStringList(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $values = [];
        foreach ($decoded as $value) {
            if (is_string($value) && $value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }
}
