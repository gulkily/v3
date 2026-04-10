<?php

declare(strict_types=1);

namespace ForumRewrite\Host;

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelConnection;
use PDO;

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

        $application = new Application(
            $this->projectRoot,
            $this->repositoryRoot,
            $this->databasePath,
        );

        $this->writeRouteArtifact($application, '/', $this->artifactRoot . '/index.html');
        $this->writeRouteArtifact($application, '/instance/', $this->artifactRoot . '/instance.html');
        $this->writeRouteArtifact($application, '/activity/', $this->artifactRoot . '/activity.html');
        $this->writeRouteArtifact($application, '/users/', $this->artifactRoot . '/users.html');

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
        file_put_contents($path, $contents);
    }

    private function isHiddenBootstrapBoardTagsJson(string $boardTagsJson): bool
    {
        $boardTags = json_decode($boardTagsJson, true);
        if (!is_array($boardTags)) {
            return false;
        }

        return in_array('identity', $boardTags, true);
    }
}
