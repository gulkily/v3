<?php

declare(strict_types=1);

// Private temporary files must be private from creation, before their first byte.
umask(0077);

$projectRoot = dirname(__DIR__);
require $projectRoot . '/autoload.php';
$defaultPath = getenv('FORUM_SECRETS_PATH') ?: (dirname($projectRoot) . '/forum-private/secrets.php');

$options = [
    'path' => $defaultPath,
    'force' => false,
    'api_key_stdin' => false,
    'refresh_template' => false,
    'view' => false,
    'edit' => false,
    'update_llm' => false,
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

    if ($arg === 'edit' || $arg === '--edit') {
        $options['edit'] = true;
        continue;
    }

    if ($arg === 'update-llm' || $arg === '--update-llm') {
        $options['update_llm'] = true;
        continue;
    }

    if ($arg === 'refresh-template' || $arg === '--refresh-template') {
        $options['refresh_template'] = true;
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

// All cooperating writers share this lock, including the external-editor route.
// A sidecar survives atomic rename; never unlink a lock another process may hold.
$configLock = null;
if (!$options['view'] && ($options['edit'] || $options['force'] || $options['api_key_stdin']
    || $options['refresh_template'] || $options['update_llm'] || !is_file($path))) {
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        fwrite(STDERR, "Unable to create private config directory.\n");
        exit(1);
    }
    $configLock = @fopen($path . '.lock', 'c');
    if ($configLock === false) {
        fwrite(STDERR, "Unable to lock private configuration; check directory permissions.\n");
        exit(1);
    }
    @chmod($path . '.lock', 0600);
    $deadline = microtime(true) + 3;
    while (!flock($configLock, LOCK_EX | LOCK_NB)) {
        if (microtime(true) >= $deadline) {
            fwrite(STDERR, "Private configuration is busy; retry after the other editor finishes.\n");
            exit(1);
        }
        usleep(50000);
    }
}

if ($options['edit']) {
    if ($options['view'] || $options['refresh_template'] || $options['force'] || $options['api_key_stdin'] || $options['update_llm']) {
        fwrite(STDERR, "edit cannot be combined with another private-config action.\n");
        exit(2);
    }

    if (!is_file($path)) {
        fwrite(STDERR, "Private config does not exist: {$path}\n");
        fwrite(STDERR, "Create it first with ./v3 private-config --force.\n");
        exit(1);
    }

    $editor = trim((string) (getenv('VISUAL') ?: getenv('EDITOR') ?: 'vi'));
    if ($editor === '') {
        $editor = 'vi';
    }

    fwrite(STDOUT, "Opening private config with {$editor}: {$path}\n");
    $editorProcess = proc_open(
        escapeshellcmd($editor) . ' ' . escapeshellarg($path),
        [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ],
        $editorPipes,
    );
    if (!is_resource($editorProcess)) {
        fwrite(STDERR, "Unable to start editor: {$editor}\n");
        exit(1);
    }

    $exitCode = proc_close($editorProcess);
    if ($exitCode !== 0) {
        fwrite(STDERR, "Editor exited with status {$exitCode}.\n");
        exit($exitCode);
    }

    exit(0);
}

$defaults = \ForumRewrite\Support\PrivateConfigSchema::templateDefaults();

try {
    $snapshot = \ForumRewrite\Support\PrivateConfig::snapshot($path);
    $existing = $snapshot['file'];
} catch (Throwable) {
    fwrite(STDERR, "Cannot read private configuration; check the file and permissions.\n");
    exit(1);
}

if ($options['view']) {
    printConfigView($path, $existing);
    printUpdateReminder($path);
    exit(0);
}

$llmUpdate = null;
if ($options['update_llm']) {
    try {
        $request = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        fwrite(STDERR, "Invalid LLM update request.\n");
        exit(2);
    }
    if (!is_array($request) || !isset($request['values']) || !is_array($request['values'])) {
        fwrite(STDERR, "LLM update request requires a values object.\n");
        exit(2);
    }
    if (isset($request['expected_revision']) && (!is_string($request['expected_revision'])
        || !hash_equals($snapshot['revision'], $request['expected_revision']))) {
        fwrite(STDERR, "Private configuration changed since review. Reload and review the draft again.\n");
        exit(3);
    }
    $update = \ForumRewrite\Support\PrivateConfigSchema::llmUpdate($existing, $request['values']);
    if ($update['errors'] !== []) {
        fwrite(STDERR, implode("\n", $update['errors']) . "\n");
        exit(2);
    }
    $llmUpdate = $update['values'];
    if ($llmUpdate === []) {
        fwrite(STDOUT, "No LLM changes.\n");
        exit(0);
    }
}

$config = resolvedTemplateConfig($existing);
if ($llmUpdate !== null) {
    foreach ($llmUpdate as $key => $value) {
        $config[$key] = $value;
    }
}
if ($options['api_key_stdin']) {
    $apiKey = trim((string) fgets(STDIN));
    if ($apiKey === '') {
        fwrite(STDERR, "No API key received on stdin.\n");
        exit(1);
    }

    $config['DEDALUS_API_KEY'] = $apiKey;
    $config['LLM_API_KEY'] = $apiKey;
}

if (is_file($path) && !$options['force'] && !$options['api_key_stdin'] && !$options['refresh_template'] && !$options['update_llm']) {
    fwrite(STDOUT, "Private config already exists at {$path}\n");
    fwrite(STDOUT, "Run with view to inspect redacted current values.\n");
    fwrite(STDOUT, "Run with refresh-template to add current comments/examples while preserving values.\n");
    fwrite(STDOUT, "Run with --force to rewrite defaults while preserving existing values, or --api-key-stdin to update the key.\n");
    exit(0);
}

$directory = dirname($path);
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    fwrite(STDERR, "Unable to create private config directory: {$directory}\n");
    exit(1);
}

$contents = renderPrivateConfigFile($config, $existing, true);

$temporaryPath = $path . '.tmp-' . bin2hex(random_bytes(4));
register_shutdown_function(static function () use ($temporaryPath): void { @unlink($temporaryPath); });
if (file_put_contents($temporaryPath, $contents, LOCK_EX) !== strlen($contents)) {
    @unlink($temporaryPath);
    fwrite(STDERR, "Unable to write temporary config file: {$temporaryPath}\n");
    exit(1);
}

@chmod($temporaryPath, 0600);
try {
    if (\ForumRewrite\Support\PrivateConfig::snapshot($path)['revision'] !== $snapshot['revision']) {
        @unlink($temporaryPath);
        fwrite(STDERR, "Private configuration changed during save. Reload and review again.\n");
        exit(3);
    }
} catch (Throwable) {
    @unlink($temporaryPath);
    fwrite(STDERR, "Cannot recheck private configuration; no changes saved.\n");
    exit(1);
}
if (!rename($temporaryPath, $path)) {
    @unlink($temporaryPath);
    fwrite(STDERR, "Unable to move temporary config into place: {$path}\n");
    exit(1);
}
@chmod($path, 0600);

$action = is_file($path) && $existing !== [] ? ($options['refresh_template'] ? 'Refreshed' : 'Updated') : 'Created';
fwrite(STDOUT, "{$action} private config at {$path}\n");
if (($config['LLM_API_KEY'] ?? '') === 'replace-with-real-key') {
    fwrite(STDOUT, "LLM_API_KEY is still a placeholder. Update it before enabling real analysis.\n");
}
if (trim((string) ($config['LLM_PROVIDER'] ?? '')) === '') {
    fwrite(STDOUT, "LLM_PROVIDER is not set. Set it to anthropic, openai, openrouter, stub, or a custom OpenAI-compatible gateway name before enabling real analysis.\n");
}
printUpdateReminder($path);

/** @param array<string, mixed> $existing */
function resolvedTemplateConfig(array $existing): array
{
    $config = [];
    $resolved = \ForumRewrite\Support\PrivateConfigSchema::resolve($existing, []);
    foreach (\ForumRewrite\Support\PrivateConfigSchema::templateDefaults() as $key => $_default) {
        $config[$key] = $resolved[$key]['value'];
    }

    return $config;
}

/**
 * @param array<string, mixed> $config
 */
function printConfigView(string $path, array $config): void
{
    fwrite(STDOUT, "Private config path: {$path}\n");
    if (!is_file($path)) {
        fwrite(STDOUT, "Status: missing\n");
        fwrite(STDOUT, "Values: no private config file found.\n");
        return;
    }

    fwrite(STDOUT, "Status: present\n");
    fwrite(STDOUT, "Values:\n");
    $resolved = \ForumRewrite\Support\PrivateConfigSchema::resolve($config);
    foreach (\ForumRewrite\Support\PrivateConfigSchema::templateDefaults() as $key => $_default) {
        fwrite(STDOUT, '  ' . $key . ' = ' . formatConfigValue($key, $resolved[$key]['value']) . ' (' . $resolved[$key]['source'] . ")\n");
    }

    $extraKeys = \ForumRewrite\Support\PrivateConfigSchema::additionalFileValues($config);
    if ($extraKeys !== []) {
        sort($extraKeys);
        fwrite(STDOUT, "Additional file values:\n");
        foreach ($extraKeys as $key) {
            fwrite(STDOUT, '  ' . $key . ' = ' . formatConfigValue($key, $config[$key]) . " (file)\n");
        }
    }
}

function formatConfigValue(string $key, mixed $value): string
{
    return \ForumRewrite\Support\PrivateConfigSchema::formatValue($key, $value);
}

/**
 * @param array<string, mixed> $config
 * @param array<string, mixed> $existing
 */
function renderPrivateConfigFile(array $config, array $existing, bool $includeComments): string
{
    $remainingTemplateKeys = array_fill_keys(array_keys(\ForumRewrite\Support\PrivateConfigSchema::templateDefaults()), true);
    $renderLine = static function (string $key, mixed $value) use (&$remainingTemplateKeys): string {
        unset($remainingTemplateKeys[$key]);
        return renderConfigLine($key, $value);
    };
    $additionalKeys = \ForumRewrite\Support\PrivateConfigSchema::additionalFileValues($existing);

    $contents = "<?php\n\n"
        . "declare(strict_types=1);\n\n";
    if ($includeComments) {
        $contents .= "// Private runtime config for this forum instance.\n"
            . "// This file may contain secrets. Keep it outside public/ and out of git.\n"
            . "// Run ./v3 private-config view to inspect redacted effective values.\n"
            . "// Run ./v3 private-config refresh-template to refresh comments/examples without changing values.\n\n";
    }

    $contents .= "return [\n";
    if ($includeComments) {
        $contents .= "    // Status frontend URL for ./v3 status; leave empty for local auto-discovery.\n"
            . "    // Example: https://forum.example/ (use the direct URL for this instance).\n"
            . "    // --url takes precedence, then FORUM_STATUS_URL from the environment or this file.\n";
    }
    $contents .= $renderLine('FORUM_STATUS_URL', $config['FORUM_STATUS_URL']);

    if ($includeComments) {
        $contents .= "    // LLM provider used for post analysis and agent reply drafting.\n"
            . "    // Required, no default. Supported values: openai, openrouter, anthropic, stub, or a custom OpenAI-compatible gateway name.\n";
    }
    $contents .= $renderLine('LLM_PROVIDER', $config['LLM_PROVIDER']);

    if ($includeComments) {
        $contents .= "\n    // Provider API key. For LLM_PROVIDER=stub this can stay as the placeholder.\n";
    }
    $contents .= $renderLine('LLM_API_KEY', $config['LLM_API_KEY']);

    if ($includeComments) {
        $contents .= "\n    // Base URL without the endpoint path.\n"
            . "    // OpenAI-compatible providers call /v1/chat/completions; Anthropic calls /v1/messages.\n";
    }
    $contents .= $renderLine('LLM_API_BASE_URL', $config['LLM_API_BASE_URL']);

    if ($includeComments) {
        $contents .= "\n    // Provider model identifier.\n";
    }
    $contents .= $renderLine('LLM_MODEL', $config['LLM_MODEL']);

    if ($includeComments) {
        $contents .= "\n    // External provider request timeout in seconds.\n";
    }
    $contents .= $renderLine('LLM_TIMEOUT_SECONDS', (int) $config['LLM_TIMEOUT_SECONDS']);
    $contents .= $renderLine('AGENT_REPLY_MAX_COMPLETION_TOKENS', $config['AGENT_REPLY_MAX_COMPLETION_TOKENS']);

    if ($includeComments) {
        $contents .= "\n    // Optional provider headers. OpenRouter commonly uses HTTP-Referer and X-Title.\n";
    }
    $contents .= $renderLine('LLM_EXTRA_HEADERS', $config['LLM_EXTRA_HEADERS']);

    if ($includeComments) {
        $contents .= "\n    // Prompt template path, relative to the application root unless absolute.\n";
    }
    $contents .= $renderLine('LLM_POST_ANALYSIS_PROMPT_PATH', $config['LLM_POST_ANALYSIS_PROMPT_PATH']);

    if ($includeComments) {
        $contents .= "\n    // Fastmod is independent of full post analysis and disabled by default.\n"
            . "    // Its LLM settings fall back to the corresponding LLM_* values when omitted.\n"
            . "    // Prompt path is the shared CSV task introduction; choose rubric templates in Tools > Fastmod.\n";
    }
    $contents .= $renderLine('FAST_SCORING_ENABLED', $config['FAST_SCORING_ENABLED'])
        . $renderLine('FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED', $config['FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED'])
        . $renderLine('FAST_SCORING_LLM_MODEL', $config['FAST_SCORING_LLM_MODEL'])
        . $renderLine('FAST_SCORING_PROMPT_PATH', $config['FAST_SCORING_PROMPT_PATH'])
        . $renderLine('FAST_SCORING_DATABASE_PATH', $config['FAST_SCORING_DATABASE_PATH']);

    if ($includeComments) {
        $contents .= "\n    // Agent reply controls. These names remain Dedalus-prefixed for backward compatibility.\n";
    }
    $contents .= $renderLine('DEDALUS_AGENT_REPLIES_ENABLED', $config['DEDALUS_AGENT_REPLIES_ENABLED'])
        . $renderLine('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED', $config['DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED'])
        . $renderLine('AGENT_RESPONSE_REQUESTS_ENABLED', $config['AGENT_RESPONSE_REQUESTS_ENABLED']);

    if ($includeComments) {
        $contents .= "\n    // Private LLM exchange capture and web visibility. Both default to true.\n";
    }
    $contents .= $renderLine('LLM_CONVERSATION_RECORDING_ENABLED', $config['LLM_CONVERSATION_RECORDING_ENABLED'])
        . $renderLine('LLM_CONVERSATION_UI_ENABLED', $config['LLM_CONVERSATION_UI_ENABLED']);

    // Every schema template setting must survive writes, even without a dedicated comment block.
    foreach ($remainingTemplateKeys as $key => $_included) {
        $contents .= $renderLine($key, $config[$key]);
    }

    if ($additionalKeys !== []) {
        if ($includeComments) {
            $contents .= "\n    // Additional existing values preserved by refresh-template.\n";
        }
        foreach ($additionalKeys as $key) {
            $contents .= $renderLine($key, $existing[$key]);
        }
    }

    $contents .= "];\n";
    if ($includeComments) {
        $contents .= "\n// Provider examples. Copy the relevant values into the returned array above.\n"
            . "//\n"
            . "// Direct OpenAI:\n"
            . "//   'LLM_PROVIDER' => 'openai',\n"
            . "//   'LLM_API_BASE_URL' => 'https://api.openai.com',\n"
            . "//   'LLM_MODEL' => 'gpt-5-nano',\n"
            . "//\n"
            . "// Direct Anthropic:\n"
            . "//   'LLM_PROVIDER' => 'anthropic',\n"
            . "//   'LLM_API_BASE_URL' => 'https://api.anthropic.com',\n"
            . "//   'LLM_MODEL' => 'claude-haiku-4-5-20251001',\n"
            . "//\n"
            . "// OpenRouter:\n"
            . "//   'LLM_PROVIDER' => 'openrouter',\n"
            . "//   'LLM_API_BASE_URL' => 'https://openrouter.ai/api',\n"
            . "//   'LLM_MODEL' => 'openai/gpt-5-nano',\n"
            . "//   'LLM_EXTRA_HEADERS' => [\n"
            . "//       'HTTP-Referer' => 'https://forum.example',\n"
            . "//       'X-Title' => 'Forum',\n"
            . "//   ],\n"
            . "//\n"
            . "// LiteLLM or another OpenAI-compatible gateway:\n"
            . "//   'LLM_PROVIDER' => 'litellm',\n"
            . "//   'LLM_API_BASE_URL' => 'https://llm-gateway.example',\n"
            . "//   'LLM_MODEL' => 'openai/gpt-5-nano',\n"
            . "//\n"
            . "// Offline smoke tests:\n"
            . "//   'LLM_PROVIDER' => 'stub',\n";
    }

    return $contents;
}

function printUpdateReminder(string $path): void
{
    fwrite(STDOUT, "\nUpdate commands:\n");
    fwrite(STDOUT, "  ./v3 private-config edit\n");
    fwrite(STDOUT, "  ./v3 private-config --force\n");
    fwrite(STDOUT, "  ./v3 private-config refresh-template\n");
    fwrite(STDOUT, "  printf '%s\\n' \"\$LLM_API_KEY\" | ./v3 private-config --api-key-stdin\n");
    fwrite(STDOUT, "  ./v3 private-config --path=" . escapeshellarg($path) . " --force\n");
    fwrite(STDOUT, "LLM_PROVIDER is required (no default). Supported values: openai, openrouter, anthropic, stub, or an OpenAI-compatible gateway name.\n");
    fwrite(STDOUT, "OpenAI-compatible providers use LLM_API_BASE_URL + /v1/chat/completions; Anthropic uses LLM_API_BASE_URL + /v1/messages.\n");
    fwrite(STDOUT, "private-config edit uses VISUAL, EDITOR, or vi to open {$path} without printing secrets.\n");
    fwrite(STDOUT, "Edit {$path} directly for provider options such as LLM_PROVIDER, LLM_MODEL, FAST_SCORING_LLM_MODEL, and LLM_EXTRA_HEADERS.\n");
    fwrite(STDOUT, "Edit {$path} directly for booleans such as FAST_SCORING_ENABLED, DEDALUS_AGENT_REPLIES_ENABLED, AGENT_RESPONSE_REQUESTS_ENABLED, and LLM_CONVERSATION_RECORDING_ENABLED.\n");
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
  php scripts/write_private_config.php edit
  php scripts/write_private_config.php update-llm
  php scripts/write_private_config.php --view
  php scripts/write_private_config.php refresh-template
  php scripts/write_private_config.php --force
  printf '%s\n' "$LLM_API_KEY" | php scripts/write_private_config.php --api-key-stdin
  php scripts/write_private_config.php --path=/private/path/secrets.php

Creates or updates the private PHP config used by ForumRewrite\Support\PrivateConfig.
Use view/--view to print a redacted summary and update reminders without creating or modifying the file.
Use edit/--edit to open an existing config file with VISUAL, EDITOR, or vi.
Use refresh-template to rewrite the file with current comments/examples while preserving values.
The default local path is ../forum-private/secrets.php relative to this app checkout.
LLM_PROVIDER is required (no default). Supported values: openai, openrouter, anthropic, stub, and OpenAI-compatible gateways.
Legacy DEDALUS_* LLM settings are still read as fallbacks, but new writes use LLM_* names.
update-llm accepts a JSON request only on standard input; never pass a secret in an argument.
Its values object contains changed LLM fields; blank API key keeps the current key.
An optional expected_revision binds a TUI review to the current file and value sources.
Writers coordinate through a private .lock sidecar; busy saves return after three seconds.

TEXT);
}
