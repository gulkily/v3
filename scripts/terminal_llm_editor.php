<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Support\PrivateConfig;
use ForumRewrite\Support\PrivateConfigSchema;
use ForumRewrite\Support\TerminalDialog;

// Whiptail sends selections to a pipe, keeping secrets out of arguments/logs.
function editorDialog(string $kind, string $text, array $extra = [], string $title = 'LLM connection'): ?string
{
    [$rows, $columns] = TerminalDialog::size();
    while ($rows < 18 || $columns < 50) {
        fwrite(STDOUT, "\nResize to at least 50 columns × 18 rows. Enter: retry · q: return\n");
        $answer = fgets(STDIN);
        if ($answer === false || trim($answer) === 'q') {
            return null;
        }
        [$rows, $columns] = TerminalDialog::size();
    }
    if ($kind === '--menu') {
        $extra[0] = (string) min((int) $extra[0], $rows - 10);
    }
    // Long review text needs scrolling, otherwise newt can clip the decision.
    if ($kind === '--yesno' && strlen($text) > ($rows - 10) * ($columns - 12)) {
        $temporary = tempnam(sys_get_temp_dir(), 'v3-tui-review-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to display review.');
        }
        chmod($temporary, 0600);
        try {
            file_put_contents($temporary, wordwrap($text, $columns - 12, "\n", true));
            if (editorDialog('--textbox', $temporary, ['--scrolltext'], 'Review changes') === null) {
                return null;
            }
        } finally {
            unlink($temporary);
        }
        $text = 'Save the reviewed changes?';
    }
    $command = ['--title', $title, $kind, $text, '0', '0', ...$extra];
    if ($kind === '--menu') {
        $command[] = '--notags';
    }
    [$status, $value] = TerminalDialog::run($command);
    if ($status === 75) {
        // Rebuild at the new size; committed draft values remain in the caller.
        return editorDialog($kind, $text, $extra, $title);
    }
    return $status === 0 ? $value : null;
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
    $snapshot = PrivateConfig::snapshot($path);
    $file = $snapshot['file'];
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
            $label = ucfirst(strtolower(str_replace('_', ' ', substr($key, 4))));
            $entries[] = editorText($label . ' (' . $source . (in_array($key, $locked, true) ? '; locked' : '') . '): ' . $display);
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
        $update = PrivateConfigSchema::llmUpdate($file, $draft);
        if ($update['errors'] !== []) {
            editorDialog('--msgbox', implode("\n", $update['errors']));
            continue;
        }
        $draft = $update['values'];
        $diff = $update['diff'];
        if ($diff === []) {
            editorDialog('--msgbox', 'No changes.');
            continue;
        }
        $review = '';
        foreach ($diff as $key => $change) {
            $review .= $key . ': ' . editorText($change['before']) . ' → ' . editorText($change['after']) . ($key === 'LLM_API_KEY' ? ' (replacement)' : '') . "\n";
        }
        if (editorDialog('--yesno', $review, [], 'Save LLM changes?') === null) {
            continue;
        }
        [$code, $output] = editorSave(['values' => $draft, 'expected_revision' => $snapshot['revision']]);
        if ($code === 3) {
            $snapshot = PrivateConfig::snapshot($path);
            $file = $snapshot['file'];
            $resolved = PrivateConfigSchema::resolve($file);
            $before = array_map(static fn (array $field): mixed => $field['value'], $resolved);
            $locked = PrivateConfigSchema::lockedLlmKeys($file);
            unset($draft['LLM_API_KEY']);
            foreach ($locked as $key) {
                unset($draft[$key]);
            }
            editorDialog('--msgbox', "Configuration changed; review again.\nNon-secret draft retained; locked fields refreshed. Re-enter any replacement key.");
            continue;
        }
        if ($code !== 0) {
            editorDialog('--msgbox', editorText($output) . "\nCorrect the draft or cancel; no partial save.");
            continue;
        }
        // The current-value menu itself shows the successful result.
        $snapshot = PrivateConfig::snapshot($path);
        $file = $snapshot['file'];
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
