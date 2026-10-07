<?php
declare(strict_types=1);

namespace App\Service;

use App\Database;
use App\Http\HttpException;

final class DayRepository
{
    public const NUTRIENTS = [
        'calories_kcal', 'protein_g', 'carbs_g', 'sugar_g',
        'fat_g', 'saturated_fat_g', 'fiber_g', 'sodium_mg',
    ];

    public static function assertDate(string $date): void
    {
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        if (!checkdate($m, $d, $y)) {
            throw new HttpException('Invalid date');
        }
    }

    /** @return array<string, mixed> */
    public static function load(string $date): array
    {
        self::assertDate($date);

        $weight = Database::one('SELECT weight_kg FROM weight_logs WHERE log_date = ?', [$date]);
        $previous = Database::one(
            'SELECT log_date, weight_kg FROM weight_logs WHERE log_date < ? ORDER BY log_date DESC LIMIT 1',
            [$date]
        );

        $steps = Database::one('SELECT steps FROM step_logs WHERE log_date = ?', [$date]);
        $steps = $steps ? (int) $steps['steps'] : null;

        $analysis = Database::one(
            'SELECT id, model, score, summary, result_json, created_at
               FROM day_analyses WHERE log_date = ? ORDER BY created_at DESC, id DESC LIMIT 1',
            [$date]
        );

        $analysis = $analysis ? self::mapAnalysis($analysis) : null;
        $trainingKcal = $analysis['result']['training_kcal'] ?? null;

        $sets = self::sets($date);
        $trainedIds = array_values(array_unique(array_column($sets, 'exercise_id')));

        $note = Database::one('SELECT note FROM day_notes WHERE log_date = ?', [$date]);

        return [
            'date' => $date,
            'tags' => self::tags($date),
            'note' => $note['note'] ?? null,
            'steps' => $steps,
            'targets' => Targets::compute($date, $steps, $trainingKcal !== null ? (float) $trainingKcal : null),
            'weight_kg' => $weight ? (float) $weight['weight_kg'] : null,
            'previous_weight' => $previous
                ? ['date' => $previous['log_date'], 'weight_kg' => (float) $previous['weight_kg']]
                : null,
            'foods' => self::foods($date),
            'sets' => $sets,
            // object, not list, so an empty result still serialises as {}
            'progress' => (object) ($trainedIds ? Progress::forDate($date, $trainedIds) : []),
            'analysis' => $analysis,
        ];
    }

    /** Tags of a day, archived ones included. @return list<array<string, mixed>> */
    public static function tags(string $date): array
    {
        $rows = Database::all(
            'SELECT t.id, t.name, t.system_key, t.archived_at
               FROM day_tags d JOIN tags t ON t.id = d.tag_id
              WHERE d.log_date = ? ORDER BY t.system_key IS NULL, t.id',
            [$date]
        );
        return array_map(fn($r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'system_key' => $r['system_key'],
        ], $rows);
    }

    /** @return list<array<string, mixed>> */
    public static function foods(string $date): array
    {
        $rows = Database::all(
            'SELECT id, description, ' . implode(', ', self::NUTRIENTS) . ', nutrients_source, created_at
               FROM food_entries WHERE log_date = ? ORDER BY created_at, id',
            [$date]
        );
        return array_map([self::class, 'mapFood'], $rows);
    }

    /** @return list<array<string, mixed>> */
    public static function sets(string $date): array
    {
        $rows = Database::all(
            'SELECT s.id, s.exercise_id, e.name AS exercise_name, s.reps, s.created_at
               FROM exercise_sets s JOIN exercises e ON e.id = s.exercise_id
              WHERE s.log_date = ? ORDER BY s.created_at, s.id',
            [$date]
        );
        return array_map(fn(array $r) => [
            'id' => (int) $r['id'],
            'exercise_id' => (int) $r['exercise_id'],
            'exercise_name' => $r['exercise_name'],
            'reps' => (int) $r['reps'],
            'created_at' => $r['created_at'],
        ], $rows);
    }

    /** @return array<string, mixed> */
    public static function mapFood(array $row): array
    {
        $food = [
            'id' => (int) $row['id'],
            'description' => $row['description'],
            'nutrients_source' => $row['nutrients_source'],
            'created_at' => $row['created_at'],
        ];
        foreach (self::NUTRIENTS as $n) {
            $food[$n] = $row[$n] !== null ? (float) $row[$n] : null;
        }
        return $food;
    }

    /** @return array<string, mixed> */
    private static function mapAnalysis(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'model' => $row['model'],
            'created_at' => $row['created_at'],
            'result' => json_decode($row['result_json'], true),
        ];
    }
}
