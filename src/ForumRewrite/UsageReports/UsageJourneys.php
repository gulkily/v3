<?php

declare(strict_types=1);

namespace ForumRewrite\UsageReports;

/**
 * Finds journeys in usage logs: the clicks between one stop and the next.
 * A stop is a pause of STOP_SECONDS or more before the next entry, or a form
 * submit. Where a journey ends is the last page reached, or "page › control"
 * when the last click opened something without loading a page (a menu) or
 * submitted a form.
 *
 * Journeys are grouped by start and end. A group whose fewest clicks seen is
 * two or more is a shortcut candidate: that destination was never reached
 * from that start in one click.
 */
final class UsageJourneys
{
    public const STOP_SECONDS = 20;

    /**
     * @param list<list<array{type:string, dt:int, page:string, region:?string, name:?string, to:?string}>> $reports entries per report
     * @return list<array{start:string, end:string, clicks:int, path:list<string>}>
     */
    public static function fromReports(array $reports): array
    {
        $journeys = [];
        foreach ($reports as $entries) {
            array_push($journeys, ...self::fromEntries($entries));
        }

        return $journeys;
    }

    /**
     * @param list<array{type:string, dt:int, page:string, region:?string, name:?string, to:?string}> $entries
     * @return list<array{start:string, end:string, clicks:int, path:list<string>}>
     */
    public static function fromEntries(array $entries): array
    {
        $journeys = [];
        $start = null;
        $here = null;
        $path = [];

        $close = static function () use (&$journeys, &$start, &$here, &$path): void {
            if ($path !== [] && $start !== null && $here !== null) {
                $journeys[] = ['start' => $start, 'end' => $here, 'clicks' => count($path), 'path' => $path];
            }
            $start = $here;
            $path = [];
        };

        foreach ($entries as $index => $entry) {
            if ($index > 0 && $entry['dt'] >= self::STOP_SECONDS) {
                $close();
            }
            $start ??= $entry['page'];
            if ($entry['type'] === 'view') {
                $here = $entry['page'];
                continue;
            }

            $label = (string) $entry['name'];
            if ($entry['type'] === 'submit') {
                $path[] = 'submit ' . $label;
                $here = $entry['page'] . ' › ' . $label;
                $close();
                continue;
            }

            $path[] = $label;
            $to = (string) $entry['to'];
            $here = $to === '-' ? $entry['page'] . ' › ' . $label : ($to === 'external' ? 'another site' : $to);
        }
        $close();

        return $journeys;
    }

    /**
     * @param list<array{start:string, end:string, clicks:int, path:list<string>}> $journeys
     * @return list<array{start:string, end:string, times:int, fewest:int, usual:list<string>, usual_clicks:int, shortcut:bool}>
     *         groups that took two or more clicks at least once, most frequent first
     */
    public static function groups(array $journeys): array
    {
        $groups = [];
        foreach ($journeys as $journey) {
            if ($journey['start'] === $journey['end']) {
                continue;
            }
            $key = $journey['start'] . "\0" . $journey['end'];
            $groups[$key] ??= ['start' => $journey['start'], 'end' => $journey['end'], 'times' => 0, 'fewest' => PHP_INT_MAX, 'max' => 0, 'paths' => []];
            $groups[$key]['times']++;
            $groups[$key]['fewest'] = min($groups[$key]['fewest'], $journey['clicks']);
            $groups[$key]['max'] = max($groups[$key]['max'], $journey['clicks']);
            $pathKey = implode("\0", $journey['path']);
            $groups[$key]['paths'][$pathKey] = ($groups[$key]['paths'][$pathKey] ?? 0) + 1;
        }

        $result = [];
        foreach ($groups as $group) {
            if ($group['max'] < 2) {
                continue;
            }
            arsort($group['paths']);
            $multiClick = array_filter(array_keys($group['paths']), static fn (string $path): bool => substr_count($path, "\0") >= 1);
            $usual = explode("\0", (string) (reset($multiClick) ?: array_key_first($group['paths'])));
            $result[] = [
                'start' => $group['start'],
                'end' => $group['end'],
                'times' => $group['times'],
                'fewest' => $group['fewest'],
                'usual' => $usual,
                'usual_clicks' => count($usual),
                'shortcut' => $group['fewest'] >= 2,
            ];
        }
        usort($result, static fn (array $a, array $b): int => [$b['shortcut'], $b['times'], $b['usual_clicks']] <=> [$a['shortcut'], $a['times'], $a['usual_clicks']]);

        return $result;
    }

    /**
     * @param list<list<array{type:string, dt:int, page:string, region:?string, name:?string, to:?string}>> $reports
     * @return list<array{page:string, region:string, name:string, clicks:int}> most clicked first
     */
    public static function clickCounts(array $reports): array
    {
        $counts = [];
        foreach ($reports as $entries) {
            foreach ($entries as $entry) {
                if ($entry['type'] !== 'click') {
                    continue;
                }
                $key = $entry['page'] . "\0" . $entry['region'] . "\0" . $entry['name'];
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        arsort($counts);
        $rows = [];
        foreach ($counts as $key => $clicks) {
            [$page, $region, $name] = explode("\0", (string) $key);
            $rows[] = ['page' => $page, 'region' => $region, 'name' => $name, 'clicks' => $clicks];
        }

        return $rows;
    }
}
