<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Support\PrivateConfig;
use ForumRewrite\Support\PrivateConfigSchema;

// Whiptail sends selections to a pipe, keeping secrets out of arguments/logs.
function editorDialog(string $kind, string $text, array $extra = [], string $title = 'LLM connection'): ?string
{
    $command = [getenv('V3_TUI_WHIPTAIL') ?: 'whiptail', '--title', $title, '--output-fd', '3', $kind, $text, '0', '0', ...$extra];
    $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR, 3 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to open editor. Use ./v3 private-config edit.');
    }
    $value = stream_get_contents($pipes[3]);
    fclose($pipes[3]);
    return proc_close($process) === 0 ? (string) $value : null;
}

function editorText(string $value): string
{
    return preg_replace('/[\x00-\x1f\x7f]/', ' ', $value) ?? '';
}

/** @return array{int,string} */
function editorSave(array $request): array
{
    $process = proc_open([PHP_BINARY, __DIR__ . '/write_private_config.php', 'update-llm'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
    if (!is_resource($process)) {
        return [1, 'Unable to save. Use ./v3 private-config edit.'];
    }
    fwrite($pipes[0], json_encode($request, JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return [proc_close($process), (string) $output];
}

$path = getenv('FORUM_SECRETS_PATH') ?: dirname(__DIR__, 2) . '/forum-private/secrets.php';
try {
    $file = PrivateConfig::fileValues($path);
    $resolved = PrivateConfigSchema::resolve($file);
    $before = array_map(static fn (array $field): mixed => $field['value'], $resolved);
    $locked = PrivateConfigSchema::lockedLlmKeys($file);
    $draft = [];
    $selection = 'LLM_MODEL';
    while (true) {
        $entries = [];
        foreach (PrivateConfigSchema::llmEditableKeys() as $key) {
            $value = $draft[$key] ?? $before[$key];
            $display = PrivateConfigSchema::formatValue($key, $value);
            $source = array_key_exists($key, $draft) ? 'draft' : $resolved[$key]['source'];
            $entries[] = $key;
            $entries[] = editorText($display . ' (' . $source . (in_array($key, $locked, true) ? '; locked' : '') . ')');
        }
        $entries = [...$entries, 'preset', 'Apply provider preset', 'review', 'Review changes'];
        $choice = editorDialog('--menu', '', ['8', ...$entries, '--default-item', $selection]);
        if ($choice === null) {
            exit(0);
        }
        $selection = $choice;
        if ($choice === 'preset') {
            $presets = PrivateConfigSchema::llmPresets();
            $items = [];
            foreach (array_keys($presets) as $name) {
                array_push($items, $name, ucfirst($name));
            }
            $preset = editorDialog('--menu', 'Apply preset', ['5', ...$items]);
            if ($preset !== null && isset($presets[$preset])) {
                if (array_intersect(array_keys($presets[$preset]), $locked) !== []) {
                    editorDialog('--msgbox', 'Preset includes locked settings. Change deployment configuration to unlock them.');
                } else {
                    $draft = array_replace($draft, $presets[$preset]);
                }
            }
            continue;
        }
        if (in_array($choice, PrivateConfigSchema::llmEditableKeys(), true)) {
            if (in_array($choice, $locked, true)) {
                editorDialog('--msgbox', 'Environment override: change deployment configuration to edit this value.');
                continue;
            }
            $secret = $resolved[$choice]['definition']['secret'];
            $value = editorDialog($secret ? '--passwordbox' : '--inputbox',
                $choice . ($secret ? ' (blank keeps current key)' : ' (' . $resolved[$choice]['source'] . ')'),
                $secret ? [] : [(string) ($draft[$choice] ?? $before[$choice])]);
            if ($value !== null) {
                if ($secret && trim($value) === '') {
                    unset($draft[$choice]);
                } else {
                    $draft[$choice] = $value;
                }
            }
            continue;
        }
        if ($choice !== 'review') {
            continue;
        }
        // Normalize unchanged input before comparing; a blank key is never a deletion.
        foreach ($draft as $key => $value) {
            if ((string) $value === (string) $before[$key]) {
                unset($draft[$key]);
            }
        }
        $after = array_replace($before, $draft);
        $errors = PrivateConfigSchema::validateLlmConnection($after);
        if ($errors !== []) {
            editorDialog('--msgbox', implode("\n", $errors));
            continue;
        }
        $diff = PrivateConfigSchema::redactedLlmDiff($before, $after);
        if ($diff === []) {
            editorDialog('--msgbox', 'No changes.');
            continue;
        }
        $review = '';
        foreach ($diff as $key => $change) {
            $review .= $key . ': ' . editorText($change['before']) . ' → ' . editorText($change['after']) . "\n";
        }
        if (editorDialog('--yesno', $review, [], 'Save LLM changes?') === null) {
            continue;
        }
        [$code, $output] = editorSave(['values' => $draft]);
        if ($code !== 0) {
            editorDialog('--msgbox', editorText($output) . "\nCorrect the draft or cancel; no partial save.");
            continue;
        }
        // The current-value menu itself shows the successful result.
        $file = PrivateConfig::fileValues($path);
        $resolved = PrivateConfigSchema::resolve($file);
        $before = array_map(static fn (array $field): mixed => $field['value'], $resolved);
        $locked = PrivateConfigSchema::lockedLlmKeys($file);
        $draft = [];
    }
} catch (Throwable) {
    // Config/transport exceptions can contain private values; never echo them.
    editorDialog('--msgbox', 'Cannot read or update private configuration. Check the file and permissions with ./v3 private-config edit.');
    exit(1);
}
