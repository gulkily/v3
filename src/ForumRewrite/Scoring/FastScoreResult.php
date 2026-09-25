<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

final class FastScoreResult
{
    /**
     * @param list<string> $signals
     * @return array{status:string, probability:?float, source:string, signals:list<string>}
     */
    public static function scored(float $probability, string $source, array $signals = []): array
    {
        return self::result('scored', $probability, $source, $signals);
    }

    /**
     * @param list<string> $signals
     * @return array{status:string, probability:?float, source:string, signals:list<string>}
     */
    public static function notScored(string $status, array $signals = []): array
    {
        return self::result($status, null, 'none', $signals);
    }

    /**
     * @param list<string> $signals
     * @return array{status:string, probability:?float, source:string, signals:list<string>}
     */
    public static function excluded(array $signals): array
    {
        return self::result('excluded', null, 'heuristic', $signals);
    }

    /**
     * @param list<string> $signals
     * @return array{status:string, probability:?float, source:string, signals:list<string>}
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
