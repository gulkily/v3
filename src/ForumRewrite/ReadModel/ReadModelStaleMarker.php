<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

final class ReadModelStaleMarker
{
    public function __construct(
        private readonly string $databasePath,
    ) {
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    /**
     * @return array<string, string>|null
     */
    public function read(): ?array
    {
        if (!$this->exists()) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($this->path()), true);
        if (!is_array($decoded)) {
            return null;
        }

        $result = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $result[$key] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> $data
     */
    public function mark(array $data): void
    {
        $directory = dirname($this->path());
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($this->path(), json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public function clear(): void
    {
        if ($this->exists()) {
            @unlink($this->path());
        }
    }

    public function path(): string
    {
        return dirname($this->databasePath) . '/read_model_stale.json';
    }
}
