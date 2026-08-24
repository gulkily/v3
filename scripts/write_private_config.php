<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$defaultPath = getenv('FORUM_SECRETS_PATH') ?: (dirname($projectRoot) . '/forum-private/secrets.php');

$options = [
    'path' => $defaultPath,
    'force' => false,
    'api_key_stdin' => false,
    'view' => false,
    'help' => false,
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        $options['help'] = true;
        continue;
    }

    if ($arg === 'view' || $arg === '--view') {
        $options['view'] = true;
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
    'LLM_PROVIDER' => 'dedalus',
    'LLM_API_KEY' => 'replace-with-real-key',
    'LLM_API_BASE_URL' => 'https://api.dedaluslabs.ai',
    'LLM_MODEL' => 'openai/gpt-5-nano',
    'LLM_TIMEOUT_SECONDS' => 60,
    'LLM_EXTRA_HEADERS' => [],
    'LLM_POST_ANALYSIS_PROMPT_PATH' => 'prompts/dedalus_post_analysis_system.txt',
    'DEDALUS_AGENT_REPLIES_ENABLED' => true,
    'DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED' => false,
];

$existing = [];
if (is_file($path)) {
    $existing = loadConfigFile($path);
}

if ($options['view']) {
    printConfigView($path, $defaults, $existing);
    printUpdateReminder($path);
    exit(0);
}

$config = array_merge($defaults, $existing);
$config = applyLegacyLlmFallbacks($config, $existing);
if ($options['api_key_stdin']) {
    $apiKey = trim((string) fgets(STDIN));
    if ($apiKey === '') {
        fwrite(STDERR, "No API key received on stdin.\n");
        exit(1);
    }

    $config['DEDALUS_API_KEY'] = $apiKey;
    $config['LLM_API_KEY'] = $apiKey;
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
    . renderConfigLine('LLM_PROVIDER', $config['LLM_PROVIDER'])
    . renderConfigLine('LLM_API_KEY', $config['LLM_API_KEY'])
    . renderConfigLine('LLM_API_BASE_URL', $config['LLM_API_BASE_URL'])
    . renderConfigLine('LLM_MODEL', $config['LLM_MODEL'])
    . renderConfigLine('LLM_TIMEOUT_SECONDS', (int) $config['LLM_TIMEOUT_SECONDS'])
    . renderConfigLine('LLM_EXTRA_HEADERS', $config['LLM_EXTRA_HEADERS'])
    . renderConfigLine('LLM_POST_ANALYSIS_PROMPT_PATH', $config['LLM_POST_ANALYSIS_PROMPT_PATH'])
    . renderConfigLine('DEDALUS_AGENT_REPLIES_ENABLED', $config['DEDALUS_AGENT_REPLIES_ENABLED'])
    . renderConfigLine('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED', $config['DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED'])
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
if (($config['LLM_API_KEY'] ?? '') === 'replace-with-real-key') {
    fwrite(STDOUT, "LLM_API_KEY is still a placeholder. Update it before enabling real analysis.\n");
}
printUpdateReminder($path);

/**
 * @return array<string, mixed>
 */
function loadConfigFile(string $path): array
{
    $loaded = require $path;
    if (!is_array($loaded)) {
        fwrite(STDERR, "Existing config did not return an array: {$path}\n");
        exit(1);
    }

    $config = [];
    foreach ($loaded as $key => $value) {
        if (is_string($key)) {
            $config[$key] = $value;
        }
    }

    return $config;
}

/**
 * @param array<string, mixed> $config
 * @param array<string, mixed> $existing
 * @return array<string, mixed>
 */
function applyLegacyLlmFallbacks(array $config, array $existing): array
{
    foreach ([
        'LLM_API_KEY' => 'DEDALUS_API_KEY',
        'LLM_API_BASE_URL' => 'DEDALUS_API_BASE_URL',
        'LLM_MODEL' => 'DEDALUS_MODEL',
        'LLM_TIMEOUT_SECONDS' => 'DEDALUS_TIMEOUT_SECONDS',
        'LLM_POST_ANALYSIS_PROMPT_PATH' => 'DEDALUS_POST_ANALYSIS_PROMPT_PATH',
    ] as $neutral => $legacy) {
        if (array_key_exists($neutral, $existing) || !array_key_exists($legacy, $existing)) {
            continue;
        }

        $legacyValue = $existing[$legacy];
        if (trim((string) $legacyValue) !== '') {
            $config[$neutral] = $legacyValue;
        }
    }

    if (!array_key_exists('LLM_PROVIDER', $existing)
        && strtolower(trim((string) ($existing['DEDALUS_ANALYSIS_MODE'] ?? ''))) === 'stub') {
        $config['LLM_PROVIDER'] = 'stub';
    }

    return $config;
}

/**
 * @param array<string, mixed> $defaults
 * @param array<string, mixed> $config
 */
function printConfigView(string $path, array $defaults, array $config): void
{
    fwrite(STDOUT, "Private config path: {$path}\n");
    if (!is_file($path)) {
        fwrite(STDOUT, "Status: missing\n");
        fwrite(STDOUT, "Values: no private config file found.\n");
        return;
    }

    fwrite(STDOUT, "Status: present\n");
    fwrite(STDOUT, "Values:\n");
    foreach (array_keys($defaults) as $key) {
        $hasFileValue = array_key_exists($key, $config);
        $value = $hasFileValue ? $config[$key] : $defaults[$key];
        $source = $hasFileValue ? 'file' : 'default';
        if (!$hasFileValue) {
            $legacyKey = legacyFallbackKey($key);
            if ($legacyKey !== null && array_key_exists($legacyKey, $config)) {
                $legacyValue = $config[$legacyKey];
                if (trim((string) $legacyValue) !== '') {
                    $value = $legacyValue;
                    $source = 'legacy ' . $legacyKey;
                }
            }

            if ($key === 'LLM_PROVIDER'
                && strtolower(trim((string) ($config['DEDALUS_ANALYSIS_MODE'] ?? ''))) === 'stub') {
                $value = 'stub';
                $source = 'legacy DEDALUS_ANALYSIS_MODE';
            }
        }
        $env = getenv($key);
        if ($env !== false) {
            $value = $env;
            $source = 'environment override';
        }

        fwrite(STDOUT, '  ' . $key . ' = ' . formatConfigValue($key, $value) . ' (' . $source . ")\n");
    }

    $extraKeys = array_values(array_diff(array_keys($config), array_keys($defaults)));
    if ($extraKeys !== []) {
        sort($extraKeys);
        fwrite(STDOUT, "Additional file values:\n");
        foreach ($extraKeys as $key) {
            fwrite(STDOUT, '  ' . $key . ' = ' . formatConfigValue($key, $config[$key]) . " (file)\n");
        }
    }
}

function legacyFallbackKey(string $key): ?string
{
    return match ($key) {
        'LLM_API_KEY' => 'DEDALUS_API_KEY',
        'LLM_API_BASE_URL' => 'DEDALUS_API_BASE_URL',
        'LLM_MODEL' => 'DEDALUS_MODEL',
        'LLM_TIMEOUT_SECONDS' => 'DEDALUS_TIMEOUT_SECONDS',
        'LLM_POST_ANALYSIS_PROMPT_PATH' => 'DEDALUS_POST_ANALYSIS_PROMPT_PATH',
        default => null,
    };
}

function formatConfigValue(string $key, mixed $value): string
{
    if ($key === 'LLM_EXTRA_HEADERS' && is_array($value)) {
        $redacted = [];
        foreach ($value as $header => $headerValue) {
            $redacted[$header] = trim((string) $headerValue) === '' ? '<empty>' : '<set>';
        }

        return var_export($redacted, true);
    }

    if (preg_match('/(API_KEY|SECRET|TOKEN|PASSWORD|PRIVATE_KEY)/i', $key) === 1) {
        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return '<empty>';
        }
        if ($stringValue === 'replace-with-real-key') {
            return '<placeholder>';
        }

        return '<set>';
    }

    return var_export($value, true);
}

function printUpdateReminder(string $path): void
{
    fwrite(STDOUT, "\nUpdate commands:\n");
    fwrite(STDOUT, "  ./v3 private-config --force\n");
    fwrite(STDOUT, "  printf '%s\\n' \"\$LLM_API_KEY\" | ./v3 private-config --api-key-stdin\n");
    fwrite(STDOUT, "  ./v3 private-config --path=" . escapeshellarg($path) . " --force\n");
    fwrite(STDOUT, "Edit {$path} directly for provider options such as LLM_PROVIDER, LLM_MODEL, and LLM_EXTRA_HEADERS.\n");
    fwrite(STDOUT, "Edit {$path} directly for booleans such as DEDALUS_AGENT_REPLIES_ENABLED and DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED.\n");
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
  php scripts/write_private_config.php view
  php scripts/write_private_config.php --view
  php scripts/write_private_config.php --force
  printf '%s\n' "$LLM_API_KEY" | php scripts/write_private_config.php --api-key-stdin
  php scripts/write_private_config.php --path=/private/path/secrets.php

Creates or updates the private PHP config used by ForumRewrite\Support\PrivateConfig.
Use view/--view to print a redacted summary and update reminders without creating or modifying the file.
The default local path is ../forum-private/secrets.php relative to this app checkout.

TEXT);
}
