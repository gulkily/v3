<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

final class FastScoreResult
{
    /** @param array<string,float> $scores */
    public static function scoredSet(array $scores, string $source): array
    {
        if ($scores === []) {
            throw new \InvalidArgumentException('A scored result requires at least one rubric.');
        }
        foreach ($scores as $id => $value) {
            if (!is_string($id) || (!is_float($value) && !is_int($value))
                || !is_finite((float) $value) || $value < 0 || $value > 1) {
                throw new \InvalidArgumentException('Invalid rubric score.');
            }
        }
        return self::result('scored', $scores['moderation'] ?? null, $source, [])
            + ['scores' => $scores, 'legacy' => false];
    }

    /**
     * @param list<string> $signals
     * @return array{status:string, probability:?float, source:string, signals:list<string>, scores?:array<string,float>, rubric_revision?:?string, legacy?:bool}
     */
    public static function scored(float $probability, string $source, array $signals = []): array
    {
        return self::result('scored', $probability, $source, $signals);
    }

    /**
     * @param list<string> $signals
     * @return array{status:string, probability:?float, source:string, signals:list<string>, scores?:array<string,float>, rubric_revision?:?string, legacy?:bool}
     */
    public static function notScored(string $status, array $signals = [], ?string $failureCode = null): array
    {
        $result = self::result($status, null, 'none', $signals);
        if ($failureCode !== null) {
            $result['failure_code'] = $failureCode;
            $result['failure_message'] = FastScoreFailure::safeMessage($failureCode, $status);
        }
        return $result;
    }

    /**
     * @param list<string> $signals
     * @return array{status:string, probability:?float, source:string, signals:list<string>, scores?:array<string,float>, rubric_revision?:?string, legacy?:bool}
     */
    public static function excluded(array $signals): array
    {
        return self::result('excluded', null, 'heuristic', $signals);
    }

    /**
     * @param list<string> $signals
     * @return array{status:string, probability:?float, source:string, signals:list<string>, scores?:array<string,float>, rubric_revision?:?string, legacy?:bool}
     */
    private static function result(string $status, ?float $probability, string $source, array $signals): array
    {
        return [
            'status' => $status,
            'probability' => $probability,
            'source' => $source,
            'signals' => array_values(array_filter($signals, 'is_string')),
        ];
    }
}
