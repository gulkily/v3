#!/usr/bin/env php
<?php

declare(strict_types=1);

// Standalone publisher: PHP 8.1+, HTTP(S) streams, and GPG for signed posts.
function failInput(string $message): never
{
    fwrite(STDERR, "Error: $message\nUse --help for usage.\n");
    exit(2);
}

function runGpg(array $arguments): array
{
    // An argument array avoids shell interpretation. Inherit stdin/stderr so GPG
    // can use its normal pinentry; capture only status or public-key output.
    $process = proc_open(array_merge(['gpg'], $arguments), [0 => STDIN, 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start GPG.');
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return [proc_close($process), $output];
}

function resolveSigningKey(string $selector): string
{
    // GPG matches local user IDs. Count primary keys, not their signing subkeys,
    // and reject ambiguous names before any request to the instance.
    if (preg_match('/\A(?:[A-Fa-f0-9]{40}|[A-Fa-f0-9]{64})\z/', $selector)) {
        return $selector;
    }
    [$code, $output] = runGpg(['--batch', '--with-colons', '--fingerprint', '--list-secret-keys', '--', $selector]);
    $fingerprints = [];
    $primary = false;
    foreach (explode("\n", $output) as $line) {
        $fields = explode(':', $line);
        if ($fields[0] === 'sec' || $fields[0] === 'ssb') {
            $primary = $fields[0] === 'sec';
        } elseif ($fields[0] === 'fpr' && $primary) {
            if (preg_match('/\A(?:[A-Fa-f0-9]{40}|[A-Fa-f0-9]{64})\z/', $fields[9] ?? '')) {
                $fingerprints[$fields[9]] = true;
            }
            $primary = false;
        }
    }
    if (count($fingerprints) > 1) {
        throw new RuntimeException('Username matches multiple secret keys; use --signing-key with a full primary fingerprint.');
    }
    if ($code !== 0 || !$fingerprints) {
        throw new RuntimeException('No matching secret key found. Check the GPG user ID and keyring/GNUPGHOME.');
    }
    $fingerprint = (string) array_key_first($fingerprints);
    echo "Signing-key: $fingerprint\n";
    fflush(STDOUT);
    return $fingerprint;
}

function signRecord(string $record, string $fingerprint): string
{
    // Sign the server's exact canonical bytes, including its trailing newlines.
    // The private temporary directory holds only the record and detached signature.
    $directory = sys_get_temp_dir() . '/publish-text-' . bin2hex(random_bytes(12));
    if (!mkdir($directory, 0700)) {
        throw new RuntimeException('Unable to create signing directory.');
    }
    try {
        $path = $directory . '/record.txt';
        if (file_put_contents($path, $record) !== strlen($record)) {
            throw new RuntimeException('Unable to write signing record.');
        }
        [$code] = runGpg(['--armor', '--detach-sign', '--local-user', $fingerprint, '--output', $path . '.asc', $path]);
        if ($code !== 0 || !is_file($path . '.asc')) {
            throw new RuntimeException('GPG signing failed. Check the secret key and GPG/pinentry.');
        }
        $signature = file_get_contents($path . '.asc');
        if ($signature === false) {
            throw new RuntimeException('Unable to read signature.');
        }
        return $signature;
    } finally {
        // Clean up on signing/read failures as well as success; the key stays in GPG.
        foreach (glob($directory . '/*') as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}

function post(string $base, string $route, array $fields, bool $signed = false): array
{
    // Submit a single form request. Keep TLS checks enabled and reject redirects
    // so a redirect cannot forward authored content to another destination.
    $context = stream_context_create(['http' => [
        'method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\nConnection: close\r\n",
        'content' => http_build_query($fields, '', '&'), 'timeout' => 60,
        'ignore_errors' => true, 'follow_location' => 0,
    ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $body = @file_get_contents($base . '/api/' . $route, false, $context);
    if ($body === false) {
        throw new RuntimeException('HTTP request failed. Check connectivity and PHP allow_url_fopen/HTTPS support.');
    }
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('~^HTTP/\S+ (\d+)~', $header, $match)) {
            $status = (int) $match[1];
        }
    }
    // Signed routes use JSON; anonymous routes use key=value lines. Errors may
    // use either format, regardless of which endpoint was requested.
    $json = json_decode($body, true);
    $lines = [];
    foreach (explode("\n", $body) as $line) {
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', rtrim($line, "\r"), 2);
            $lines[$key] = $value;
        }
    }
    $values = $signed ? $json : $lines;
    if ($status < 200 || $status >= 300) {
        $error = (is_array($json) ? ($json['error'] ?? null) : null) ?? $lines['error'] ?? 'Check the instance URL and access policy.';
        throw new RuntimeException("HTTP $status: " . (is_string($error) ? $error : 'API error.'));
    }
    if (!is_array($values) || ($values['status'] ?? null) !== 'ok') {
        $error = is_array($values) ? ($values['error'] ?? null) : null;
        throw new RuntimeException(is_string($error) ? $error : 'Unexpected API response; check the instance URL and access policy.');
    }
    return $values;
}

function requiredFields(array $values, array $keys): array
{
    // Reject incomplete prepare responses before asking GPG to sign anything.
    $result = [];
    foreach ($keys as $key) {
        if (!isset($values[$key]) || !is_string($values[$key]) || $values[$key] === '') {
            throw new RuntimeException("API response is missing $key.");
        }
        $result[$key] = $values[$key];
    }
    return $result;
}

$options = ['subject' => '', 'board-tags' => 'general'];
// Parse options before or after positionals, matching the Python command shape.
// Unlike PHP getopt(), this also accepts options following INSTANCE and FILE.
$positionals = [];
$literal = false;
for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if (!$literal && ($arg === '--help' || $arg === '-h')) {
        echo "Usage: php scripts/publish_text.php INSTANCE FILE [options]\n"
            . "Publish a UTF-8 file; without a key option, publish anonymously.\n"
            . "  --subject TEXT         New thread subject\n  --board-tags TAGS      Comma-separated boards (default: general)\n"
            . "  --thread-id ID         Publish a reply\n  --parent-id ID         Reply parent (defaults to thread ID)\n"
            . "  --signing-key KEY      Full primary fingerprint or unique local GPG username\n"
            . "  --generate-key NAME    Create persistent Ed25519 key, register identity, publish\n"
            . "Key options are mutually exclusive. GPG uses your keyring/GNUPGHOME and pinentry.\n"
            . "Requires PHP 8.1+ with HTTP(S) streams; signing requires GPG. No automatic retries.\n";
        exit(0);
    }
    if (!$literal && $arg === '--') {
        $literal = true;
        continue;
    }
    if (!$literal && str_starts_with($arg, '-')) {
        $pair = explode('=', $arg, 2);
        $name = substr($pair[0], 2);
        if (!in_array($name, ['subject', 'board-tags', 'thread-id', 'parent-id', 'signing-key', 'generate-key'], true)) {
            failInput("Unknown option: $arg");
        }
        $value = $pair[1] ?? ($argv[++$i] ?? null);
        if ($value === null) {
            failInput("Missing value for $arg");
        }
        $options[$name] = $value;
    } else {
        $positionals[] = $arg;
    }
}
if (count($positionals) !== 2) {
    failInput('Provide an instance URL and text file.');
}
// Finish validation and read the entire UTF-8 body before creating keys or posting.
[$base, $file] = $positionals;
$base = rtrim($base, '/');
$url = parse_url($base);
if (!$url || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || empty($url['host'])
    || array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($url)) || preg_match('/[\x00-\x20\x7f]/', $base)) {
    failInput('Instance must be an HTTP(S) base URL without credentials, query or fragment.');
}
if (isset($options['signing-key'], $options['generate-key'])) {
    failInput('Choose --signing-key or --generate-key.');
}
if (isset($options['signing-key']) && (trim($options['signing-key']) === '' || preg_match('/[\x00-\x1f\x7f]/', $options['signing-key']))) {
    failInput('--signing-key requires a nonempty fingerprint or username without control characters.');
}
if (isset($options['generate-key']) && (trim($options['generate-key']) === '' || preg_match('/[\x00-\x1f\x7f]/', $options['generate-key']))) {
    failInput('--generate-key requires a nonempty name without control characters.');
}
if (!empty($options['parent-id']) && empty($options['thread-id'])) {
    failInput('--parent-id requires --thread-id.');
}
if (!empty($options['thread-id']) && $options['subject'] !== '') {
    failInput('--subject only applies to new threads.');
}
$body = is_file($file) ? @file_get_contents($file) : false;
if ($body === false || preg_match('//u', $body) !== 1 || preg_match('/\A\s*\z/u', $body)) {
    failInput('Cannot read a nonempty UTF-8 text file.');
}
$preparedId = null;
try {
    $fingerprint = isset($options['signing-key']) ? resolveSigningKey($options['signing-key']) : null;
    if (isset($options['generate-key'])) {
        // Use GPG's machine status to identify the new key, not its potentially
        // nonunique name. Key material persists in the user's keyring/GNUPGHOME.
        [$code, $output] = runGpg(['--status-fd', '1', '--quick-generate-key', trim($options['generate-key']), 'ed25519', 'sign', '0']);
        if (!preg_match('/^\[GNUPG:\] KEY_CREATED [BP] ([A-Fa-f0-9]{40,64})$/m', $output, $match)) {
            throw new RuntimeException('GPG did not report a new key. Check GPG/pinentry and your keyring before retrying.');
        }
        $fingerprint = $match[1];
        // Print immediately so a later registration failure cannot hide the key ID.
        echo "Signing-key: $fingerprint\nKey-location: GPG keyring (GNUPGHOME if set)\n";
        fflush(STDOUT);
        if ($code !== 0) {
            throw new RuntimeException('GPG reported an error after key creation; inspect the retained key.');
        }
        [$code, $public] = runGpg(['--armor', '--export', $fingerprint]);
        if ($code !== 0 || $public === '') {
            throw new RuntimeException('Unable to export public key; the secret key remains in your keyring.');
        }
        // Publish only the public key and prove ownership with a signed identity record.
        $payload = requiredFields(post($base, 'prepare_identity', ['public_key' => $public], true), ['prepare_token', 'canonical_record']);
        $payload['detached_signature'] = signRecord($payload['canonical_record'], $fingerprint);
        $registered = post($base, 'create_identity', $payload, true);
        $identity = 'openpgp:' . strtolower($fingerprint);
        if (($registered['identity_id'] ?? null) !== $identity) {
            throw new RuntimeException('Registration response identity does not match generated key.');
        }
        echo "Identity-ID: $identity\n";
    }
    $fields = ['body' => $body, 'board_tags' => $options['board-tags']];
    $kind = empty($options['thread-id']) ? 'thread' : 'reply';
    if ($kind === 'reply') {
        $fields['thread_id'] = $options['thread-id'];
        $fields['parent_id'] = !empty($options['parent-id']) ? $options['parent-id'] : $options['thread-id'];
    } else {
        $fields['subject'] = $options['subject'];
    }
    if ($fingerprint !== null) {
        // Prepare assigns the canonical bytes and IDs. Send them back unchanged
        // with an armored detached signature; never fall back to anonymous posting.
        $fields['author_identity_id'] = 'openpgp:' . strtolower($fingerprint);
        $payload = requiredFields(post($base, 'prepare_' . $kind, $fields, true), ['prepare_token', 'canonical_record', 'record_path', 'post_id']);
        $preparedId = $payload['post_id'];
        $payload['author_identity_id'] = $fields['author_identity_id'];
        $payload['detached_signature'] = signRecord($payload['canonical_record'], $fingerprint);
        $values = post($base, 'create_prepared_post', $payload, true);
    } else {
        $values = post($base, 'create_' . $kind, $fields);
    }
    requiredFields($values, ['post_id']);
    echo 'Post-ID: ' . $values['post_id'] . "\n";
    if (!empty($values['thread_id'])) {
        echo 'Thread-ID: ' . $values['thread_id'] . "\n";
    }
    echo 'URL: ' . $base . '/posts/' . rawurlencode($values['post_id']) . "\n";
} catch (Throwable $error) {
    // The server may have committed before its response failed. Report the known
    // post ID for a manual check, without issuing a potentially duplicate request.
    fwrite(STDERR, 'Error: ' . $error->getMessage() . "\nCheck the instance before retrying; publication may have completed.\n");
    if ($preparedId !== null) {
        fwrite(STDERR, "Post-ID: $preparedId\n");
    }
    exit(1);
}
