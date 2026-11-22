#!/usr/bin/env php
<?php

declare(strict_types=1);

const QUILL_VERSION = '2026-11-22';

// quill.php: portable forum client. PHP 8.1+ CLI, curl and GPG; no extensions beyond core
// (intl is used for Unicode normalization when present). One of three versions (quill.py,
// quill.php, quill.pl) with the same commands, output, saved state and exit codes, kept on
// purpose; see docs/reference/multi_language_scripts.md. Unix-like hosts; processes run
// with argument lists, never through a shell.

if (PHP_SAPI !== 'cli') {
    // The site serves this file as text; a web server should never run it.
    http_response_code(404);
    exit;
}

final class ClientError extends RuntimeException
{
}

final class Quill
{
    public const USER_AGENT = 'quill-php/' . QUILL_VERSION;
    public const REPORT_ERROR_LIMIT = 300;
    /** Exit status for a post the server kept but did not publish (0 published, 2 error). */
    public const STAGED_EXIT = 3;
    public const REJECTED_GUIDANCE = 'Rejected: the server refused this write, so nothing was published. Correct the original draft and start a new write; do not resume.';
    public const SESSION_COOKIES = ['__Host-v3_session', 'v3_session'];
    public const IMAGE_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg'];
    // Same limits as the browser composer (public/assets/image_prepare.js) and the server's upload check.
    public const IMAGE_SOURCE_BYTES = 32 * 1024 * 1024;
    public const IMAGE_MAX_BYTES = 5 * 1024 * 1024;
    public const IMAGE_MAX_SIDE = 2048;
    /** PNG chunks that only carry metadata (text, EXIF, timestamps); everything else is image data or colour. */
    public const PNG_METADATA_CHUNKS = ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'];
    /** Saved Key: value files list these first, in this order, so they read the same as quill.py's. */
    public const META_ORDER = ['Site', 'Protocol', 'Kind', 'State', 'Identity-ID', 'Thread-ID', 'Parent-ID', 'Fingerprint', 'Home', 'Generated'];

    /** Trouble-report context for this run: last API route, busy retries, operation and identity. */
    public static array $trace = ['route' => '', 'busy' => 0, 'operation' => '', 'identity' => ''];
    /** Update notices already printed this run; the server sends one on every response. */
    public static array $notices = [];
}

function fail(string $message): never
{
    throw new ClientError($message);
}

function out(string $text): void
{
    fwrite(STDOUT, $text);
}

function err(string $text): void
{
    fwrite(STDERR, $text);
}

/** The name this copy runs under, so printed commands work whether it was renamed or not. */
function program(): string
{
    $name = basename((string) ($_SERVER['argv'][0] ?? ''));
    return str_ends_with($name, '.php') ? $name : 'quill.php';
}

/** QUILL_<name>, then the older FRM_<name>. */
function setting(string $name, string $default = ''): string
{
    foreach (['QUILL_', 'FRM_'] as $prefix) {
        $value = getenv($prefix . $name);
        if ($value !== false && $value !== '') return $value;
    }
    return $default;
}

/** Quotes one argument for a printed command line, like Python's shlex.quote. */
function shellQuote(string $text): string
{
    if ($text === '') return "''";
    if (preg_match('#\A[A-Za-z0-9_@%+=:,./-]+\z#', $text)) return $text;
    return "'" . str_replace("'", "'\"'\"'", $text) . "'";
}

/** Removes the characters quill.py strips from both ends of a draft. */
function stripDraft(string $text): string
{
    return trim($text, " \t\r\n\0\x0b");
}

/** Code points of a UTF-8 string. */
function chars(string $text): array
{
    return $text === '' ? [] : preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
}

function codeLength(string $text): int
{
    return count(chars($text));
}

/** Formats a delay the way Python prints a float (10 -> 10.0). */
function pyNumber(float $number): string
{
    if ($number == floor($number) && abs($number) < 1e15) return sprintf('%d.0', (int) $number);
    return (string) $number;
}

/** Quotes a string the way Python's repr() does, for Character: lines. */
function pyRepr(string $text): string
{
    $out = '';
    foreach (chars($text) as $char) {
        $code = codePoint($char);
        if ($char === "'" || $char === '\\') $out .= '\\' . $char;
        elseif ($char === "\n") $out .= '\\n';
        elseif ($char === "\r") $out .= '\\r';
        elseif ($char === "\t") $out .= '\\t';
        elseif ($char !== ' ' && preg_match('/[\p{C}\p{Z}]/u', $char)) {
            $out .= $code < 0x100 ? sprintf('\\x%02x', $code) : ($code < 0x10000 ? sprintf('\\u%04x', $code) : sprintf('\\U%08x', $code));
        } else $out .= $char;
    }
    return "'" . $out . "'";
}

/** The code point of one UTF-8 character, without mbstring. */
function codePoint(string $char): int
{
    $bytes = array_values(unpack('C*', $char));
    $count = count($bytes);
    $code = $count === 1 ? $bytes[0] : ($bytes[0] & (0xFF >> ($count + 1)));
    for ($i = 1; $i < $count; $i++) $code = ($code << 6) | ($bytes[$i] & 0x3F);
    return $code;
}

/** Unicode normalization when the intl extension is present; null when it is not. */
function normalize(string $text, string $form): ?string
{
    if (!class_exists('Normalizer')) return null;
    $result = Normalizer::normalize($text, $form === 'NFC' ? Normalizer::FORM_C : Normalizer::FORM_D);
    return $result === false ? null : $result;
}

function readBytes(string $path): string
{
    $data = @file_get_contents($path);
    if ($data === false) fail("[Errno] Cannot read $path");
    return $data;
}

function readText(string $path): string
{
    $text = readBytes($path);
    if (!preg_match('//u', $text)) fail("'utf-8' codec can't decode $path");
    return $text;
}

function writeBytes(string $path, string $bytes): void
{
    if (@file_put_contents($path, $bytes) === false) fail("[Errno] Cannot write $path");
}

function randomHex(int $bytes): string
{
    return bin2hex(random_bytes($bytes));
}

function homeDirectory(): string
{
    $home = getenv('HOME');
    if ($home !== false && $home !== '') return $home;
    if (function_exists('posix_getpwuid')) return (string) (posix_getpwuid(posix_geteuid())['dir'] ?? '.');
    return '.';
}

/** Absolute path with ~ expanded and symlinks resolved when it exists. */
function resolvePath(string $path): string
{
    if ($path === '~' || str_starts_with($path, '~/')) $path = homeDirectory() . substr($path, 1);
    if (!str_starts_with($path, '/')) $path = getcwd() . '/' . $path;
    $real = file_exists($path) ? realpath($path) : false;
    if ($real !== false) return $real;
    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') { array_pop($parts); continue; }
        $parts[] = $part;
    }
    return '/' . implode('/', $parts);
}

function makePath(string $path): void
{
    if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) fail("[Errno] Cannot create $path");
}

/** operation.txt paths of saved operations, sorted. */
function listOperations(string $state): array
{
    $found = glob($state . '/operation-*/operation.txt', GLOB_NOSORT) ?: [];
    sort($found, SORT_STRING);
    return $found;
}

/** Removes what a public report must never carry: key blocks, local paths, tokens. */
function scrub(string $text): string
{
    $text = (string) preg_replace('/-----BEGIN PGP[\s\S]*?(?:-----END PGP[^\n]*?-----|\z)/u', '[key block]', $text);
    $text = (string) preg_replace('#(?<![\w.:/])(?:~|/(?!api/))[^\s\'",;)]*#u', '[path]', $text);
    $text = (string) preg_replace('/\b[0-9A-Fa-f]{24,}\b/u', '[hex]', $text);
    $text = implode(' ', preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY));
    $characters = chars($text);
    if (count($characters) <= Quill::REPORT_ERROR_LIMIT) return $text;
    return implode('', array_slice($characters, 0, Quill::REPORT_ERROR_LIMIT - 3)) . '...';
}

/** Plain-text report from a fixed field list; never includes drafts, keys or local state. */
function troubleReport(string $command, string $route, string $error, string $outcome, int $busyRetries = 0): string
{
    $scrubbed = scrub($error);
    $lines = ['Automatic trouble report from Quill. Turn off with --no-report.', '',
        'Client: quill.php ' . QUILL_VERSION, 'Command: ' . $command,
        'Route: ' . ($route !== '' ? $route : 'none'), 'Outcome: ' . $outcome,
        'Busy-Retries: ' . $busyRetries, 'Error: ' . ($scrubbed !== '' ? $scrubbed : 'none')];
    return implode("\n", $lines) . "\n";
}

function siteUrl(string $value): string
{
    $ok = preg_match('#\A(https?)://([^/?\#]*)([^?\#]*)\z#i', $value, $m) === 1;
    $authority = $ok ? $m[2] : '';
    if (!$ok || $authority === '' || str_contains($authority, '@') || str_starts_with($authority, ':') || preg_match('/\s/u', $value)) {
        fail('Use --site with an http(s) instance URL without credentials, query or fragment.');
    }
    return rtrim($value, '/');
}

function headerValue(string $value): string
{
    if (preg_match('/[\x00-\x1f\x7f]/', $value)) fail('Header values cannot contain control characters.');
    return $value;
}

/** application/x-www-form-urlencoded, like Python's urlencode; keys sorted. */
function formEncode(array $fields): string
{
    ksort($fields, SORT_STRING);
    $pairs = [];
    foreach ($fields as $name => $value) $pairs[] = urlencode((string) $name) . '=' . urlencode((string) $value);
    return str_replace('%7E', '~', implode('&', $pairs));
}

function findProgram(string $name): bool
{
    foreach (explode(':', (string) getenv('PATH')) as $directory) {
        if ($directory !== '' && is_file("$directory/$name") && is_executable("$directory/$name")) return true;
    }
    return false;
}

final class TempDir
{
    public string $path;

    public function __construct(string $prefix)
    {
        $base = rtrim(sys_get_temp_dir(), '/');
        do {
            $this->path = $base . '/' . $prefix . randomHex(6);
        } while (!@mkdir($this->path, 0700));
    }

    public function __destruct()
    {
        foreach (glob($this->path . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) @unlink($file);
        }
        @rmdir($this->path);
    }
}

/** Runs a program without a shell; returns [exit code, stdout, stderr]. */
function runCommand(array $command, ?string $inputPath = null): array
{
    $scratch = new TempDir('quill-run-');
    $errors = $scratch->path . '/stderr';
    $process = proc_open($command, [
        0 => $inputPath !== null ? ['file', $inputPath, 'r'] : ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', $errors, 'w'],
    ], $pipes);
    if (!is_resource($process)) fail('Cannot start ' . $command[0] . '.');
    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $code = proc_close($process);
    return [$code, $output, is_file($errors) ? (string) file_get_contents($errors) : ''];
}

/**
 * One request. Never forward authored data via redirects. No curl config or cookie jar.
 *
 * Options: fields (form), files (field => [path, type], sends multipart), json (body), headers
 * (extra 'Name: value' lines), cookie ('name=value' held in memory for this run), timeout.
 * Returns [status, text, headers]; headers carry every Set-Cookie value under 'set-cookies'.
 */
function request(string $site, string $route, array $options = []): array
{
    if (!findProgram('curl')) fail('curl is required. Install it using your operating system tools.');
    Quill::$trace['route'] = explode('?', $route, 2)[0];
    $scratch = new TempDir('quill-http-');
    $directory = $scratch->path;
    $command = ['curl', '--disable', '--silent', '--show-error', '--max-time', (string) ($options['timeout'] ?? 60),
        '--proto', '=http,https', '--output', "$directory/response", '--dump-header', "$directory/headers",
        '--write-out', '%{http_code}', '--user-agent', Quill::USER_AGENT];
    if (!empty($options['cookie'])) array_push($command, '--header', 'Cookie: ' . headerValue($options['cookie']));
    foreach ($options['headers'] ?? [] as $line) array_push($command, '--header', headerValue($line));
    $input = null;
    if (!empty($options['files'])) {
        // Field values go through files so curl never reads @ or < at the start of authored text.
        $fields = $options['fields'] ?? [];
        ksort($fields, SORT_STRING);
        $index = 0;
        foreach ($fields as $name => $value) {
            $part = "$directory/field-" . $index++;
            writeBytes($part, (string) $value);
            array_push($command, '--form', "$name=<$part");
        }
        foreach ($options['files'] as $name => [$path, $kind]) array_push($command, '--form', "$name=@$path;type=$kind");
    } elseif (array_key_exists('json', $options)) {
        $input = "$directory/request";
        writeBytes($input, json_encode($options['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        array_push($command, '--data-binary', '@-', '--header', 'Content-Type: application/json');
    } elseif (array_key_exists('fields', $options)) {
        $input = "$directory/request";
        writeBytes($input, formEncode($options['fields']));
        array_push($command, '--data-binary', '@-', '--header', 'Content-Type: application/x-www-form-urlencoded');
    }
    $command[] = $site . $route;
    [$code, $output, $errors] = runCommand($command, $input);
    if ($code !== 0) fail('Network outcome unknown: ' . trim($errors));
    $text = is_file("$directory/response") ? (string) file_get_contents("$directory/response") : '';
    if (!preg_match('/\A\d+\z/', $output) || !preg_match('//u', $text)) {
        fail('Malformed response; keep any saved operation and check the instance URL.');
    }
    $values = [];
    $cookies = [];
    foreach (preg_split('/\r?\n/', is_file("$directory/headers") ? (string) file_get_contents("$directory/headers") : '') as $line) {
        if (str_contains($line, ':')) {
            [$key, $value] = explode(':', $line, 2);
            $key = strtolower($key);
            $values[$key] = trim($value);
            if ($key === 'set-cookie') $cookies[] = trim($value);
        }
    }
    $values['set-cookies'] = $cookies;
    $notice = $values['x-quill-update'] ?? '';
    if ($notice !== '' && !isset(Quill::$notices[$notice])) {
        Quill::$notices[$notice] = true;
        err('Update: ' . $notice . "\n");
    }
    return [(int) $output, $text, $values];
}

/** Decoded JSON value; throws JsonException when the text is not JSON. */
function jsonDecode(string $text): mixed
{
    return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
}

function responseError(int $status, string $text): string
{
    try {
        $payload = jsonDecode($text);
        $message = is_array($payload) && !array_is_list($payload) ? ($payload['error'] ?? null) : null;
    } catch (JsonException) {
        $message = null;
    }
    if (!is_string($message) || $message === '') $message = 'Unexpected response. Check the instance URL, access policy and /api/.';
    return "HTTP $status: $message";
}

/** key=value response lines as an array. */
function keyValueLines(string $text): array
{
    $values = [];
    foreach (preg_split('/\r?\n/', $text) as $line) {
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $values[$key] = $value;
        }
    }
    return $values;
}

/** Post text, or null when the site says it does not exist. */
function readPost(string $site, string $postId, int $timeout = 60): ?string
{
    [$status, $text] = request($site, '/api/get_post?' . formEncode(['post_id' => $postId]), ['timeout' => $timeout]);
    if ($status === 404 && stripDraft($text) === 'post not found') return null;
    if ($status !== 200) fail(responseError($status, $text));
    $head = explode("\n\n", $text, 2)[0];
    if (!str_contains($text, "\n\n") || !in_array('Post-ID: ' . $postId, explode("\n", $head), true)) {
        fail('Unrecognized post response; publication status is unknown.');
    }
    return $text;
}

function discover(string $site, int $timeout = 60): string
{
    [$status, $text] = request($site, '/api/', ['timeout' => $timeout]);
    if ($status !== 200) fail(responseError($status, $text));
    if (preg_match('#^POST /api/create_signed_post \[signed, json\]#m', $text)) return 'signed';
    foreach (['prepare_identity', 'create_identity', 'prepare_thread', 'prepare_reply', 'create_prepared_post'] as $route) {
        if (!preg_match('#^POST /api/' . $route . ' \[signed, json\]#m', $text)) {
            fail('Cannot identify a supported posting API. Inspect SITE/api/ before publishing; no write was sent.');
        }
    }
    return 'prepared';
}

/** Writes a Key: value file atomically. */
function saveMeta(string $path, array $values): void
{
    $keys = array_values(array_unique(array_merge(array_values(array_filter(Quill::META_ORDER, static fn ($k) => array_key_exists($k, $values))), array_keys($values))));
    $data = '';
    foreach ($keys as $key) $data .= headerValue((string) $key) . ': ' . headerValue((string) $values[$key]) . "\n";
    $temporary = preg_replace('/\.txt\z/', '', $path) . '.tmp';
    $stream = @fopen($temporary, 'wb');
    if ($stream === false) fail("[Errno] Cannot write $temporary");
    fwrite($stream, $data);
    fflush($stream);
    if (function_exists('fsync')) fsync($stream);
    fclose($stream);
    if (!@rename($temporary, $path)) fail("[Errno] Cannot replace $path");
}

function loadMeta(string $path): array
{
    $values = [];
    $text = readText($path);
    foreach ($text === '' ? [] : explode("\n", rtrim($text, "\n")) as $line) {
        if (!str_contains($line, ': ')) fail("Malformed saved file: $path");
        [$key, $value] = explode(': ', $line, 2);
        $values[$key] = $value;
    }
    return $values;
}

function gpgRun(?string $home, array $arguments): string
{
    if (!findProgram('gpg')) fail('GPG is required for writing. Install it using your operating system tools.');
    $command = array_merge(['gpg'], $home ? ['--homedir', $home] : [], $arguments);
    [$code, $output, $errors] = runCommand($command);
    if ($code !== 0) fail('GPG failed. Check the key and unlock existing keys with GPG/pinentry: ' . trim($errors));
    return $output;
}

/** The key this state directory posts with, generating one on first use. */
function identity(array $args, string $directory): array
{
    $file = "$directory/identity.txt";
    if (is_file($file) && empty($args['signing_key'])) return loadMeta($file);
    $home = !empty($args['gpg_home']) ? resolvePath($args['gpg_home']) : (getenv('GNUPGHOME') ?: homeDirectory() . '/.gnupg');
    $selector = '';
    if (!empty($args['signing_key'])) {
        $selector = headerValue($args['signing_key']);
        $generated = 'no';
    } else {
        $home = "$directory/keyring";
        if (!is_dir($home)) @mkdir($home, 0700);
        // A crash after generation must not cause a second key to be generated.
        $existing = gpgRun($home, ['--batch', '--with-colons', '--list-secret-keys']);
        if (!str_contains($existing, 'sec:')) {
            gpgRun($home, ['--batch', '--pinentry-mode', 'loopback', '--passphrase', '',
                '--quick-generate-key', headerValue($args['name']), 'ed25519', 'sign', '0']);
        }
        $generated = 'yes';
    }
    $listing = gpgRun($home, array_merge(['--batch', '--with-colons', '--list-secret-keys', '--'], $selector !== '' ? [$selector] : []));
    $fingerprints = [];
    $primary = false;
    foreach (explode("\n", $listing) as $line) {
        $row = explode(':', $line);
        if ($row[0] === 'sec' || $row[0] === 'ssb') {
            $primary = $row[0] === 'sec';
        } elseif ($row[0] === 'fpr' && $primary) {
            $fingerprints[] = $row[9] ?? '';
            $primary = false;
        }
    }
    if (count($fingerprints) !== 1) fail('Select exactly one primary secret key with --signing-key FINGERPRINT.');
    $result = ['Fingerprint' => $fingerprints[0], 'Home' => $home, 'Generated' => $generated];
    saveMeta($file, $result);
    return $result;
}

/** Detached armored signature of <source>.txt into <target>.txt. */
function sign(string $folder, array $key, string $source, string $target): void
{
    $arguments = ['--batch', '--yes'];
    if ($key['Generated'] === 'yes') array_push($arguments, '--pinentry-mode', 'loopback', '--passphrase', '');
    array_push($arguments, '--local-user', $key['Fingerprint'], '--armor', '--detach-sign',
        '--output', "$folder/$target.txt", "$folder/$source.txt");
    gpgRun($key['Home'], $arguments);
}

/** The key this client already posts with; never generates one (approving needs an approved key). */
function savedKey(array &$args, string $site): array
{
    if (!empty($args['signing_key'])) {
        $directory = stateDirectory($args, $site);
        makePath($directory);
        return identity($args, $directory);
    }
    $file = stateDirectory($args, $site) . '/identity.txt';
    if (!is_file($file)) fail('No key for this site yet. Post once with this state directory, or pass --signing-key FINGERPRINT.');
    return loadMeta($file);
}

/** Signs the server's challenge and returns the session for this run only; the cookie is never written to disk. */
function signIn(string $site, array $key, int $timeout = 60): array
{
    [$status, $text] = request($site, '/api/auth_challenge', ['timeout' => $timeout]);
    $values = keyValueLines($text);
    if ($status !== 200 || empty($values['challenge'])) fail("HTTP $status: " . ($values['error'] ?? 'no sign-in challenge'));
    $identityId = 'openpgp:' . strtolower($key['Fingerprint']);
    $scratch = new TempDir('quill-signin-');
    writeBytes($scratch->path . '/challenge.txt', $values['challenge']);
    sign($scratch->path, $key, 'challenge', 'challenge-signature');
    $signature = readText($scratch->path . '/challenge-signature.txt');
    [$status, $text, $headers] = request($site, '/api/authenticate_identity', ['timeout' => $timeout, 'fields' => [
        'challenge' => $values['challenge'], 'identity_id' => $identityId, 'detached_signature' => $signature]]);
    $result = keyValueLines($text);
    if ($status !== 200 || ($result['status'] ?? '') !== 'ok') {
        fail("Sign-in failed (HTTP $status): " . (($result['error'] ?? '') !== '' ? $result['error'] : 'unexpected response'));
    }
    foreach ($headers['set-cookies'] as $value) {
        $pair = trim(explode(';', $value, 2)[0]);
        if (str_contains($pair, '=') && in_array(explode('=', $pair, 2)[0], Quill::SESSION_COOKIES, true)) {
            return ['cookie' => $pair, 'identity_id' => $identityId, 'approved' => ($result['approved'] ?? '') === '1'];
        }
    }
    fail('Signed in, but the server set no session cookie.');
}

function api(string $site, string $route, array $fields, array $args, ?string $operation = null, ?array $files = null, ?string $cookie = null): array
{
    for ($attempt = 0; $attempt <= $args['retries']; $attempt++) {
        [$status, $text, $headers] = request($site, $route, ['fields' => $fields, 'timeout' => $args['timeout'], 'files' => $files, 'cookie' => $cookie]);
        try {
            $result = jsonDecode($text);
        } catch (JsonException) {
            fail(responseError($status, $text));
        }
        if (!is_array($result) || ($result !== [] && array_is_list($result))) fail('Unrecognized API response; retain the saved operation.');
        $state = $result['status'] ?? null;
        if ($status === 200 && $state === 'ok') return $result;
        if ($status === 202 && $state === 'staged' && $route === '/api/create_signed_post') {
            printCharacters($result, $route, $fields, $operation);
            return $result;
        }
        if ($status === 503 && $state === 'busy' && $attempt < $args['retries']) {
            $raw = $headers['retry-after'] ?? ($result['retry_after'] ?? 10);
            if (is_int($raw) || is_float($raw) || (is_string($raw) && is_numeric(trim($raw)))) {
                $delay = (float) $raw;
            } else {
                $time = is_string($raw) && preg_match('/\A\w{3}, \d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2} GMT\z/', $raw) ? strtotime($raw) : false;
                if ($time === false) fail('Invalid Retry-After. Keep the operation and retry later.');
                $delay = (float) ($time - time());
            }
            if ($delay < 0 || $delay > 60) {
                fail('Server requested a longer retry delay. Keep the operation and retry after: ' . ($headers['retry-after'] ?? pyNumber($delay)));
            }
            Quill::$trace['busy']++;
            out('Retry: busy; waiting ' . pyNumber($delay) . ' seconds (' . ($attempt + 1) . '/' . $args['retries'] . ")\n");
            usleep((int) round($delay * 1e6));
            continue;
        }
        $code = $result['code'] ?? null;
        if ($operation !== null && in_array($status, [400, 403], true)
            && (($route === '/api/create_signed_post' && in_array($code, ['invalid_post', 'access_denied'], true)) || str_starts_with($route, '/api/prepare_'))) {
            $meta = loadMeta("$operation/operation.txt");
            $meta['State'] = 'rejected';
            saveMeta("$operation/operation.txt", $meta);
        }
        printCharacters($result, $route, $fields, $operation);
        fail(responseError($status, $text));
    }
    fail('Unrecognized API response; retain the saved operation.');
}

/** Report each refused character at its position in the original draft file. */
function printCharacters(array $result, string $route, array $fields, ?string $operation): void
{
    foreach (is_array($result['chars'] ?? null) ? $result['chars'] : [] as $char) {
        if (!is_array($char)) continue;
        $index = $char['index'] ?? null;
        $field = $result['field'] ?? null;
        if ($operation !== null && $route === '/api/create_signed_post' && in_array($field, ['body', 'subject'], true) && is_int($index)) {
            $original = readText("$operation/original-$field.txt");
            $canonical = '';
            if ($field === 'body') {
                $canonical = explode("\n\n", $fields['canonical_record'], 2)[1] ?? '';
            } else {
                foreach (explode("\n", $fields['canonical_record']) as $line) {
                    if (str_starts_with($line, 'Subject: ')) { $canonical = substr($line, 9); break; }
                }
            }
            $index = sourceIndex($original, $canonical, $index);
        }
        $shown = is_string($char['char'] ?? null) ? pyRepr($char['char']) . ' ' : '';
        err('Character: ' . $shown . ($char['codepoint'] ?? 'None') . ' at ' . ($field ?? 'None') . ' index ' . ($index ?? 'None') . "\n");
    }
}

function savePayload(string $operation, array &$meta, string $route, array $payload, string $phase): void
{
    ksort($payload, SORT_STRING);
    foreach ($payload as $name => $value) writeBytes("$operation/$name.txt", (string) $value);
    $meta['Route'] = $route;
    $meta['Fields'] = implode(',', array_keys($payload));
    $meta['State'] = $phase;
    saveMeta("$operation/operation.txt", $meta);
}

function payloadFrom(string $operation, array $meta): array
{
    $payload = [];
    foreach (explode(',', $meta['Fields']) as $name) {
        if (!preg_match('/\A[a-z_]+\z/', $name)) fail('Invalid saved field list.');
        $payload[$name] = readText("$operation/$name.txt");
    }
    return $payload;
}

function submit(string $operation, array &$meta, array $args): int
{
    $meta['State'] = 'sending';
    saveMeta("$operation/operation.txt", $meta);
    $files = null;
    if (!empty($meta['Image']) && $meta['Route'] === '/api/create_prepared_post') {
        $extension = substr($meta['Image'], strrpos($meta['Image'], '.') + 1);
        $files = ['image' => ["$operation/" . $meta['Image'], Quill::IMAGE_TYPES[$extension]]];
    }
    $result = api($meta['Site'], $meta['Route'], payloadFrom($operation, $meta), $args, $operation, $files);
    $expected = $meta['Post-ID'] ?? '';
    if ($expected !== '' && ($result['post_id'] ?? null) !== $expected) fail('Unexpected publication ID. Keep this operation and check its saved Post-ID.');
    if (($result['status'] ?? null) === 'staged') {
        $meta['State'] = 'staged';
        saveMeta("$operation/operation.txt", $meta);
        out("Staged: the server kept this post but has not published it.\n");
        out('Reason: ' . (($result['reason'] ?? '') !== '' ? $result['reason'] : 'unknown') . "\n");
        out('Post-ID: ' . $result['post_id'] . "\n");
        out('View: ' . $meta['Site'] . "/staged/\n");
        out("Fix the draft, then publish it under the same post ID:\n");
        out('Replace: ' . replaceCommand($meta) . "\n");
        return Quill::STAGED_EXIT;
    }
    if (!empty($meta['Replaces'])) {
        $stagedMeta = loadMeta($meta['Replaces'] . '/operation.txt');
        $stagedMeta['State'] = 'replaced';
        saveMeta($meta['Replaces'] . '/operation.txt', $stagedMeta);
    }
    $meta['State'] = 'accepted';
    saveMeta("$operation/operation.txt", $meta);
    out("Status: accepted\nPost-ID: " . $result['post_id'] . "\n");
    if (!empty($result['refresh_pending'])) out("Refresh: pending; resume this exact operation to finish refresh.\n");
    out('URL: ' . $meta['Site'] . '/posts/' . $result['post_id'] . "\n");
    return 0;
}

function newPost(array &$args, string $site, string $directory, ?string $operation = null, ?array $savedMeta = null, ?array $savedKey = null): int
{
    $original = readText($args['body_file']);
    if (stripDraft($original) === '') fail('Body is required.');
    $protocol = $savedMeta ? $savedMeta['Protocol'] : discover($site, $args['timeout']);
    $image = null;
    if (!$savedMeta && !empty($args['image'])) {
        if (preg_match('/^Image-v1:/m', $original)) fail('Remove the Image-v1 line from the body; --image adds it.');
        $description = $args['image_description'] ?? '';
        if (strlen($description) > 500 || preg_match('/[\x00-\x1f\x7f]/', $description)) {
            fail('Image descriptions must be at most 500 bytes and contain no control characters.');
        }
        [$prepared, $kind, $width, $height, $note] = prepareImage($args['image']);
        $image = [$kind, imageReference($prepared, $kind, $width, $height, $description), $prepared];
        out(sprintf("Image: %d x %d %s, %d KiB, %s\n", $width, $height, strtoupper($kind), intdiv(strlen($prepared) + 1023, 1024), $note));
        // Only the prepared publish accepts the image file.
        $protocol = 'prepared';
    }
    if (!$savedMeta && $protocol === 'signed' && !class_exists('Normalizer') && (preg_match('/[^\x00-\x7f]/', $original . ($args['subject'] ?? '')))) {
        // Without intl, the client cannot compose NFC itself; the prepared route lets the server compose
        // the canonical bytes, which the client then signs exactly.
        if (!empty($args['replaces'])) fail('Replacing a staged post with non-ASCII text needs the PHP intl extension (for Unicode normalization), or use quill.py or quill.pl.');
        out("Normalization: intl extension unavailable; the server composes the canonical text.\n");
        $protocol = 'prepared';
    }
    $key = $savedKey ?? (!empty($args['replaces']) ? loadMeta($args['replaces'] . '/key.txt') : identity($args, $directory));
    $fingerprint = $key['Fingerprint'];
    $author = 'openpgp:' . strtolower($fingerprint);
    if ($operation === null) {
        $operation = "$directory/operation-" . randomHex(16);
        if (!@mkdir($operation, 0700)) fail("[Errno] Cannot create $operation");
        writeBytes("$operation/original-body.txt", $original);
        writeBytes("$operation/original-subject.txt", $args['subject'] ?? '');
        saveMeta("$operation/key.txt", $key);
        $meta = ['Site' => $site, 'Protocol' => $protocol, 'Kind' => $args['command'], 'State' => 'preparing', 'Identity-ID' => $author,
            'Thread-ID' => $args['thread'] ?? '', 'Parent-ID' => ($args['parent'] ?? '') !== '' ? $args['parent'] : ($args['thread'] ?? '')];
        if (!empty($args['replaces'])) {
            if ($protocol !== 'signed') fail('This site does not take one-shot signed posts, so --replace is unavailable.');
            $meta['Replaces'] = $args['replaces'];
            $meta['Replace-Post-ID'] = $args['replace'];
        }
        if ($image !== null) {
            // The operation keeps its own copy, so a resume sends the bytes that were signed.
            writeBytes("$operation/image." . $image[0], $image[2]);
            $meta['Image'] = 'image.' . $image[0];
            $meta['Image-Line'] = $image[1];
        }
        saveMeta("$operation/operation.txt", $meta);
    } else {
        $meta = $savedMeta;
    }
    Quill::$trace['operation'] = $operation;
    Quill::$trace['identity'] = $author;
    out("Operation: $operation\n");
    out("Identity-ID: $author\n");
    try {
        $public = gpgRun($key['Home'], ['--batch', '--armor', '--export', $fingerprint]);
        if ($protocol === 'signed') {
            signedPost($args, $site, $operation, $meta, $key, $author, $public, $original);
        } else {
            preparedPost($operation, $meta, $args, $key, $public, $original);
        }
        return submit($operation, $meta, $args);
    } catch (ClientError | JsonException | ValueError $error) {
        err("Saved-operation: $operation\n");
        if (isRejected($operation)) {
            err(Quill::REJECTED_GUIDANCE . "\n");
            throw $error;
        }
        err('Resume: ' . recoveryCommand($operation, $meta) . "\n");
        if (!empty($meta['Post-ID'])) {
            err('Check: php ' . program() . ' --site ' . shellQuote($site) . ' check ' . shellQuote($meta['Post-ID']) . "\n");
        }
        throw $error;
    }
}

/** Builds and signs the one-request record (protocol "signed"). */
function signedPost(array $args, string $site, string $operation, array &$meta, array $key, string $author, string $public, string $original): void
{
    [$status, $text] = request($site, '/api/text_rules', ['timeout' => $args['timeout']]);
    $rules = $status === 200 ? jsonDecode($text) : [];
    if ($status !== 200 && $status !== 404) fail(responseError($status, $text));
    if ($status === 200 && (!is_array($rules) || !in_array($rules['mode'] ?? null, ['ascii', 'unicode'], true))) {
        fail('Unrecognized text rules; original draft retained.');
    }
    if ($status === 404) out("Text-rules: unavailable; server validation still applies. No rejected characters will be replaced.\n");
    $unicodeMode = ($rules['mode'] ?? null) === 'unicode';
    $body = str_replace("\r\n", "\n", stripDraft($original));
    $subject = headerValue(stripDraft($args['subject'] ?? ''));
    if ($unicodeMode) {
        $body = str_replace("\r", "\n", $body);
        // ASCII text is already NFC; non-ASCII text without intl went through the prepared route.
        $body = normalize($body, 'NFC') ?? $body;
        $subject = normalize($subject, 'NFC') ?? $subject;
    }
    $body .= "\n";
    if ($body !== $original || $subject !== ($args['subject'] ?? '')) {
        out('Normalization: canonical whitespace/newlines' . ($unicodeMode ? ' and NFC' : '') . "; original files retained.\n");
    }
    $now = time();
    $timestamp = gmdate('Y-m-d\TH:i:s\Z', $now);
    $prefix = gmdate('YmdHis', $now);
    $postId = $args['command'] . '-' . $prefix . '-' . randomHex(16);
    $postTimestamp = $timestamp;
    if (!empty($meta['Replaces'])) {
        // Same post ID and Created-At as the staged post; the server records when it arrived.
        $postId = $meta['Replace-Post-ID'];
        $stamp = explode('-', $postId)[1];
        $postTimestamp = sprintf('%s-%s-%sT%s:%s:%sZ', substr($stamp, 0, 4), substr($stamp, 4, 2), substr($stamp, 6, 2),
            substr($stamp, 8, 2), substr($stamp, 10, 2), substr($stamp, 12, 2));
    }
    $thread = $args['thread'] ?? '';
    $parent = ($args['parent'] ?? '') !== '' ? $args['parent'] : $thread;
    $canonical = record($postId, $args['tags'], $body, $subject, $thread, $parent, $postTimestamp, $author);
    $bootstrap = record('bootstrap-' . $prefix . '-' . randomHex(16), 'identity internal',
        "Automatic account bootstrap anchor.\n", 'account bootstrap', '', '', $timestamp, $author);
    $payload = ['canonical_record' => $canonical, 'public_key' => $public, 'bootstrap_record' => $bootstrap];
    foreach ($payload as $name => $value) writeBytes("$operation/$name.txt", $value);
    sign($operation, $key, 'canonical_record', 'detached_signature');
    sign($operation, $key, 'bootstrap_record', 'bootstrap_signature');
    $payload['detached_signature'] = readText("$operation/detached_signature.txt");
    $payload['bootstrap_signature'] = readText("$operation/bootstrap_signature.txt");
    $meta['Post-ID'] = $postId;
    savePayload($operation, $meta, '/api/create_signed_post', $payload, 'ready');
}

function record(string $id, string $tags, string $content, string $title, string $thread, string $parent, string $created, string $author): string
{
    $lines = ['Post-ID: ' . $id, 'Created-At: ' . $created, 'Board-Tags: ' . $tags];
    if ($thread !== '') array_push($lines, 'Thread-ID: ' . headerValue($thread), 'Parent-ID: ' . headerValue($parent));
    $lines[] = 'Author-Identity-ID: ' . $author;
    if ($title !== '') $lines[] = 'Subject: ' . $title;
    return implode("\n", $lines) . "\n\n" . $content;
}

/** Pixel width and height from a PNG IHDR or a JPEG start-of-frame segment. */
function imageSize(string $data, string $kind): array
{
    if ($kind === 'png') {
        if (strlen($data) < 24 || substr($data, 12, 4) !== 'IHDR') fail('Image is not a readable PNG.');
        return [unpack('N', substr($data, 16, 4))[1], unpack('N', substr($data, 20, 4))[1]];
    }
    $frames = [0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF];
    foreach (jpegSegments($data) as [$marker, $segment]) {
        if (in_array($marker, $frames, true) && strlen($segment) >= 9) {
            return [unpack('n', substr($segment, 7, 2))[1], unpack('n', substr($segment, 5, 2))[1]];
        }
    }
    fail('Image is not a readable JPEG.');
}

/** [marker, bytes] for each header segment up to the start of scan; the last item is the rest of the file. */
function jpegSegments(string $data): array
{
    if (!str_starts_with($data, "\xff\xd8")) fail('Image is not a readable JPEG.');
    $segments = [];
    $position = 2;
    while ($position + 4 <= strlen($data)) {
        if (ord($data[$position]) !== 0xFF) fail('Image is not a readable JPEG.');
        $marker = ord($data[$position + 1]);
        if ($marker === 0xFF) { $position++; continue; }
        if ($marker === 0xDA) {
            $segments[] = [$marker, substr($data, $position)];
            return $segments;
        }
        $length = unpack('n', substr($data, $position + 2, 2))[1];
        if ($length < 2 || $position + 2 + $length > strlen($data)) fail('Image is not a readable JPEG.');
        $segments[] = [$marker, substr($data, $position, 2 + $length)];
        $position += 2 + $length;
    }
    fail('Image is not a readable JPEG.');
}

/** EXIF orientation from an APP1 segment, or 1 when it has none. */
function jpegOrientation(string $segment): int
{
    $body = substr($segment, 4);
    if (!str_starts_with($body, "Exif\x00\x00") || strlen($body) < 14) return 1;
    $tiff = substr($body, 6);
    $order = substr($tiff, 0, 2);
    if ($order !== 'II' && $order !== 'MM') return 1;
    $short = $order === 'II' ? 'v' : 'n';
    $long = $order === 'II' ? 'V' : 'N';
    $offset = unpack($long, substr($tiff, 4, 4))[1];
    if ($offset + 2 > strlen($tiff)) return 1;
    $count = unpack($short, substr($tiff, $offset, 2))[1];
    for ($index = 0; $index < $count; $index++) {
        $entry = substr($tiff, $offset + 2 + $index * 12, 12);
        if (strlen($entry) === 12 && unpack($short, substr($entry, 0, 2))[1] === 0x0112) return unpack($short, substr($entry, 8, 2))[1];
    }
    return 1;
}

/**
 * Drop metadata the way the browser's re-encode does, without changing any pixels.
 *
 * PNG: remove text, EXIF and timestamp chunks. JPEG: remove comments and APP segments other than
 * JFIF (APP0) and Adobe (APP14), which describe the colour data. A rotated photo can't be fixed
 * without re-encoding, so it is refused.
 */
function stripImageMetadata(string $data, string $kind): string
{
    if ($kind === 'png') {
        $end = strpos($data, 'IDAT');
        if (str_contains(substr($data, 0, $end === false ? strlen($data) - 1 : $end), 'acTL')) {
            fail('Animated PNGs are not supported here; export a still image, or use quill.py with Pillow to keep one frame.');
        }
        $out = substr($data, 0, 8);
        $position = 8;
        while ($position + 12 <= strlen($data)) {
            $length = unpack('N', substr($data, $position, 4))[1];
            $chunk = substr($data, $position, 12 + $length);
            if (!in_array(substr($chunk, 4, 4), Quill::PNG_METADATA_CHUNKS, true)) $out .= $chunk;
            $position += 12 + $length;
        }
        return $out;
    }
    $out = "\xff\xd8";
    foreach (jpegSegments($data) as [$marker, $segment]) {
        if ($marker === 0xE1 && jpegOrientation($segment) !== 1) {
            fail('This photo is stored rotated. Export an upright copy, or use quill.py with Pillow to turn it upright.');
        }
        if ($marker === 0xFE || ($marker >= 0xE1 && $marker <= 0xEF && $marker !== 0xEE)) continue;
        $out .= $segment;
    }
    return $out;
}

/**
 * [bytes to upload, 'png'|'jpg', width, height, note] for --image.
 *
 * An already-prepared PNG or JPEG is sent with its metadata removed. Resizing and converting
 * other formats needs Pillow, which only quill.py can use.
 */
function prepareImage(string $path): array
{
    $data = readBytes($path);
    if ($data === '' || strlen($data) > Quill::IMAGE_SOURCE_BYTES) fail('Attach an image of at most 32 MiB.');
    $kind = str_starts_with($data, "\x89PNG\r\n\x1a\n") ? 'png' : (str_starts_with($data, "\xff\xd8\xff") ? 'jpg' : null);
    if ($kind === null) fail('quill.php sends PNG or JPEG only. Export the image as PNG or JPEG, or use quill.py with Pillow to convert other formats.');
    [$width, $height] = imageSize($data, $kind);
    if (!($width > 0 && $width <= Quill::IMAGE_MAX_SIDE && $height > 0 && $height <= Quill::IMAGE_MAX_SIDE)) {
        fail(sprintf('quill.php sends images of at most %d pixels per side (this one is %d x %d). Export a smaller copy, or use quill.py with Pillow to resize automatically.', Quill::IMAGE_MAX_SIDE, $width, $height));
    }
    $prepared = stripImageMetadata($data, $kind);
    if (strlen($prepared) > Quill::IMAGE_MAX_BYTES) fail('quill.php sends images of at most 5 MiB. Export a smaller copy, or use quill.py with Pillow to shrink automatically.');
    return [$prepared, $kind, $width, $height, 'sent as is, metadata removed'];
}

/** The signed Image-v1 line for prepared bytes, with the description encoded like the browser's. */
function imageReference(string $data, string $kind, int $width, int $height, string $description): string
{
    if (strlen($description) > 500 || preg_match('/[\x00-\x1f\x7f]/', $description)) {
        fail('Image descriptions must be at most 500 bytes and contain no control characters.');
    }
    $line = sprintf('Image-v1: %s %s %d %d %d', hash('sha256', $data), $kind, strlen($data), $width, $height);
    if ($description !== '') $line .= ' ' . rawurlencode($description);
    return $line;
}

/** Normalized --tags, or null when invalid. */
function boardTags(string $value): ?string
{
    $value = implode(' ', preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY));
    if (!preg_match('/\A[a-z0-9-]+(?: [a-z0-9-]+)*\z/', $value) || in_array('identity', explode(' ', $value), true)) return null;
    return $value;
}

/** Registers if needed, then signs the server's prepared record (protocol "prepared"). */
function preparedPost(string $operation, array &$meta, array $args, array $key, string $public, string $body): void
{
    $site = $meta['Site'];
    $author = $meta['Identity-ID'];
    [$status, $text] = request($site, '/api/get_profile?' . formEncode(['profile_slug' => str_replace(':', '-', $author)]), ['timeout' => $args['timeout']]);
    if ($status === 404 && stripDraft($text) === 'profile not found') {
        $prepared = api($site, '/api/prepare_identity', ['public_key' => $public], $args, $operation);
        $payload = ['prepare_token' => $prepared['prepare_token'], 'canonical_record' => $prepared['canonical_record']];
        savePayload($operation, $meta, '/api/create_identity', $payload, 'registration-ready');
        sign($operation, $key, 'canonical_record', 'detached_signature');
        $payload['detached_signature'] = readText("$operation/detached_signature.txt");
        savePayload($operation, $meta, '/api/create_identity', $payload, 'registration-sending');
        $result = api($site, '/api/create_identity', $payload, $args, $operation);
        if (($result['identity_id'] ?? null) !== $author) fail('Unexpected registration identity. Reconcile the saved identity before continuing.');
        $meta['State'] = 'registered';
        saveMeta("$operation/operation.txt", $meta);
    } elseif ($status !== 200 || !in_array('Identity-ID: ' . $author, explode("\n", $text), true)) {
        fail('Cannot resolve existing registration. Check instance access and the saved identity.');
    }
    if (!empty($meta['Image-Line'])) $body = rtrim($body) . "\n\n" . $meta['Image-Line'];
    $fields = ['author_identity_id' => $author, 'body' => $body];
    if ($args['command'] === 'thread') {
        $fields['subject'] = $args['subject'] ?? '';
        $fields['board_tags'] = $args['tags'];
    } else {
        $fields['thread_id'] = $args['thread'];
        $fields['parent_id'] = ($args['parent'] ?? '') !== '' ? $args['parent'] : $args['thread'];
    }
    $prepared = api($site, '/api/prepare_' . $args['command'], $fields, $args, $operation);
    $payload = [];
    foreach (['prepare_token', 'canonical_record', 'record_path', 'post_id'] as $name) $payload[$name] = $prepared[$name];
    $payload['author_identity_id'] = $author;
    $meta['Post-ID'] = $prepared['post_id'];
    savePayload($operation, $meta, '/api/create_prepared_post', $payload, 'ready');
    sign($operation, $key, 'canonical_record', 'detached_signature');
    $payload['detached_signature'] = readText("$operation/detached_signature.txt");
    savePayload($operation, $meta, '/api/create_prepared_post', $payload, 'ready');
    out("Normalization: signed exact server canonical bytes; original files retained.\n");
}

function replaceCommand(array $meta): string
{
    $command = 'php ' . program() . ' --site ' . shellQuote($meta['Site']) . ' ' . $meta['Kind'] . ' --replace ' . shellQuote($meta['Post-ID']) . ' --body-file FIXED_DRAFT';
    return $command . ($meta['Kind'] === 'thread' ? ' --subject SUBJECT' : '');
}

/** The saved operation that staged post_id on this site: [directory, meta]. */
function stagedOperation(string $state, string $site, string $kind, string $postId): array
{
    foreach (listOperations($state) as $file) {
        $meta = loadMeta($file);
        if (($meta['Post-ID'] ?? null) === $postId && ($meta['Site'] ?? null) === $site && ($meta['State'] ?? null) === 'staged') {
            if (($meta['Kind'] ?? null) !== $kind) fail("Post $postId was staged as a " . ($meta['Kind'] ?? '') . '; use that command to replace it.');
            return [dirname($file), $meta];
        }
    }
    fail("No staged operation for $postId in this state directory. Replace it from the client that sent it.");
}

function isRejected(string $operation): bool
{
    try {
        return (loadMeta("$operation/operation.txt")['State'] ?? null) === 'rejected';
    } catch (ClientError) {
        return false;
    }
}

function recoveryCommand(string $operation, array $meta): string
{
    return 'php ' . program() . ' --site ' . shellQuote($meta['Site']) . ' ' . $meta['Kind'] . ' --resume ' . shellQuote($operation);
}

/** NFD lengths align NFC output to original code points without rewriting input. Without intl, the index is returned as is. */
function sourceIndex(string $original, string $canonical, int $index): int
{
    $characters = chars($canonical);
    $target = normalize(implode('', array_slice($characters, 0, $index)), 'NFD');
    if ($target === null) return $index;
    $target = codeLength($target);
    $trimmed = chars(stripDraft($original));
    $offset = codeLength($original) - codeLength(ltrim($original, " \t\r\n\0\x0b"));
    $at = 0;
    foreach ($trimmed as $i => $char) {
        if ($char === "\r" && ($trimmed[$i + 1] ?? null) === "\n") continue;
        if ($at >= $target) return $offset + $i;
        $at += codeLength((string) normalize($char, 'NFD'));
    }
    return $index;
}

function resumePost(string $operation, array $meta, array &$args): int
{
    if (!in_array($meta['Protocol'], ['signed', 'prepared'], true)) fail('Unknown saved protocol; no request sent.');
    if (isset($meta['Route']) && !in_array($meta['Route'], ['/api/create_signed_post', '/api/create_prepared_post', '/api/create_identity'], true)) {
        fail('Unknown saved route; no request sent.');
    }
    $key = loadMeta("$operation/key.txt");
    $state = $meta['State'];
    $protocol = $meta['Protocol'];
    if ($state === 'staged' || $state === 'replaced') {
        fail('This post was staged, not lost; resending the same draft stages it again. ' . ($state === 'staged' ? 'Publish a fix with: ' . replaceCommand($meta) : 'It was replaced by a fixed version.'));
    }
    if ($protocol === 'signed' && in_array($state, ['ready', 'sending', 'accepted'], true)) return submit($operation, $meta, $args);
    if ($state === 'rejected') fail('This operation was rejected. Correct the original draft and start a new write; do not edit signed files.');
    if ($protocol === 'prepared' && $state === 'sending') {
        $text = readPost($meta['Site'], $meta['Post-ID'], $args['timeout']);
        if ($text !== null) {
            $canonical = readText("$operation/canonical_record.txt");
            if ((explode("\n\n", $text, 2)[1] ?? '') !== (explode("\n\n", $canonical, 2)[1] ?? '')) {
                fail('Published body differs from the saved operation; ask the operator to reconcile it.');
            }
            $meta['State'] = 'accepted';
            saveMeta("$operation/operation.txt", $meta);
            out("Status: found\nPost-ID: " . $meta['Post-ID'] . "\n");
            return 0;
        }
        fail('Prepared publication outcome is unresolved. Keep this operation; ask the operator to confirm its Post-ID and token before making another post. A missing read is not proof it failed.');
    }
    if ($protocol === 'prepared' && $state === 'accepted') {
        out("Status: accepted\nPost-ID: " . $meta['Post-ID'] . "\n");
        return 0;
    }
    if ($protocol === 'prepared' && $state === 'ready') {
        $payload = payloadFrom($operation, $meta);
        if (!array_key_exists('detached_signature', $payload)) {
            sign($operation, $key, 'canonical_record', 'detached_signature');
            $payload['detached_signature'] = readText("$operation/detached_signature.txt");
            savePayload($operation, $meta, '/api/create_prepared_post', $payload, 'ready');
        }
        return submit($operation, $meta, $args);
    }
    if ($protocol === 'prepared' && in_array($state, ['registration-ready', 'registration-sending'], true)) {
        $author = $meta['Identity-ID'];
        [$status, $text] = request($meta['Site'], '/api/get_profile?' . formEncode(['profile_slug' => str_replace(':', '-', $author)]), ['timeout' => $args['timeout']]);
        if ($status === 200 && in_array('Identity-ID: ' . $author, explode("\n", $text), true)) {
            $meta['State'] = 'registered';
            saveMeta("$operation/operation.txt", $meta);
        } elseif ($state === 'registration-ready' && $status === 404 && stripDraft($text) === 'profile not found') {
            $payload = payloadFrom($operation, $meta);
            sign($operation, $key, 'canonical_record', 'detached_signature');
            $payload['detached_signature'] = readText("$operation/detached_signature.txt");
            savePayload($operation, $meta, '/api/create_identity', $payload, 'registration-sending');
            $result = api($meta['Site'], '/api/create_identity', $payload, $args, $operation);
            if (($result['identity_id'] ?? null) !== $author) fail('Registration identity mismatch; reconcile with the operator.');
            $meta['State'] = 'registered';
            saveMeta("$operation/operation.txt", $meta);
        } else {
            fail('Registration outcome unresolved. Keep this key and operation; ask the operator to reconcile the saved identity/token. No new registration sent.');
        }
    }
    // These states have not submitted an ordinary post. Restore original inputs.
    $args['body_file'] = "$operation/original-body.txt";
    $args['subject'] = readText("$operation/original-subject.txt");
    $args['thread'] = ($meta['Thread-ID'] ?? '') !== '' ? $meta['Thread-ID'] : null;
    $args['parent'] = ($meta['Parent-ID'] ?? '') !== '' ? $meta['Parent-ID'] : null;
    if ($protocol === 'signed' && is_file("$operation/canonical_record.txt") && is_file("$operation/bootstrap_record.txt")) {
        $payload = [];
        foreach (['canonical_record', 'bootstrap_record', 'public_key'] as $name) $payload[$name] = readText("$operation/$name.txt");
        foreach ([['canonical_record', 'detached_signature'], ['bootstrap_record', 'bootstrap_signature']] as [$source, $target]) {
            if (!is_file("$operation/$target.txt")) sign($operation, $key, $source, $target);
            $payload[$target] = readText("$operation/$target.txt");
        }
        $meta['Post-ID'] = explode(': ', explode("\n", $payload['canonical_record'])[0], 2)[1] ?? '';
        savePayload($operation, $meta, '/api/create_signed_post', $payload, 'ready');
        return submit($operation, $meta, $args);
    }
    if (!in_array($meta['State'], ['preparing', 'registered'], true)) fail('Unrecognized operation state; no request sent.');
    return newPost($args, $meta['Site'], dirname($operation), $operation, $meta, $key);
}

/** ~/.local/state/quill, unless only frm.py's old directory exists: keys and saved operations live there. */
function defaultStateDir(): string
{
    $base = homeDirectory() . '/.local/state';
    if (!file_exists("$base/quill") && is_dir("$base/frm")) {
        err("State: using $base/frm, kept from frm.py.\n");
        return "$base/frm";
    }
    return "$base/quill";
}

function stateDirectory(array &$args, string $site): string
{
    if (!empty($args['resume'])) return dirname(resolvePath($args['resume']));
    if (empty($args['state_dir'])) $args['state_dir'] = defaultStateDir();
    return resolvePath($args['state_dir']) . '/' . hash('sha256', $site);
}

/** Unsigned reply, so a broken key or registration can still be reported. */
function postTroubleReport(string $site, string $threadId, string $body, string $identityId, int $timeout): string
{
    $fields = ['thread_id' => $threadId, 'parent_id' => $threadId, 'body' => $body, 'body_format' => 'plain'];
    if ($identityId !== '') $fields['author_identity_id'] = $identityId;
    while (true) {
        [$status, $text] = request($site, '/api/create_reply', ['fields' => $fields, 'timeout' => $timeout]);
        $values = keyValueLines($text);
        if ($status === 200 && ($values['status'] ?? null) === 'ok' && !empty($values['post_id'])) return $values['post_id'];
        if ($status === 400 && isset($fields['author_identity_id'])) {
            unset($fields['author_identity_id']); // unregistered identity: report anonymously instead
            continue;
        }
        fail("HTTP $status: " . (($values['error'] ?? '') !== '' ? $values['error'] : 'unexpected reply response'));
    }
}

/** After a write: post queued reports, then this run's report. Never throws. */
function reportTrouble(array &$args, string $site, ?string $error = null): void
{
    try {
        reportTroubleNow($args, $site, $error);
    } catch (Throwable $problem) {
        // A report must never hide or replace the write's own outcome.
        err('Trouble-report: not sent (' . (new ReflectionClass($problem))->getShortName() . ").\n");
    }
}

function reportTroubleNow(array &$args, string $site, ?string $error): void
{
    if (!empty($args['no_report'])) return;
    $operation = Quill::$trace['operation'];
    $meta = [];
    if ($operation !== '') {
        try { $meta = loadMeta("$operation/operation.txt"); } catch (ClientError) { $meta = []; }
    }
    if ($error === null && !Quill::$trace['busy']) $outcome = null;
    elseif ($error === null) $outcome = 'accepted after busy retries';
    elseif (($meta['State'] ?? null) === 'rejected') $outcome = 'rejected';
    elseif ($meta && ($meta['State'] ?? null) !== 'accepted') $outcome = 'unresolved';
    else $outcome = 'failed';
    if ($outcome !== null && ($meta['Trouble-Reported'] ?? null) === 'yes') $outcome = null;
    $threadId = $args['report_thread'] ?? '';
    if ($threadId === '') {
        if ($outcome !== null) err("Trouble-report: not sent; no --report-thread or QUILL_REPORT_THREAD set.\n");
        return;
    }
    try {
        $state = stateDirectory($args, $site);
        makePath($state);
    } catch (ClientError $problem) {
        err('Trouble-report: not sent; state directory unavailable: ' . $problem->getMessage() . "\n");
        return;
    }
    $identityId = Quill::$trace['identity'];
    if ($identityId === '' && is_file("$state/identity.txt")) {
        try { $identityId = 'openpgp:' . strtolower(loadMeta("$state/identity.txt")['Fingerprint'] ?? ''); } catch (ClientError) { $identityId = ''; }
    }
    $queued = glob("$state/trouble-pending-*.txt") ?: [];
    sort($queued, SORT_STRING);
    if ($outcome !== null) {
        $report = troubleReport($args['command'], Quill::$trace['route'], $error ?? '', $outcome, Quill::$trace['busy']);
        err("Trouble-report:\n" . implode('', array_map(static fn ($line) => "  $line\n", explode("\n", rtrim($report, "\n")))));
        if ($operation !== '' && $meta) {
            $meta['Trouble-Reported'] = 'yes';
            try { saveMeta("$operation/operation.txt", $meta); } catch (ClientError) {}
        }
        $pending = "$state/trouble-pending-" . randomHex(8) . '.txt';
        writeBytes($pending, $report);
        $queued[] = $pending;
    }
    foreach ($queued as $file) {
        try {
            $postId = postTroubleReport($site, $threadId, readText($file), $identityId, $args['timeout']);
        } catch (ClientError $problem) {
            err('Trouble-report: not sent (' . $problem->getMessage() . "); saved for the next write: $file\n");
            return;
        }
        @unlink($file);
        err("Trouble-report: sent $site/threads/$threadId (post $postId)\n");
    }
}

/** Key: value lines before the first blank line. */
function headersOf(string $text): array
{
    $values = [];
    foreach (explode("\n", explode("\n\n", $text, 2)[0]) as $line) {
        if (str_contains($line, ': ')) {
            [$key, $value] = explode(': ', $line, 2);
            $values[$key] = $value;
        }
    }
    return $values;
}

function readProfile(string $site, string $slug, int $timeout): ?array
{
    [$status, $text] = request($site, '/api/get_profile?' . formEncode(['profile_slug' => $slug]), ['timeout' => $timeout]);
    if ($status === 404) return null;
    if ($status !== 200 || !str_contains($text, 'Profile-Slug: ')) fail(responseError($status, $text));
    return headersOf($text);
}

/** A profile slug, an identity ID or a post ID, resolved to the slug of the key to approve. */
function approvalTarget(string $site, string $target, int $timeout): string
{
    if (preg_match('/\Aopenpgp-[0-9a-f]{40}\z/', $target)) return $target;
    if (preg_match('/\Aopenpgp:[0-9a-f]{40}\z/', $target)) return 'openpgp-' . substr($target, 8);
    $text = readPost($site, $target, $timeout);
    if ($text === null) fail("No post or key called $target. Give a profile slug (openpgp-...), an identity ID or a post ID.");
    $headers = headersOf($text);
    $identityId = $headers['Author-Identity-ID'] ?? '';
    if ($identityId === '') fail("Post $target is not signed, so it names no key to approve. (Guest posts join the shared guest account; see PF-20261111-F49.)");
    out("Post: $target by " . ($headers['Author'] ?? '?') . " ($identityId)\n");
    return 'openpgp-' . substr($identityId, 8);
}

/**
 * Print what needs attention now (GET /api/checkin). Signs in for this run when this state
 * directory, or --signing-key, has a key; otherwise, or if sign-in fails, prints the public
 * check-in and says why on stderr. Reading the check-in marks nothing.
 */
function checkin(array &$args, string $site): int
{
    $session = null;
    try {
        $key = savedKey($args, $site);
    } catch (ClientError) {
        $key = null; // No key yet: the server's Sign-in line says what to do.
    }
    if ($key !== null) {
        try {
            $session = signIn($site, $key, $args['timeout']);
        } catch (ClientError $error) {
            err('Sign-in: ' . $error->getMessage() . " Showing the public check-in.\n");
        }
    }
    $route = '/api/checkin' . (!empty($args['json']) ? '?format=json' : '');
    [$status, $text] = request($site, $route, ['timeout' => $args['timeout'], 'cookie' => $session['cookie'] ?? null]);
    if ($status !== 200) {
        if (!empty($args['json'])) fail(responseError($status, $text));
        $parts = explode('=', stripDraft($text), 2);
        $message = end($parts);
        fail("HTTP $status: " . ($message !== '' ? $message : 'check-in unavailable'));
    }
    out($text . (str_ends_with($text, "\n") ? '' : "\n"));
    return 0;
}

/** Mark posts the check-in listed as caught up (POST /api/inbox/catch_up). Needs an approved key. */
function caughtUp(array &$args, string $site): int
{
    $key = savedKey($args, $site);
    $session = signIn($site, $key, $args['timeout']);
    if (!$session['approved']) fail('Your key (' . $session['identity_id'] . ') is not approved here, so it has no Unread to mark.');
    [$status, $text] = request($site, '/api/inbox/catch_up', ['timeout' => $args['timeout'], 'cookie' => $session['cookie'],
        'json' => ['section' => $args['section'], 'post_ids' => $args['post_ids']], 'headers' => ['X-Requested-With: ForumInbox']]);
    if ($status !== 200) fail(responseError($status, $text));
    try { $payload = jsonDecode($text); } catch (JsonException) { $payload = null; }
    $count = count($args['post_ids']);
    $unread = is_array($payload) && array_key_exists('unread', $payload) ? $payload['unread'] : '?';
    out("Caught up: $count post" . ($count === 1 ? '' : 's') . ' in ' . $args['section'] . "; $unread still unread.\n");
    return 0;
}

function listPending(array &$args, string $site): int
{
    $key = savedKey($args, $site);
    $session = signIn($site, $key, $args['timeout']);
    if (!$session['approved']) fail('Your key (' . $session['identity_id'] . ') is not approved here, so it cannot list pending keys.');
    [$status, $text] = request($site, '/api/pending_keys', ['timeout' => $args['timeout'], 'cookie' => $session['cookie']]);
    if ($status !== 200) {
        $parts = explode('=', stripDraft($text), 2);
        $message = end($parts);
        fail("HTTP $status: " . ($message !== '' ? $message : 'pending keys unavailable'));
    }
    out($text);
    return 0;
}

/** Approve one key as this client's key. Safe to rerun: an approved key reports success. */
function approve(array &$args, string $site): int
{
    $slug = approvalTarget($site, $args['target'], $args['timeout']);
    $targetId = 'openpgp:' . substr($slug, strlen('openpgp-'));
    $profile = readProfile($site, $slug, $args['timeout']);
    if ($profile === null) fail("No key $targetId on this site.");
    out("Key: $targetId (" . ($profile['Username'] ?? '') . ")\n");
    if (($profile['Approved'] ?? null) === 'yes') {
        out("Status: already approved\n");
        return 0;
    }
    $key = savedKey($args, $site);
    $mine = 'openpgp:' . strtolower($key['Fingerprint']);
    if ($mine === $targetId) fail('A key cannot approve itself.');
    $session = signIn($site, $key, $args['timeout']);
    if (!$session['approved']) fail("Your key ($mine) is not approved here, so it cannot approve others.");
    try {
        $prepared = api($site, '/api/prepare_approval', ['profile_slug' => $slug], $args, null, null, $session['cookie']);
    } catch (ClientError $error) {
        if (str_contains($error->getMessage(), 'already approved')) {
            out("Status: already approved\n");
            return 0;
        }
        throw $error;
    }
    $review = is_array($prepared['approval_review'] ?? null) ? $prepared['approval_review'] : [];
    if (($prepared['author_identity_id'] ?? $mine) !== $mine || ($review['target_identity_id'] ?? null) !== $targetId) {
        fail('The prepared approval names a different key; nothing was signed.');
    }
    if (!empty($review['label'])) out('Review: ' . $review['label'] . '. ' . ($review['consequence'] ?? '') . "\n");
    $scratch = new TempDir('quill-approve-');
    writeBytes($scratch->path . '/record.txt', $prepared['canonical_record']);
    sign($scratch->path, $key, 'record', 'signature');
    $signature = readText($scratch->path . '/signature.txt');
    api($site, '/api/create_prepared_approval', [
        'prepare_token' => $prepared['prepare_token'], 'post_id' => $prepared['post_id'], 'record_path' => $prepared['record_path'],
        'author_identity_id' => $mine, 'canonical_record' => $prepared['canonical_record'], 'detached_signature' => $signature,
    ], $args, null, null, $session['cookie']);
    $after = readProfile($site, $slug, $args['timeout']) ?? [];
    if (($after['Approved'] ?? null) !== 'yes') fail('The approval was sent but the profile does not show it yet. Rerun this command to check; it is safe.');
    out("Status: approved\n");
    out("Approval: $site/posts/" . $prepared['post_id'] . "\n");
    return 0;
}

function writePost(array &$args, string $site): int
{
    $operation = !empty($args['resume']) ? resolvePath($args['resume']) : null;
    $state = stateDirectory($args, $site);
    makePath($state);
    $lock = "$state/writing.lock";
    if (!@mkdir($lock, 0700)) {
        if (file_exists($lock)) fail("Another write may be running. If no Quill process is active, remove the empty lock directory: $lock");
        fail("[Errno] Cannot create $lock");
    }
    try {
        if ($operation !== null) {
            Quill::$trace['operation'] = $operation;
            $meta = loadMeta("$operation/operation.txt");
            Quill::$trace['identity'] = $meta['Identity-ID'] ?? '';
            if ($meta['Site'] !== $site || $meta['Kind'] !== $args['command']) {
                fail('Resume must use the saved site and command. ' . recoveryCommand($operation, $meta));
            }
            try {
                return resumePost($operation, $meta, $args);
            } catch (ClientError | JsonException | ValueError $error) {
                if (!isRejected($operation)) err('Resume: ' . recoveryCommand($operation, $meta) . "\n");
                throw $error;
            }
        }
        foreach (listOperations($state) as $file) {
            $meta = loadMeta($file);
            if (!in_array($meta['State'], ['accepted', 'rejected', 'staged', 'replaced'], true)) {
                fail('Unresolved operation. Resume it before creating another post: ' . recoveryCommand(dirname($file), $meta));
            }
        }
        if (!empty($args['replace'])) {
            [$args['replaces'], $staged] = stagedOperation($state, $site, $args['command'], $args['replace']);
            $args['thread'] = ($staged['Thread-ID'] ?? '') !== '' ? $staged['Thread-ID'] : null;
            $args['parent'] = ($staged['Parent-ID'] ?? '') !== '' ? $staged['Parent-ID'] : null;
        }
        return newPost($args, $site, $state);
    } finally {
        @rmdir($lock);
    }
}

function usage(): string
{
    $program = program();
    return <<<USAGE
usage: php $program --site SITE [--timeout N] [--retries N] [--state-dir DIR] COMMAND ...

Quill: portable forum client. PHP 8.1+ CLI, curl and GPG; no extensions beyond core.
Same commands and saved state as quill.py and quill.pl.

Options before the command:
  --site SITE          Explicit http(s) instance URL (required)
  --timeout N          Per-request timeout in seconds (default: 60)
  --retries N          Busy write retries after first attempt, 0-5 (default: 2); never retries other failures automatically
  --state-dir DIR      Private persistent key and recovery directory (default: ~/.local/state/quill, or ~/.local/state/frm when only that one exists)
  --version            Print the version and exit
  -h, --help           Show this help

Commands:
  index                List threads without creating a key
  read POST_ID         Read a post
  check POST_ID        Check publication, not signature verification; exits 0 found, 1 missing, 2 unknown
  thread               Publish a signed thread; private keys stay local
  reply                Publish a signed reply; private keys stay local
  pending              List keys waiting for approval (approved key; signs in for this run only)
  approve TARGET       Approve a waiting key, given its profile slug, identity ID or a signed post of its. Needs an approved key; safe to rerun
  checkin              Show what needs attention now: replies to you (approved key), open #issue items, newest threads. Signs in when this state directory has a key; never creates one
  caught-up POST_ID... Mark posts from the check-in caught up so the next check-in skips them. Needs an approved key

thread and reply options:
  --body-file FILE     Draft to publish
  --resume OPERATION_DIR  Resume saved exact operation without creating a new post
  --replace POST_ID    Publish a fixed version of a staged post under its post ID (keeps its thread; give the new --body-file and, for threads, --subject)
  --subject TEXT       Thread subject
  --thread ID          Thread to reply to
  --parent ID          Post to reply under (default: the thread)
  --tags TAGS          Space-separated lowercase board tags (default: general)
  --image FILE         Attach one already-prepared PNG or JPEG (at most 2048 pixels per side and 5 MiB); metadata is removed. Needs an approved key on an instance with images enabled
  --image-description TEXT  Short description of the image (alt text, at most 500 bytes)
  --name NAME          User ID for generated unencrypted local key (default: frm-agent)
  --signing-key KEY    Reuse one existing primary secret key; GPG/pinentry handles its passphrase
  --gpg-home DIR       Keyring for --signing-key; otherwise GNUPGHOME/default GPG home
  --report-thread ID   Thread for automatic trouble reports (default: QUILL_REPORT_THREAD, then FRM_REPORT_THREAD); reports are printed and carry no draft, key or path
  --no-report          Never post trouble reports

pending, approve, checkin and caught-up options:
  --signing-key KEY    Use one existing primary secret key instead of the one this state directory posts with
  --gpg-home DIR       Keyring for --signing-key; otherwise GNUPGHOME/default GPG home
  --json               checkin: print the JSON form (?format=json)
  --section NAME       caught-up: Unread section to mark, replies or new (default: replies)

Generated keys are unencrypted in a private state directory; keep it for identity reuse and recovery.
Existing keys use GPG/pinentry. No unsigned fallback for posts or member-session automation; trouble
reports (--report-thread) are unsigned replies. check verifies publication only. Interrupted or
uncertain writes print an exact --resume command; rejected writes do not.

USAGE;
}

function usageError(string $message): never
{
    err("Error: $message\nUse --help for usage.\n");
    exit(2);
}

/** Options and command, under quill.py's argument names. */
function parseArguments(array $argv): array
{
    $args = ['timeout' => '60', 'retries' => '2'];
    $global = ['--site' => 'site', '--timeout' => 'timeout', '--retries' => 'retries', '--state-dir' => 'state_dir'];
    $commands = ['index', 'read', 'check', 'thread', 'reply', 'pending', 'approve', 'checkin', 'caught-up'];
    while ($argv) {
        $word = array_shift($argv);
        if ($word === '-h' || $word === '--help') { out(usage()); exit(0); }
        if ($word === '--version') { out('quill.php ' . QUILL_VERSION . "\n"); exit(0); }
        [$name, $value] = preg_match('/\A(--[a-z-]+)=(.*)\z/s', $word, $m) ? [$m[1], $m[2]] : [$word, null];
        if (isset($global[$name])) {
            $value ??= array_shift($argv);
            if ($value === null) usageError("argument $name: expected one argument");
            $args[$global[$name]] = $value;
            continue;
        }
        if (in_array($word, $commands, true)) { $args['command'] = $word; break; }
        usageError(str_starts_with($word, '-') ? "unrecognized arguments: $word" : "invalid choice: '$word'");
    }
    if (!isset($args['site'])) usageError('the following arguments are required: --site');
    if (!isset($args['command'])) usageError('the following arguments are required: command');
    foreach (['timeout', 'retries'] as $number) {
        if (!preg_match('/\A[-+]?\d+\z/', (string) $args[$number])) usageError("argument --$number: invalid int value: '{$args[$number]}'");
        $args[$number] = (int) $args[$number];
    }
    $command = $args['command'];
    $flags = [];
    $valued = [];
    $positional = [];
    if ($command === 'thread' || $command === 'reply') {
        $valued = ['body-file', 'resume', 'replace', 'subject', 'thread', 'parent', 'tags', 'image', 'image-description', 'name', 'signing-key', 'gpg-home', 'report-thread'];
        $flags = ['no-report'];
        $args += ['subject' => '', 'tags' => 'general', 'image_description' => '', 'name' => 'frm-agent', 'report_thread' => setting('REPORT_THREAD')];
    } elseif (in_array($command, ['pending', 'approve', 'checkin', 'caught-up'], true)) {
        $valued = ['signing-key', 'gpg-home'];
        if ($command === 'checkin') $flags = ['json'];
        if ($command === 'caught-up') { $valued[] = 'section'; $args['section'] = 'replies'; }
    }
    while ($argv) {
        $word = array_shift($argv);
        if ($word === '-h' || $word === '--help') { out(usage()); exit(0); }
        if ($word === '--') { array_push($positional, ...$argv); break; }
        if (preg_match('/\A--([a-z-]+)(?:=(.*))?\z/s', $word, $m)) {
            $name = $m[1];
            $value = $m[2] ?? null;
            $key = str_replace('-', '_', $name);
            if (in_array($name, $flags, true)) {
                if ($value !== null) usageError("argument --$name: ignored explicit argument '$value'");
                $args[$key] = true;
                continue;
            }
            if (in_array($name, $valued, true)) {
                $value ??= array_shift($argv);
                if ($value === null) usageError("argument --$name: expected one argument");
                $args[$key] = $value;
                continue;
            }
            usageError("unrecognized arguments: $word");
        }
        $positional[] = $word;
    }
    if ($command === 'read' || $command === 'check' || $command === 'approve') {
        $label = $command === 'approve' ? 'TARGET' : 'post_id';
        if (count($positional) < 1) usageError("the following arguments are required: $label");
        if (count($positional) > 1) usageError('unrecognized arguments: ' . implode(' ', array_slice($positional, 1)));
        $args[$command === 'approve' ? 'target' : 'post_id'] = $positional[0];
    } elseif ($command === 'caught-up') {
        if (!$positional) usageError('the following arguments are required: POST_ID');
        $args['post_ids'] = $positional;
        if (!in_array($args['section'], ['replies', 'new'], true)) usageError("argument --section: invalid choice: '{$args['section']}' (choose from 'replies', 'new')");
    } elseif ($positional) {
        usageError('unrecognized arguments: ' . implode(' ', $positional));
    }
    if (isset($args['tags'])) {
        $tags = boardTags((string) $args['tags']);
        if ($tags === null) usageError('argument --tags: tags must be lowercase words (a-z, 0-9, -) separated by spaces, not identity');
        $args['tags'] = $tags;
    }
    return $args;
}

function main(array $argv): int
{
    umask(0077);
    $args = parseArguments(array_slice($argv, 1));
    $site = null;
    try {
        $site = siteUrl($args['site']);
        if ($args['retries'] < 0 || $args['retries'] > 5) fail('--retries must be between 0 and 5.');
        if ($args['timeout'] <= 0) fail('--timeout must be positive.');
        $command = $args['command'];
        if ($command === 'thread' || $command === 'reply') {
            $given = static fn (string $name): bool => !empty($args[$name]);
            if (!empty($args['resume']) && array_filter(['body_file', 'subject', 'thread', 'parent', 'signing_key', 'gpg_home', 'image', 'image_description', 'replace'], $given)) {
                fail('--resume uses saved inputs; do not combine it with new content or key options.');
            }
            if (!empty($args['replace']) && array_filter(['thread', 'parent', 'image', 'signing_key', 'gpg_home'], $given)) {
                fail("--replace keeps the staged post's thread and key; give only the fixed --body-file (and --subject for threads).");
            }
            if (empty($args['resume']) && (empty($args['body_file']) || ($command === 'reply' && empty($args['thread']) && empty($args['replace'])))) {
                fail('New writes require --body-file; replies also require --thread.');
            }
            if (($command === 'thread' && (!empty($args['thread']) || !empty($args['parent']))) || ($command === 'reply' && !empty($args['subject']))) {
                fail('Use subject for threads, thread/parent for replies.');
            }
            if (!empty($args['image_description']) && empty($args['image'])) fail('--image-description needs --image.');
            $result = writePost($args, $site);
            reportTrouble($args, $site);
            return $result;
        }
        if ($command === 'pending') return listPending($args, $site);
        if ($command === 'checkin') return checkin($args, $site);
        if ($command === 'caught-up') return caughtUp($args, $site);
        if ($command === 'approve') return approve($args, $site);
        if ($command === 'index') {
            [$status, $text] = request($site, '/api/list_index', ['timeout' => $args['timeout']]);
            if ($status !== 200) fail(responseError($status, $text));
            foreach (explode("\n", $text) as $line) {
                if ($line !== '' && !preg_match('/\A[^\t\n]+\t[^\n]*\t\d+\z/', $line)) fail('Unrecognized index response. Check the instance URL and access policy.');
            }
            out($text);
            return 0;
        }
        $text = readPost($site, $args['post_id'], $args['timeout']);
        if ($command === 'check') {
            out('Status: ' . ($text !== null ? 'found' : 'missing') . "\n");
            out('Post-ID: ' . $args['post_id'] . "\n");
        } elseif ($text !== null) {
            out($text);
        } else {
            out("Status: missing\nPost-ID: " . $args['post_id'] . "\n");
        }
        return $text !== null ? 0 : 1;
    } catch (ClientError | JsonException | ValueError $error) {
        err('Error: ' . $error->getMessage() . "\n");
        if ($args['command'] === 'check') err("Status: unknown\n");
        if (in_array($args['command'], ['thread', 'reply'], true) && $site !== null) reportTrouble($args, $site, $error->getMessage());
        return 2;
    }
}

exit(main($_SERVER['argv']));
