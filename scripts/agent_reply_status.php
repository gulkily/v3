<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

$projectRoot = dirname(__DIR__);

try {
    $options = parseOptions(array_slice($argv, 1));
    if (($options['help'] ?? false) === true) {
        fwrite(STDOUT, usageText());
        exit(0);
    }

    $databasePath = (string) ($options['database-path'] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')));
    $postId = trim((string) ($options['post-id'] ?? ''));
    $limit = max(1, min(100, (int) ($options['limit'] ?? ($postId === '' ? 25 : 10))));

    if (!is_file($databasePath)) {
        throw new RuntimeException('Database not found: ' . $databasePath);
    }

    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    fwrite(STDOUT, "Agent reply status\n");
    fwrite(STDOUT, "Database: {$databasePath}\n");
    fwrite(STDOUT, 'Post filter: ' . ($postId === '' ? '(recent skipped rows)' : $postId) . "\n");
    fwrite(STDOUT, "Limit: {$limit}\n\n");

    if (!tableExists($pdo, 'post_generated_responses')) {
        fwrite(STDOUT, "No post_generated_responses table exists yet.\n");
        exit(0);
    }

    $rows = $postId === ''
        ? recentSkippedRows($pdo, $limit)
        : rowsForPost($pdo, $postId, $limit);

    if ($rows === []) {
        fwrite(STDOUT, $postId === ''
            ? "No skipped agent reply rows found.\n"
            : "No agent reply generation rows found for {$postId}.\n");
        if ($postId !== '' && tableExists($pdo, 'post_analyses')) {
            printAnalysisOnlyRows($pdo, $postId);
        }
        exit(0);
    }

    foreach ($rows as $index => $row) {
        if ($index > 0) {
            fwrite(STDOUT, "\n");
        }
        printReplyRow($row);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n\n" . usageText());
    exit(1);
}

/**
 * @param list<string> $args
 * @return array<string, string|int|bool>
 */
function parseOptions(array $args): array
{
    $options = [];
    foreach ($args as $arg) {
        if ($arg === '-h' || $arg === '--help') {
            $options['help'] = true;
            continue;
        }
        if (str_starts_with($arg, '--post-id=')) {
            $options['post-id'] = substr($arg, strlen('--post-id='));
            continue;
        }
        if (str_starts_with($arg, '--database-path=')) {
            $options['database-path'] = substr($arg, strlen('--database-path='));
            continue;
        }
        if (str_starts_with($arg, '--limit=')) {
            $limit = filter_var(substr($arg, strlen('--limit=')), FILTER_VALIDATE_INT);
            if ($limit === false) {
                throw new RuntimeException('Limit must be an integer.');
            }
            $options['limit'] = $limit;
            continue;
        }
        if (str_starts_with($arg, '--')) {
            throw new RuntimeException('Unknown option: ' . $arg);
        }
        if (isset($options['post-id'])) {
            throw new RuntimeException('Only one post id may be supplied.');
        }
        $options['post-id'] = $arg;
    }

    return $options;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name LIMIT 1");
    $stmt->execute(['name' => $table]);

    return $stmt->fetchColumn() !== false;
}

/**
 * @return list<array<string, mixed>>
 */
function recentSkippedRows(PDO $pdo, int $limit): array
{
    $stmt = $pdo->prepare(replyStatusQuery('WHERE pgr.status = :status ORDER BY pgr.id DESC LIMIT :limit'));
    $stmt->bindValue(':status', 'skipped', PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * @return list<array<string, mixed>>
 */
function rowsForPost(PDO $pdo, string $postId, int $limit): array
{
    $stmt = $pdo->prepare(replyStatusQuery('WHERE pgr.target_post_id = :post_id ORDER BY pgr.id DESC LIMIT :limit'));
    $stmt->bindValue(':post_id', $postId, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function replyStatusQuery(string $where): string
{
    return "SELECT
            pgr.id,
            pgr.target_post_id,
            pgr.target_content_hash,
            pgr.status AS reply_status,
            pgr.failure_code AS reply_failure,
            pgr.failure_message AS reply_message,
            pgr.agent_post_id,
            pgr.requested_at AS reply_requested_at,
            pgr.completed_at AS reply_completed_at,
            pgr.request_context_json,
            pa.status AS analysis_status,
            pa.failure_code AS analysis_failure,
            pa.failure_message AS analysis_message,
            pa.provider AS analysis_provider,
            pa.provider_model AS analysis_provider_model,
            pa.requested_at AS analysis_requested_at,
            pa.completed_at AS analysis_completed_at,
            pa.raw_response_json AS analysis_raw_response_json,
            pa.respondability_json,
            pa.engagement_json,
            pa.moderation_json
        FROM post_generated_responses pgr
        LEFT JOIN post_analyses pa
          ON pa.post_id = pgr.target_post_id
         AND pa.content_hash = pgr.target_content_hash
        {$where}";
}

function printReplyRow(array $row): void
{
    fwrite(STDOUT, 'Request #' . value($row['id'] ?? null) . "\n");
    printField('target_post_id', $row['target_post_id'] ?? null);
    printField('content_hash', $row['target_content_hash'] ?? null);
    printField('reply_status', $row['reply_status'] ?? null);
    printField('reply_failure', $row['reply_failure'] ?? null);
    printField('reply_message', $row['reply_message'] ?? null);
    printField('agent_post_id', $row['agent_post_id'] ?? null);
    printField('reply_requested_at', $row['reply_requested_at'] ?? null);
    printField('reply_completed_at', $row['reply_completed_at'] ?? null);

    $skipDetails = jsonObject($row['request_context_json'] ?? null)['agent_reply_skip'] ?? null;
    if (is_array($skipDetails) && $skipDetails !== []) {
        fwrite(STDOUT, "  skip_details:\n");
        foreach ($skipDetails as $key => $detail) {
            printField((string) $key, scalarValue($detail), 4);
        }
    }

    if (($row['analysis_status'] ?? null) === null) {
        fwrite(STDOUT, "  analysis: no matching post_analyses row for this content hash\n");
        return;
    }

    fwrite(STDOUT, "  analysis:\n");
    printField('status', $row['analysis_status'] ?? null, 4);
    printField('failure_code', $row['analysis_failure'] ?? null, 4);
    printField('failure_message', $row['analysis_message'] ?? null, 4);
    printField('provider', $row['analysis_provider'] ?? null, 4);
    printField('provider_model', $row['analysis_provider_model'] ?? null, 4);
    printField('requested_at', $row['analysis_requested_at'] ?? null, 4);
    printField('completed_at', $row['analysis_completed_at'] ?? null, 4);
    $providerDiagnostics = jsonObject($row['analysis_raw_response_json'] ?? null);
    if ($providerDiagnostics !== []) {
        fwrite(STDOUT, "  provider_diagnostics:\n");
        $request = is_array($providerDiagnostics['request'] ?? null) ? $providerDiagnostics['request'] : [];
        $response = is_array($providerDiagnostics['response'] ?? null) ? $providerDiagnostics['response'] : [];
        $error = is_array($providerDiagnostics['error'] ?? null) ? $providerDiagnostics['error'] : [];
        printField('request.method', scalarValue($request['method'] ?? null), 4);
        printField('request.url', scalarValue($request['url'] ?? null), 4);
        printField('request.model', scalarValue($request['payload']['model'] ?? null), 4);
        printField('request.body', scalarValue($request['body'] ?? null), 4);
        printField('response.status_code', scalarValue($response['status_code'] ?? null), 4);
        printField('response.headers', scalarValue($response['headers'] ?? null), 4);
        printField('response.body', scalarValue($response['body'] ?? null), 4);
        printField('response.decoded', scalarValue($response['decoded'] ?? null), 4);
        printField('error.class', scalarValue($error['class'] ?? null), 4);
        printField('error.message', scalarValue($error['message'] ?? null), 4);
    }

    $respondability = jsonObject($row['respondability_json'] ?? null);
    $engagement = jsonObject($row['engagement_json'] ?? null);
    $moderation = jsonObject($row['moderation_json'] ?? null);
    if ($respondability !== [] || $engagement !== [] || $moderation !== []) {
        fwrite(STDOUT, "  gate_inputs:\n");
        printField('respondability.should_generate_response', scalarValue($respondability['should_generate_response'] ?? null), 4);
        printField('respondability.overall_score', scalarValue($respondability['overall_score'] ?? null), 4);
        printField('respondability.response_risk', scalarValue($respondability['response_risk'] ?? null), 4);
        printField('engagement.response_should_be_public', scalarValue($engagement['response_should_be_public'] ?? null), 4);
        printField('moderation.severity', scalarValue($moderation['severity'] ?? null), 4);
    }
}

function printAnalysisOnlyRows(PDO $pdo, string $postId): void
{
    if (!tableExists($pdo, 'post_analyses')) {
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT status, failure_code, failure_message, provider, provider_model, requested_at, completed_at
         FROM post_analyses
         WHERE post_id = :post_id
         ORDER BY requested_at DESC
         LIMIT 5'
    );
    $stmt->execute(['post_id' => $postId]);
    $rows = $stmt->fetchAll();
    if ($rows === []) {
        fwrite(STDOUT, "No post_analyses rows found for {$postId}.\n");
        return;
    }

    fwrite(STDOUT, "\nAnalysis rows for {$postId}:\n");
    foreach ($rows as $row) {
        fwrite(STDOUT, "  - status=" . value($row['status'] ?? null)
            . ' failure_code=' . value($row['failure_code'] ?? null)
            . ' failure_message=' . value($row['failure_message'] ?? null)
            . ' provider=' . value($row['provider'] ?? null)
            . ' provider_model=' . value($row['provider_model'] ?? null)
            . ' requested_at=' . value($row['requested_at'] ?? null)
            . ' completed_at=' . value($row['completed_at'] ?? null)
            . "\n");
    }
}

function printField(string $name, mixed $value, int $indent = 2): void
{
    $normalized = value($value);
    if ($normalized === '(none)') {
        return;
    }

    fwrite(STDOUT, str_repeat(' ', $indent) . $name . ': ' . $normalized . "\n");
}

function value(mixed $value): string
{
    if ($value === null || $value === '') {
        return '(none)';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_scalar($value)) {
        return (string) $value;
    }

    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '(unprintable)';
}

function scalarValue(mixed $value): mixed
{
    if ($value === null || is_scalar($value)) {
        return $value;
    }

    return $value;
}

/**
 * @return array<string, mixed>
 */
function jsonObject(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    try {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

function usageText(): string
{
    return "Usage: php scripts/agent_reply_status.php [post_id] [--post-id=<id>] [--limit=25] [--database-path=<path>]\n"
        . "       ./v3 agent-reply status [post_id] [--post-id=<id>] [--limit=25] [--database-path=<path>]\n"
        . "Shows read-only diagnostics for skipped or failed agent reply generation rows.\n";
}
