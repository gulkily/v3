<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

final class ReadModelConnection
{
    public function __construct(
        private readonly string $databasePath,
    ) {
    }

    public function open(): PDO
    {
        $pdo = new PDO('sqlite:' . $this->databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}
