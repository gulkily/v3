<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

/** Private files for the local meta model: the model itself and the last training report. */
final class LocalMetaFiles
{
    public function __construct(private readonly string $projectRoot) {}

    public function modelPath(): string
    {
        return $this->projectRoot . '/state/private/fastmod_local_meta.json';
    }

    /** Operator labels: one `post_id: meta` or `post_id: not` per line; `#` starts a comment. */
    public function labelsPath(): string
    {
        return $this->projectRoot . '/state/private/fastmod_local_meta_labels.txt';
    }

    /** @return array<string,bool> post ID => meta; empty when the file doesn't exist */
    public function loadLabels(): array
    {
        if (!is_file($this->labelsPath())) return [];
        $text = @file_get_contents($this->labelsPath());
        if ($text === false) throw new \RuntimeException('Cannot read the local meta labels file.');
        return self::parseLabels($text);
    }

    /** @return array<string,bool> */
    public static function parseLabels(string $text): array
    {
        $labels = [];
        foreach (preg_split('/\R/', $text) ?: [] as $number => $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');
            if ($line === '') continue;
            if (!preg_match('/^([A-Za-z0-9_-]+)\s*:\s*(meta|not)$/i', $line, $match)) {
                throw new \InvalidArgumentException(sprintf('Local meta labels line %d should look like "thread-...: meta" or "thread-...: not".', $number + 1));
            }
            $labels[$match[1]] = strtolower($match[2]) === 'meta';
        }
        return $labels;
    }

    public function reportPath(): string
    {
        return $this->projectRoot . '/state/private/fastmod_local_meta_report.txt';
    }

    /** Null when no model has been trained; throws when the file exists but can't be used. */
    public function loadModel(): ?LocalMetaModel
    {
        if (!is_file($this->modelPath())) return null;
        $json = @file_get_contents($this->modelPath());
        if ($json === false) throw new \RuntimeException('Cannot read the local meta model.');
        // A model saved in an older format scores differently; treat it as missing until retrained.
        if ((json_decode($json, true)['format'] ?? null) !== LocalMetaModel::FORMAT) return null;
        return LocalMetaModel::fromJson($json);
    }

    public function saveModel(LocalMetaModel $model): void
    {
        $this->write($this->modelPath(), $model->toJson());
    }

    /** @param array<string,mixed> $report */
    public function saveReport(array $report): void
    {
        $this->write($this->reportPath(), self::reportText($report));
    }

    public function readReport(): ?string
    {
        if (!is_file($this->reportPath())) return null;
        $text = @file_get_contents($this->reportPath());
        return $text === false ? null : $text;
    }

    /**
     * Summary as `Key: value` lines, then one block per held-out disagreement.
     *
     * @param array<string,mixed> $report
     */
    public static function reportText(array $report): string
    {
        $labels = $report['labels'];
        $number = static fn (?float $value): string => $value === null ? 'none' : self::probability($value);
        $lines = [
            'Built-At: ' . $report['built_at'],
            'Build: ' . $report['build_id'],
            'Weak-Negatives: ' . ($report['weak_negatives'] ? 'yes' : 'no'),
            sprintf('Meta-Labels: %d (operator %d, authored %d, LLM %d)', $report['meta'], $labels['operator_meta'], $labels['authored'], $labels['llm_meta']),
            sprintf('Not-Meta-Labels: %d (operator %d, LLM %d, suppressed %d, weak %d)', $report['other'], $labels['operator_other'], $labels['llm_other'], $labels['suppressed'], $labels['weak_other']),
            sprintf('Skipped: Russian %d, LLM unsure %d, unlabeled %d', $labels['cyrillic'], $labels['uncertain'], $labels['unlabeled']),
            sprintf('Cross-Checked: %d (%d meta, %d folds)', $report['checked'], $report['checked_meta'], $report['folds']),
            'Threshold: ' . $number($report['threshold']),
            'Precision: ' . $number($report['precision']),
            'Recall: ' . $number($report['recall']),
            'Disagreements: ' . ($report['disagreement_count'] ?? count($report['disagreements']))
                . (($report['disagreement_count'] ?? 0) > count($report['disagreements']) ? ' (first ' . count($report['disagreements']) . ' listed)' : ''),
        ];
        $text = implode("\n", $lines) . "\n";
        foreach ($report['disagreements'] as $item) {
            $subject = trim(preg_replace('/\s+/', ' ', (string) $item['subject']) ?? '');
            $text .= "\nPost: {$item['post_id']}\nLabel: {$item['label']} ({$item['label_source']})\nScore: " . self::probability((float) $item['score'])
                . "\nWords: " . (implode(', ', $item['top_words']) ?: 'none') . "\nSubject: " . ($subject === '' ? '(none)' : $subject) . "\n";
        }
        return $text;
    }

    /** Two decimals, but never a rounded 0.00 or 1.00 for a score that isn't exactly that: naive Bayes often lands very close. */
    public static function probability(float $value): string
    {
        if ($value > 0.995 && $value < 1.0) return '>0.99';
        if ($value > 0.0 && $value < 0.005) return '<0.01';
        return number_format($value, 2);
    }

    /**
     * Reads a saved report back: summary lines, then disagreement blocks.
     *
     * @return array{summary:list<array{0:string,1:string}>, disagreements:list<array<string,string>>}
     */
    public static function parseReport(string $text): array
    {
        $blocks = preg_split("/\n\n+/", trim($text)) ?: [];
        $fields = static function (string $block): array {
            $pairs = [];
            foreach (explode("\n", $block) as $line) {
                $parts = explode(': ', $line, 2);
                if (count($parts) === 2) $pairs[] = $parts;
            }
            return $pairs;
        };
        $summary = $fields((string) array_shift($blocks));
        $disagreements = array_map(static fn (string $block): array => array_column($fields($block), 1, 0), $blocks);
        return ['summary' => $summary, 'disagreements' => $disagreements];
    }

    private function write(string $path, string $text): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create the private settings directory.');
        }
        $temporary = tempnam($directory, '.local-meta-');
        if ($temporary === false) throw new \RuntimeException('Cannot save ' . basename($path) . '.');
        try {
            chmod($temporary, 0600);
            if (file_put_contents($temporary, $text) !== strlen($text) || !rename($temporary, $path)) {
                throw new \RuntimeException('Cannot save ' . basename($path) . '.');
            }
        } finally {
            if (is_file($temporary)) @unlink($temporary);
        }
    }
}
