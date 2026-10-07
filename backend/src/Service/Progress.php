<?php
declare(strict_types=1);

namespace App\Service;

use App\Database;

/**
 * Progress per exercise, measured by the best set (most reps in one set).
 * All windows are relative to a reference date, so past days show progress as of that day.
 */
final class Progress
{
    private const WEEKS = 12;

    /**
     * @param list<int>|null $exerciseIds limit to these exercises
     * @return array<int, array<string, mixed>> keyed by exercise id
     */
    public static function forDate(string $date, ?array $exerciseIds = null): array
    {
        $rows = Database::all(
            'SELECT exercise_id, log_date, MAX(reps) AS best
               FROM exercise_sets WHERE log_date <= ?
              GROUP BY exercise_id, log_date',
            [$date]
        );

        /** @var array<int, array<string, int>> $byExercise exercise id => [date => best reps] */
        $byExercise = [];
        foreach ($rows as $row) {
            $byExercise[(int) $row['exercise_id']][$row['log_date']] = (int) $row['best'];
        }

        $result = [];
        foreach ($byExercise as $id => $days) {
            if ($exerciseIds === null || in_array($id, $exerciseIds, true)) {
                $result[$id] = self::compute($date, $days);
            }
        }
        return $result;
    }

    /** @param array<string, int> $days date => best reps that day */
    private static function compute(string $date, array $days): array
    {
        $current = self::bestBetween($days, self::shift($date, -6), $date);
        $fourWeeksAgo = self::bestBetween($days, self::shift($date, -34), self::shift($date, -28));

        $firstDate = min(array_keys($days));
        $firstWeekEnd = self::shift($firstDate, 6);
        // "since start" only makes sense once the first week is outside the current window
        $firstBest = $firstWeekEnd < self::shift($date, -6) ? self::bestBetween($days, $firstDate, $firstWeekEnd) : null;

        $weeks = [];
        for ($i = self::WEEKS - 1; $i >= 0; $i--) {
            $to = self::shift($date, -7 * $i);
            $from = self::shift($to, -6);
            $weeks[] = ['from' => $from, 'best' => self::bestBetween($days, $from, $to)];
        }

        return [
            'current_best' => $current,
            'best_4_weeks_ago' => $fourWeeksAgo,
            'first_best' => $firstBest,
            'first_date' => $firstDate,
            'all_time_best' => max($days),
            'change_4_weeks_pct' => self::change($current, $fourWeeksAgo),
            'change_since_start_pct' => self::change($current, $firstBest),
            'weeks' => $weeks,
        ];
    }

    private static function bestBetween(array $days, string $from, string $to): ?int
    {
        $best = null;
        foreach ($days as $day => $reps) {
            if ($day >= $from && $day <= $to) {
                $best = max($best ?? 0, $reps);
            }
        }
        return $best;
    }

    private static function change(?int $now, ?int $before): ?int
    {
        return $now !== null && $before ? (int) round(($now - $before) / $before * 100) : null;
    }

    private static function shift(string $date, int $days): string
    {
        return (new \DateTimeImmutable($date))->modify(sprintf('%+d days', $days))->format('Y-m-d');
    }
}
