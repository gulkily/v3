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
        $number = static fn (?float $value): string => $value === null ? 'none' : number_format($value, 2);
        $lines = [
            'Built-At: ' . $report['built_at'],
            'Build: ' . $report['build_id'],
            'Weak-Negatives: ' . ($report['weak_negatives'] ? 'yes' : 'no'),
            sprintf('Meta-Labels: %d (authored %d, LLM %d)', $report['meta'], $labels['authored'], $labels['llm_meta']),
            sprintf('Not-Meta-Labels: %d (LLM %d, suppressed %d, weak %d)', $report['other'], $labels['llm_other'], $labels['suppressed'], $labels['weak_other']),
            sprintf('Skipped: Russian %d, LLM unsure %d, unlabeled %d', $labels['cyrillic'], $labels['uncertain'], $labels['unlabeled']),
            sprintf('Held-Out: %d (%d meta)', $report['holdout'], $report['holdout_meta']),
            'Threshold: ' . $number($report['threshold']),
            'Precision: ' . $number($report['precision']),
            'Recall: ' . $number($report['recall']),
            'Disagreements: ' . count($report['disagreements']),
        ];
        $text = implode("\n", $lines) . "\n";
        foreach ($report['disagreements'] as $item) {
            $subject = trim(preg_replace('/\s+/', ' ', (string) $item['subject']) ?? '');
            $text .= "\nPost: {$item['post_id']}\nLabel: {$item['label']} ({$item['label_source']})\nScore: " . number_format((float) $item['score'], 2)
                . "\nWords: " . (implode(', ', $item['top_words']) ?: 'none') . "\nSubject: " . ($subject === '' ? '(none)' : $subject) . "\n";
        }
        return $text;
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
