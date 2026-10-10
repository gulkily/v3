<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use RuntimeException;

final class InstanceSourceResolver
{
    public function resolve(string $input, ?string $sourcesFile = null): string
    {
        $aliases = [];
        if ($sourcesFile !== null) {
            if (!is_file($sourcesFile) || filesize($sourcesFile) > 1048576) {
                throw new RuntimeException('Sources file is missing or exceeds 1 MiB.');
            }
            $decoded = json_decode((string) file_get_contents($sourcesFile), false, 512, JSON_THROW_ON_ERROR);
            if (!$decoded instanceof \stdClass) {
                throw new RuntimeException('Sources file must be a JSON object mapping names to instance URLs.');
            }
            foreach (get_object_vars($decoded) as $name => $url) {
                if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/D', (string) $name) !== 1 || !is_string($url)) {
                    throw new RuntimeException('Sources file contains an invalid name/URL mapping.');
                }
                $this->validateBase($url);
                $aliases[$name] = $url;
            }
        }
        if (isset($aliases[$input])) {
            $url = $aliases[$input];
        } elseif (str_contains($input, '://')) {
            $url = $input;
        } elseif (preg_match('#^(?:localhost|[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+|\[[0-9a-fA-F:]+\])(?::[0-9]+)?(?:/[^\s]*)?$#D', $input)) {
            $url = 'https://' . $input;
        } else {
            throw new RuntimeException('Unknown instance name. Supply a full URL/hostname or define the name in --sources=<JSON file>.');
        }
        $this->validateBase($url);
        return rtrim($url, '/');
    }

    private function validateBase(string $url): void
    {
        $parts = InstanceArchiveDownloader::validateUrl($url);
        if (isset($parts['query'])) {
            throw new RuntimeException('Instance base URL must not contain a query.');
        }
    }
}
