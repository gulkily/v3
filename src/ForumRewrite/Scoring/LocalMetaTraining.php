<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

use PDO;

/**
 * Builds the local meta model from labels people and the LLM already gave this instance.
 * Never reads local scores, so the model doesn't learn from its own guesses.
 */
final class LocalMetaTraining
{
    public const MIN_PER_CLASS = 20;
    public const LLM_META_AT_OR_ABOVE = 0.90;
    public const LLM_OTHER_BELOW = 0.10;
    public const TARGET_PRECISION = 0.95;
    private const HOLDOUT_ONE_IN = 5;

    public function __construct(private readonly PDO $read, private readonly ?PDO $scores) {}

    /**
     * @return array{examples:list<array{post_id:string,subject:string,body:string,meta:bool,label_source:string}>, counts:array<string,int>}
     */
    public function collect(bool $weakNegatives): array
    {
        $llm = $this->llmMetaScores();
        $suppressed = $this->suppressed();
        $hasher = new FastScoreContextFactory(static fn (string $_): ?array => null);
        $counts = ['authored' => 0, 'llm_meta' => 0, 'llm_other' => 0, 'suppressed' => 0, 'weak_other' => 0,
            'cyrillic' => 0, 'uncertain' => 0, 'unlabeled' => 0];
        $examples = [];
        $rows = $this->read->query('SELECT p.post_id, p.subject, p.body, p.board_tags_json, p.post_tags_json, t.thread_labels_json
            FROM posts p JOIN threads t ON t.root_post_id = p.post_id
            WHERE p.post_id = p.thread_id AND p.is_hidden = 0 AND p.retracted_at IS NULL
            ORDER BY p.post_id');
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $postId = (string) $row['post_id'];
            $subject = (string) ($row['subject'] ?? '');
            $body = (string) $row['body'];
            if (LocalMetaModel::isCyrillic($subject . ' ' . $body)) {
                $counts['cyrillic']++;
                continue;
            }
            $score = $llm[$postId][$hasher->contentHashForPost($row)] ?? null;
            if (isset($suppressed[$postId])) {
                [$meta, $source] = [false, 'suppressed'];
            } elseif (self::authoredMeta($row)) {
                [$meta, $source] = [true, 'authored'];
            } elseif ($score !== null && $score >= self::LLM_META_AT_OR_ABOVE) {
                [$meta, $source] = [true, 'llm_meta'];
            } elseif ($score !== null && $score < self::LLM_OTHER_BELOW) {
                [$meta, $source] = [false, 'llm_other'];
            } elseif ($score !== null) {
                $counts['uncertain']++;
                continue;
            } elseif ($weakNegatives) {
                [$meta, $source] = [false, 'weak_other'];
            } else {
                $counts['unlabeled']++;
                continue;
            }
            $counts[$source]++;
            $examples[] = ['post_id' => $postId, 'subject' => $subject, 'body' => $body, 'meta' => $meta, 'label_source' => $source];
        }
        return ['examples' => $examples, 'counts' => $counts];
    }

    /**
     * Trains on four fifths, picks a threshold on the held-out fifth, then trains the saved model on everything.
     *
     * @return array{model:LocalMetaModel, report:array<string,mixed>}
     */
    public function run(bool $weakNegatives, ?string $builtAt = null): array
    {
        ['examples' => $examples, 'counts' => $counts] = $this->collect($weakNegatives);
        $meta = count(array_filter($examples, static fn (array $e): bool => $e['meta']));
        $other = count($examples) - $meta;
        if ($meta < self::MIN_PER_CLASS || $other < self::MIN_PER_CLASS) {
            throw new LocalMetaTrainingRefused($counts, $meta, $other, $weakNegatives);
        }
        $train = [];
        $holdout = [];
        foreach ($examples as $example) {
            if (hexdec(substr(hash('sha256', $example['post_id']), 0, 6)) % self::HOLDOUT_ONE_IN === 0) $holdout[] = $example;
            else $train[] = $example;
        }
        $trial = LocalMetaModel::train($train, null, $builtAt);
        $scored = [];
        foreach ($holdout as $example) {
            $scored[] = $example + $trial->score($example['subject'], $example['body']);
        }
        [$threshold, $precision, $recall] = self::chooseThreshold($scored);
        $cut = $threshold ?? 0.5;
        $disagreements = array_values(array_filter($scored, static fn (array $s): bool => ($s['probability'] >= $cut) !== $s['meta']));
        usort($disagreements, static fn (array $a, array $b): int => abs($b['probability'] - $cut) <=> abs($a['probability'] - $cut));
        $model = LocalMetaModel::train($examples, $threshold, $builtAt);
        return ['model' => $model, 'report' => [
            'built_at' => $model->builtAt(),
            'build_id' => $model->buildId(),
            'weak_negatives' => $weakNegatives,
            'labels' => $counts,
            'meta' => $meta,
            'other' => $other,
            'holdout' => count($holdout),
            'holdout_meta' => count(array_filter($holdout, static fn (array $e): bool => $e['meta'])),
            'threshold' => $threshold,
            'precision' => $precision,
            'recall' => $recall,
            'disagreements' => array_map(static fn (array $s): array => [
                'post_id' => $s['post_id'], 'label' => $s['meta'] ? 'meta' : 'not meta', 'label_source' => $s['label_source'],
                'score' => $s['probability'], 'top_words' => $s['top_words'], 'subject' => $s['subject'],
            ], $disagreements),
        ]];
    }

    /**
     * Trains, then saves the model and report. A refusal saves nothing and leaves the previous model in place.
     *
     * @return array<string,mixed> the report
     */
    public function runAndSave(LocalMetaFiles $files, bool $weakNegatives): array
    {
        ['model' => $model, 'report' => $report] = $this->run($weakNegatives);
        $files->saveModel($model);
        $files->saveReport($report);
        return $report;
    }

    /**
     * The widest held-out cut at which precision still reaches the target; null when none does.
     * The threshold sits halfway, in log-odds, between the last score kept and the next one,
     * because naive Bayes pushes scores toward 0 and 1 and a cut right at a held-out score is fragile.
     *
     * @param list<array{meta:bool, probability:float}> $scored
     * @return array{0:?float, 1:?float, 2:?float} threshold, precision, recall
     */
    public static function chooseThreshold(array $scored): array
    {
        $positives = count(array_filter($scored, static fn (array $s): bool => $s['meta']));
        if ($positives === 0) return [null, null, null];
        usort($scored, static fn (array $a, array $b): int => $b['probability'] <=> $a['probability']);
        $best = [null, null, null];
        $truePositive = 0;
        foreach ($scored as $index => $item) {
            if ($item['meta']) $truePositive++;
            $next = $scored[$index + 1]['probability'] ?? null;
            // Only cut between distinct scores, so ties land on the same side.
            if ($next === $item['probability']) continue;
            $precision = (float) ($truePositive / ($index + 1));
            if ($precision >= self::TARGET_PRECISION) {
                $threshold = $next === null ? $item['probability'] : self::logOddsMidpoint($item['probability'], $next);
                $best = [$threshold, $precision, (float) ($truePositive / $positives)];
            }
        }
        return $best;
    }

    private static function logOddsMidpoint(float $higher, float $lower): float
    {
        $logit = static function (float $p): float {
            $p = min(1 - 1e-12, max(1e-12, $p));
            return log($p / (1 - $p));
        };
        $midpoint = 1 / (1 + exp(-(($logit($higher) + $logit($lower)) / 2)));
        // Keep the cut strictly between the two scores even where they saturate.
        return $midpoint > $lower && $midpoint < $higher ? $midpoint : ($higher + $lower) / 2;
    }

    /** @param array<string,mixed> $row */
    private static function authoredMeta(array $row): bool
    {
        foreach (['thread_labels_json', 'board_tags_json', 'post_tags_json'] as $column) {
            $values = json_decode((string) ($row[$column] ?? '[]'), true);
            if (is_array($values) && in_array('meta', array_map(static fn ($v): string => strtolower((string) $v), $values), true)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string,array<string,float>> post ID => content hash => latest stored LLM meta score */
    private function llmMetaScores(): array
    {
        if ($this->scores === null || !$this->hasTable('post_fast_scores')) return [];
        $columns = $this->scores->query('PRAGMA table_info(post_fast_scores)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('scores_json', $columns, true)) return [];
        $rows = $this->scores->query("SELECT post_id, content_hash, scores_json FROM post_fast_scores
            WHERE status = 'scored' AND source = 'llm' AND scores_json IS NOT NULL ORDER BY updated_at, rowid");
        $scores = [];
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $value = json_decode((string) $row['scores_json'], true)['meta'] ?? null;
            if ((is_float($value) || is_int($value)) && $value >= 0 && $value <= 1) {
                $scores[(string) $row['post_id']][(string) $row['content_hash']] = (float) $value;
            }
        }
        return $scores;
    }

    /** @return array<string,true> */
    private function suppressed(): array
    {
        if ($this->scores === null || !$this->hasTable('fastmod_meta')) return [];
        return array_fill_keys($this->scores->query('SELECT post_id FROM fastmod_meta WHERE suppressed = 1')->fetchAll(PDO::FETCH_COLUMN), true);
    }

    private function hasTable(string $name): bool
    {
        $stmt = $this->scores->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
        $stmt->execute([$name]);
        return $stmt->fetchColumn() !== false;
    }
}
