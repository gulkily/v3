<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

/**
 * Naive Bayes model for the meta rubric, trained from this instance's own labels.
 * Each word counts once per post. Mostly Cyrillic posts are never meta.
 */
final class LocalMetaModel
{
    public const FORMAT = 'fastmod-local-meta-v1';
    public const CYRILLIC_SIGNAL = 'cyrillic';
    private const MIN_DOCUMENTS_PER_WORD = 2;
    private const TOP_WORDS = 5;

    /**
     * @param array<string,array{0:int,1:int}> $words word => [meta documents, not-meta documents]
     */
    private function __construct(
        private readonly array $words,
        private readonly int $metaDocuments,
        private readonly int $otherDocuments,
        private readonly ?float $threshold,
        private readonly string $builtAt,
    ) {}

    /** @param list<array{subject:string, body:string, meta:bool}> $examples */
    public static function train(array $examples, ?float $threshold = null, ?string $builtAt = null): self
    {
        $counts = [];
        $meta = 0;
        $other = 0;
        foreach ($examples as $example) {
            if (self::isCyrillic($example['subject'] . ' ' . $example['body'])) continue;
            $example['meta'] ? $meta++ : $other++;
            foreach (self::tokens($example['subject'], $example['body']) as $word) {
                $counts[$word] ??= [0, 0];
                $counts[$word][$example['meta'] ? 0 : 1]++;
            }
        }
        $counts = array_filter($counts, static fn (array $c): bool => $c[0] + $c[1] >= self::MIN_DOCUMENTS_PER_WORD);
        ksort($counts, SORT_STRING);
        return new self($counts, $meta, $other, $threshold, $builtAt ?? gmdate('Y-m-d\TH:i:s\Z'));
    }

    public function withThreshold(float $threshold): self
    {
        return new self($this->words, $this->metaDocuments, $this->otherDocuments, $threshold, $this->builtAt);
    }

    /** @return array{probability:float, top_words:list<string>, signals:list<string>} */
    public function score(string $subject, string $body): array
    {
        if (self::isCyrillic($subject . ' ' . $body)) {
            return ['probability' => 0.0, 'top_words' => [], 'signals' => [self::CYRILLIC_SIGNAL]];
        }
        $total = $this->metaDocuments + $this->otherDocuments;
        if ($this->metaDocuments === 0 || $this->otherDocuments === 0) {
            return ['probability' => $this->metaDocuments > 0 ? 1.0 : 0.0, 'top_words' => [], 'signals' => ['one_class_model']];
        }
        $logOdds = log($this->metaDocuments / $total) - log($this->otherDocuments / $total);
        $contributions = [];
        foreach (self::tokens($subject, $body) as $word) {
            if (!isset($this->words[$word])) continue;
            [$inMeta, $inOther] = $this->words[$word];
            // Bernoulli-style document frequencies with add-one smoothing.
            $contribution = log(($inMeta + 1) / ($this->metaDocuments + 2)) - log(($inOther + 1) / ($this->otherDocuments + 2));
            $contributions[$word] = $contribution;
            $logOdds += $contribution;
        }
        $probability = 1 / (1 + exp(-max(-700.0, min(700.0, $logOdds))));
        $towardMeta = $probability >= 0.5;
        $ranked = array_filter($contributions, static fn (float $c): bool => $towardMeta ? $c > 0 : $c < 0);
        $towardMeta ? arsort($ranked) : asort($ranked);
        return ['probability' => $probability, 'top_words' => array_slice(array_map('strval', array_keys($ranked)), 0, self::TOP_WORDS), 'signals' => []];
    }

    public function threshold(): ?float
    {
        return $this->threshold;
    }

    /** @return array{meta:int, other:int, words:int} */
    public function counts(): array
    {
        return ['meta' => $this->metaDocuments, 'other' => $this->otherDocuments, 'words' => count($this->words)];
    }

    public function builtAt(): string
    {
        return $this->builtAt;
    }

    /** Identity of what the model scores with; the build time is not part of it. */
    public function buildId(): string
    {
        return substr(hash('sha256', json_encode([self::FORMAT, $this->metaDocuments, $this->otherDocuments, $this->words], JSON_THROW_ON_ERROR)), 0, 16);
    }

    public function toJson(): string
    {
        return json_encode([
            'format' => self::FORMAT,
            'build_id' => $this->buildId(),
            'built_at' => $this->builtAt,
            'threshold' => $this->threshold,
            'meta_documents' => $this->metaDocuments,
            'other_documents' => $this->otherDocuments,
            'words' => (object) $this->words,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['format'] ?? null) !== self::FORMAT
            || !is_int($data['meta_documents'] ?? null) || !is_int($data['other_documents'] ?? null)
            || !is_array($data['words'] ?? null) || !is_string($data['built_at'] ?? null)) {
            throw new \InvalidArgumentException('Unrecognized local meta model.');
        }
        $threshold = $data['threshold'] ?? null;
        if ($threshold !== null && (!is_numeric($threshold) || $threshold < 0 || $threshold > 1)) {
            throw new \InvalidArgumentException('Invalid local meta threshold.');
        }
        $words = [];
        foreach ($data['words'] as $word => $pair) {
            if (!is_array($pair) || count($pair) !== 2 || !is_int($pair[0] ?? null) || !is_int($pair[1] ?? null)) {
                throw new \InvalidArgumentException('Invalid local meta word counts.');
            }
            $words[(string) $word] = [$pair[0], $pair[1]];
        }
        $model = new self($words, $data['meta_documents'], $data['other_documents'], $threshold === null ? null : (float) $threshold, $data['built_at']);
        if (isset($data['build_id']) && $data['build_id'] !== $model->buildId()) {
            throw new \InvalidArgumentException('Local meta model build id does not match its counts.');
        }
        return $model;
    }

    public static function isCyrillic(string $text): bool
    {
        $letters = preg_match_all('/\p{L}/u', $text);
        if (!$letters) return false;
        return preg_match_all('/\p{Cyrillic}/u', $text) * 2 > $letters;
    }

    /** @return list<string> Distinct Latin words of two or more characters, minus common function words. */
    public static function tokens(string $subject, string $body): array
    {
        preg_match_all('/[a-z][a-z0-9]+/', strtolower($subject . "\n" . $body), $matches);
        $words = [];
        foreach ($matches[0] as $word) {
            if (!isset(self::STOPWORDS[$word])) $words[$word] = true;
        }
        return array_map('strval', array_keys($words));
    }

    private const STOPWORDS = [
        'a' => true, 'an' => true, 'and' => true, 'are' => true, 'as' => true, 'at' => true, 'be' => true,
        'but' => true, 'by' => true, 'for' => true, 'from' => true, 'has' => true, 'have' => true, 'he' => true,
        'her' => true, 'his' => true, 'in' => true, 'is' => true, 'it' => true, 'its' => true, 'of' => true,
        'on' => true, 'or' => true, 'that' => true, 'the' => true, 'their' => true, 'them' => true,
        'they' => true, 'this' => true, 'to' => true, 'was' => true, 'were' => true, 'which' => true,
        'with' => true, 'you' => true, 'your' => true,
    ];
}
