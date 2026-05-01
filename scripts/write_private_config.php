<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$defaultPath = getenv('FORUM_SECRETS_PATH') ?: (dirname($projectRoot) . '/forum-private/secrets.php');

$options = [
    'path' => $defaultPath,
    'force' => false,
    'api_key_stdin' => false,
    'help' => false,
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        $options['help'] = true;
        continue;
    }

    if ($arg === '--force') {
        $options['force'] = true;
        continue;
    }

    if ($arg === '--api-key-stdin') {
        $options['api_key_stdin'] = true;
        continue;
    }

    if (str_starts_with($arg, '--path=')) {
        $options['path'] = substr($arg, strlen('--path='));
        continue;
    }

    fwrite(STDERR, "Unknown argument: {$arg}\n\n");
    printUsage();
    exit(2);
}

if ($options['help']) {
    printUsage();
    exit(0);
}

$path = (string) $options['path'];
if ($path === '') {
    fwrite(STDERR, "Secret config path cannot be empty.\n");
    exit(2);
}

$defaults = [
    'DEDALUS_API_KEY' => 'replace-with-real-key',
    'DEDALUS_API_BASE_URL' => 'https://api.dedaluslabs.ai',
    'DEDALUS_MODEL' => 'openai/gpt-5-nano',
    'DEDALUS_TIMEOUT_SECONDS' => 60,
    'DEDALUS_POST_ANALYSIS_PROMPT_PATH' => 'prompts/dedalus_post_analysis_system.txt',
];

$existing = [];
if (is_file($path)) {
    $loaded = require $path;
    if (!is_array($loaded)) {
        fwrite(STDERR, "Existing config did not return an array: {$path}\n");
        exit(1);
    }

    foreach ($loaded as $key => $value) {
        if (is_string($key)) {
            $existing[$key] = $value;
        }
    }
}

$config = array_merge($defaults, $existing);
if ($options['api_key_stdin']) {
    $apiKey = trim((string) fgets(STDIN));
    if ($apiKey === '') {
        fwrite(STDERR, "No API key received on stdin.\n");
        exit(1);
    }

    $config['DEDALUS_API_KEY'] = $apiKey;
}

if (is_file($path) && !$options['force'] && !$options['api_key_stdin']) {
    fwrite(STDOUT, "Private config already exists at {$path}\n");
    fwrite(STDOUT, "Run with --force to rewrite defaults while preserving existing values, or --api-key-stdin to update the key.\n");
    exit(0);
}

$directory = dirname($path);
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    fwrite(STDERR, "Unable to create private config directory: {$directory}\n");
    exit(1);
}

$contents = "<?php\n\n"
    . "declare(strict_types=1);\n\n"
    . "return [\n"
    . renderConfigLine('DEDALUS_API_KEY', $config['DEDALUS_API_KEY'])
    . renderConfigLine('DEDALUS_API_BASE_URL', $config['DEDALUS_API_BASE_URL'])
    . renderConfigLine('DEDALUS_MODEL', $config['DEDALUS_MODEL'])
    . renderConfigLine('DEDALUS_TIMEOUT_SECONDS', (int) $config['DEDALUS_TIMEOUT_SECONDS'])
    . renderConfigLine('DEDALUS_POST_ANALYSIS_PROMPT_PATH', $config['DEDALUS_POST_ANALYSIS_PROMPT_PATH'])
    . "];\n";

$temporaryPath = $path . '.tmp-' . bin2hex(random_bytes(4));
if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write temporary config file: {$temporaryPath}\n");
    exit(1);
}

@chmod($temporaryPath, 0600);
if (!rename($temporaryPath, $path)) {
    @unlink($temporaryPath);
    fwrite(STDERR, "Unable to move temporary config into place: {$path}\n");
    exit(1);
}
@chmod($path, 0600);

$action = is_file($path) && $existing !== [] ? 'Updated' : 'Created';
fwrite(STDOUT, "{$action} private config at {$path}\n");
if (($config['DEDALUS_API_KEY'] ?? '') === 'replace-with-real-key') {
    fwrite(STDOUT, "DEDALUS_API_KEY is still a placeholder. Update it before enabling real analysis.\n");
}

function renderConfigLine(string $key, mixed $value): string
{
    return '    ' . var_export($key, true) . ' => ' . var_export($value, true) . ",\n";
}

function printUsage(): void
{
    fwrite(STDOUT, <<<'TEXT'
Usage:
  php scripts/write_private_config.php
  php scripts/write_private_config.php --force
  printf '%s\n' "$DEDALUS_API_KEY" | php scripts/write_private_config.php --api-key-stdin
  php scripts/write_private_config.php --path=/private/path/secrets.php

Creates or updates the private PHP config used by ForumRewrite\Support\PrivateConfig.
The default local path is ../forum-private/secrets.php relative to this app checkout.

TEXT);
}
