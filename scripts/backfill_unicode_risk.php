<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Analysis\DedalusPostAnalyzer;
use ForumRewrite\Analysis\PostAnalysisService;
use ForumRewrite\Analysis\SqlitePostAnalysisStore;
use ForumRewrite\Analysis\SqliteUnicodeRiskStore;
use ForumRewrite\Analysis\StubPostAnalyzer;
use ForumRewrite\Analysis\UnicodeRiskInspector;
use ForumRewrite\Support\LocalRepositoryBootstrap;
use ForumRewrite\Support\PrivateConfig;

const ANALYSIS_SCHEMA_VERSION = 5;
const UNICODE_RISK_SCHEMA_VERSION = 1;

$projectRoot = dirname(__DIR__);
$defaultRepositoryRoot = LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot);
$args = array_values(array_slice($argv, 1));
$withLlm = false;
$filteredArgs = [];
foreach ($args as $arg) {
    if ($arg === '--with-llm') {
        $withLlm = true;
        continue;
    }
    if ($arg === '--deterministic-only') {
        $withLlm = false;
        continue;
    }
    $filteredArgs[] = $arg;
}

$repositoryRoot = $filteredArgs[0] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: $defaultRepositoryRoot);
$databasePath = $filteredArgs[1] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3'));

try {
    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    assertPostsTableExists($pdo);

    $unicodeStore = new SqliteUnicodeRiskStore($pdo);
    $inspector = new UnicodeRiskInspector();
    $service = $withLlm
        ? new PostAnalysisService(new SqlitePostAnalysisStore($pdo), analyzerFromConfig($projectRoot), $inspector, $unicodeStore)
        : null;

    $posts = $pdo->query('SELECT post_id, thread_id, parent_id, subject, body, board_tags_json, author_label, sequence_number FROM posts ORDER BY sequence_number')->fetchAll();
    $summary = [
        'scanned' => 0,
        'changed' => 0,
        'high' => 0,
        'medium' => 0,
        'low' => 0,
        'none' => 0,
        'provider_failures' => 0,
    ];

    foreach ($posts as $post) {
        $summary['scanned']++;
        $postId = (string) $post['post_id'];
        $contentHash = contentHash($post);
        $existing = $unicodeStore->find($postId, $contentHash);
        $needsDeterministicScan = $existing === null || (int) ($existing['schema_version'] ?? 0) !== UNICODE_RISK_SCHEMA_VERSION;

        if ($needsDeterministicScan) {
            $facts = $inspector->inspectPost((string) ($post['subject'] ?? ''), (string) $post['body']);
            $risk = $unicodeStore->saveDeterministic($postId, $contentHash, UNICODE_RISK_SCHEMA_VERSION, $facts);
            $summary['changed']++;
        } else {
            $risk = $existing;
        }

        if ($withLlm && hasSignals($risk)) {
            $beforeStatus = (string) ($risk['status'] ?? '');
            $analysis = $service?->analyze(analysisContext($post, $contentHash));
            $risk = $unicodeStore->find($postId, $contentHash) ?? $risk;
            if (($analysis['status'] ?? null) !== 'complete') {
                $summary['provider_failures']++;
            } elseif ((string) ($risk['status'] ?? '') !== $beforeStatus) {
                $summary['changed']++;
            }
        }

        $summary[derivedPriority($risk)]++;
    }

    fwrite(STDOUT, "Unicode risk backfill complete\n");
    fwrite(STDOUT, "Repository: {$repositoryRoot}\n");
    fwrite(STDOUT, "Database: {$databasePath}\n");
    fwrite(STDOUT, 'Mode: ' . ($withLlm ? 'provider-enabled' : 'deterministic-only') . "\n");
    fwrite(STDOUT, sprintf(
        "Scanned: %d, changed: %d, high: %d, medium: %d, low: %d, none: %d, provider failures: %d\n",
        $summary['scanned'],
        $summary['changed'],
        $summary['high'],
        $summary['medium'],
        $summary['low'],
        $summary['none'],
        $summary['provider_failures'],
    ));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n\n" . usageText());
    exit(1);
}

function assertPostsTableExists(PDO $pdo): void
{
    $exists = (int) $pdo->query('SELECT COUNT(*) FROM sqlite_master WHERE type = "table" AND name = "posts"')->fetchColumn();
    if ($exists !== 1) {
        throw new RuntimeException('Read model database does not contain a posts table. Run ./v3 rebuild first.');
    }
}

/**
 * @param array<string, mixed> $post
 */
function contentHash(array $post): string
{
    return hash('sha256', json_encode([
        'analysis_schema_version' => ANALYSIS_SCHEMA_VERSION,
        'post_id' => (string) $post['post_id'],
        'subject' => (string) ($post['subject'] ?? ''),
        'body' => (string) $post['body'],
    ], JSON_THROW_ON_ERROR));
}

/**
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function analysisContext(array $post, string $contentHash): array
{
    return [
        'post_id' => (string) $post['post_id'],
        'content_hash' => $contentHash,
        'analysis_schema_version' => ANALYSIS_SCHEMA_VERSION,
        'post_kind' => (string) $post['post_id'] === (string) $post['thread_id'] ? 'thread' : 'reply',
        'thread_id' => (string) $post['thread_id'],
        'parent_id' => isset($post['parent_id']) ? (string) $post['parent_id'] : null,
        'subject' => isset($post['subject']) ? (string) $post['subject'] : '',
        'body' => limitAnalysisText((string) $post['body'], 6000),
        'board_tags' => decodeStringList((string) ($post['board_tags_json'] ?? '[]')),
        'author_label' => (string) ($post['author_label'] ?? 'guest'),
        'thread_subject' => '',
        'thread_body_preview' => '',
        'parent_body_preview' => '',
    ];
}

function limitAnalysisText(string $value, int $maxLength): string
{
    if (strlen($value) <= $maxLength) {
        return $value;
    }

    return substr($value, 0, $maxLength) . "\n[truncated]";
}

/**
 * @return list<string>
 */
function decodeStringList(string $json): array
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded) || !array_is_list($decoded)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $decoded), static fn (string $value): bool => $value !== ''));
}

function analyzerFromConfig(string $projectRoot): ?ForumRewrite\Analysis\PostAnalyzer
{
    $config = PrivateConfig::load($projectRoot);
    $mode = strtolower(trim((string) ($config['DEDALUS_ANALYSIS_MODE'] ?? '')));
    if ($mode === 'stub') {
        return new StubPostAnalyzer();
    }

    $apiKey = trim((string) ($config['DEDALUS_API_KEY'] ?? ''));
    if ($apiKey === '') {
        return null;
    }

    $promptPath = trim((string) ($config['DEDALUS_POST_ANALYSIS_PROMPT_PATH'] ?? ''));

    return new DedalusPostAnalyzer(
        $apiKey,
        trim((string) ($config['DEDALUS_API_BASE_URL'] ?? 'https://api.dedaluslabs.ai')) ?: 'https://api.dedaluslabs.ai',
        trim((string) ($config['DEDALUS_MODEL'] ?? 'openai/gpt-5-nano')) ?: 'openai/gpt-5-nano',
        max(60, (int) ($config['DEDALUS_TIMEOUT_SECONDS'] ?? 60)),
        $promptPath !== '' ? $promptPath : null,
    );
}

/**
 * @param array<string, mixed> $risk
 */
function hasSignals(array $risk): bool
{
    $facts = $risk['deterministic_facts'] ?? [];
    if (!is_array($facts)) {
        return false;
    }

    foreach (($facts['fields'] ?? []) as $fieldFacts) {
        if (is_array($fieldFacts) && is_array($fieldFacts['risk_labels'] ?? null) && $fieldFacts['risk_labels'] !== []) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $risk
 */
function derivedPriority(array $risk): string
{
    $review = $risk['llm_review'] ?? [];
    if (is_array($review) && in_array((string) ($review['review_priority'] ?? ''), ['high', 'medium', 'low', 'none'], true)) {
        return (string) $review['review_priority'];
    }

    $labels = [];
    $facts = $risk['deterministic_facts'] ?? [];
    if (is_array($facts)) {
        foreach (($facts['fields'] ?? []) as $fieldFacts) {
            if (!is_array($fieldFacts) || !is_array($fieldFacts['risk_labels'] ?? null)) {
                continue;
            }
            foreach ($fieldFacts['risk_labels'] as $label) {
                $labels[(string) $label] = true;
            }
        }
    }

    if (isset($labels['unsafe_rejected']) || isset($labels['directionality_risk'])) {
        return 'high';
    }
    if (isset($labels['confusable_identifier_like_text']) || isset($labels['normalization_risk']) || isset($labels['invisible_or_spacing_risk'])) {
        return 'medium';
    }
    if (isset($labels['mixed_script'])) {
        return 'low';
    }

    return 'none';
}

function usageText(): string
{
    return "Usage: php scripts/backfill_unicode_risk.php [repository_root] [database_path] [--deterministic-only|--with-llm]\n";
}
