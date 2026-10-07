<?php
declare(strict_types=1);

namespace App\Controller;

use App\Database;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Service\DayRepository;
use App\Service\GeminiClient;
use App\Service\Targets;

final class AnalysisController
{
    private const SYSTEM_INSTRUCTION = <<<'TXT'
        You are a pragmatic nutrition and bodyweight-training coach.
        You receive one day of a user's log: profile, goal, day tags and note, body weight, steps, free-text food entries,
        exercise sets, and the app's calculated targets.

        1. Estimate nutrients for EVERY food entry from its description. Descriptions may be in any language.
           If no amount is given, assume a typical single portion. Return the entry's id unchanged.
           If an entry has known_nutrients, return exactly those values instead of estimating.
        2. Estimate training_kcal: extra energy burned by the logged sets beyond resting metabolism.
           Use 0 if no sets were logged. Steps are already accounted for, do not count them again.
        3. The calorie, protein and fiber targets are calculated by the app. Do not invent your own targets.
           targets.expected_kcal_without_training excludes training; the app adds your training_kcal itself.
        4. Evaluate the day against the user's goal and targets: energy balance, protein, fiber,
           food quality, training volume and daily movement.
           training[].progress shows how the best set (max reps in one set) developed; mention clear
           progress or stagnation and suggest a concrete next step.
        5. day_tags and user_note are the user's own context for the day (e.g. "Rest day", "Sick").
           Take them into account: no criticism for missing training on a rest day or when sick,
           and adapt the advice to the situation.
        6. Be specific and concise. No generic advice, no medical diagnoses. Write in English.
        TXT;

    public function analyze(Request $request, string $date): void
    {
        $day = DayRepository::load($date);
        $profile = Database::one('SELECT birth_date, gender, height_cm FROM user_profile WHERE id = 1');

        if (!$profile) {
            throw new HttpException('Complete the profile setup first', 409);
        }
        if (!$day['foods'] && !$day['sets'] && $day['weight_kg'] === null) {
            throw new HttpException('Nothing logged for this day yet', 422);
        }

        $input = $this->buildInput($date, $day, $profile, Targets::compute($date, $day['steps'], null));
        $gemini = new GeminiClient();
        $result = $gemini->generateJson(
            self::SYSTEM_INSTRUCTION,
            "Analyze this day:\n" . json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            $this->schema()
        );

        $this->store($date, $day, $gemini->model, $input, $result);

        Response::json(DayRepository::load($date));
    }

    /** @return array<string, mixed> */
    private function buildInput(string $date, array $day, array $profile, ?array $targets): array
    {
        $training = [];
        $progress = (array) $day['progress'];
        foreach ($day['sets'] as $set) {
            $name = $set['exercise_name'];
            $p = $progress[$set['exercise_id']] ?? null;
            $training[$name] ??= [
                'exercise' => $name,
                'sets' => [],
                'total_reps' => 0,
                'progress' => $p ? [
                    'best_set_last_7_days' => $p['current_best'],
                    'best_set_4_weeks_ago' => $p['best_4_weeks_ago'],
                    'change_vs_4_weeks_ago_pct' => $p['change_4_weeks_pct'],
                    'change_since_start_pct' => $p['change_since_start_pct'],
                    'all_time_best_set' => $p['all_time_best'],
                ] : null,
            ];
            $training[$name]['sets'][] = $set['reps'];
            $training[$name]['total_reps'] += $set['reps'];
        }

        return [
            'date' => $date,
            'profile' => [
                'age' => (new \DateTimeImmutable($profile['birth_date']))->diff(new \DateTimeImmutable($date))->y,
                'gender' => $profile['gender'],
                'height_cm' => (float) $profile['height_cm'],
            ],
            'day_tags' => array_column($day['tags'], 'name'),
            'user_note' => $day['note'],
            'goal' => $targets['goal'] ?? null,
            'targets' => $targets ? [
                'expected_kcal_without_training' => $targets['expected_kcal'],
                'protein_g' => $targets['protein_g'],
                'fiber_g' => $targets['fiber_g'],
            ] : null,
            'steps' => $day['steps'],
            'weight_kg_today' => $day['weight_kg'],
            'previous_weight' => $day['previous_weight'],
            'foods' => array_map(fn($f) => [
                'id' => $f['id'],
                'description' => $f['description'],
                'logged_at' => substr($f['created_at'], 11, 5),
                // values the user already confirmed earlier; the model must not re-estimate them
                'known_nutrients' => self::isKnown($f)
                    ? array_intersect_key($f, array_flip(DayRepository::NUTRIENTS))
                    : null,
            ], $day['foods']),
            'training' => array_values($training),
        ];
    }

    private function store(string $date, array $day, string $model, array $input, array $result): void
    {
        // only AI-estimated entries are (re)written; copied/manual values stay as they are
        $writableIds = array_column(array_filter($day['foods'], fn($f) => !self::isKnown($f)), 'id');

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        $assignments = implode(', ', array_map(fn($n) => "$n = ?", DayRepository::NUTRIENTS));
        foreach ($result['foods'] ?? [] as $food) {
            $id = (int) ($food['id'] ?? 0);
            if (!in_array($id, $writableIds, true)) {
                continue; // known values, or an id that isn't from this day
            }
            $values = [];
            foreach (DayRepository::NUTRIENTS as $n) {
                $values[] = max(0.0, round((float) ($food[$n] ?? 0), 1));
            }
            Database::exec(
                "UPDATE food_entries SET $assignments, nutrients_source = 'ai' WHERE id = ?",
                [...$values, $id]
            );
        }

        $sums = implode(', ', array_map(fn($n) => "COALESCE(SUM($n), 0) AS $n", DayRepository::NUTRIENTS));
        $totals = array_map(
            fn($v) => round((float) $v, 1),
            Database::one("SELECT $sums FROM food_entries WHERE log_date = ?", [$date])
        );
        $result['totals'] = $totals;
        // Snapshot of the targets that applied at analysis time, incl. the AI's training estimate.
        $trainingKcal = max(0.0, (float) ($result['training_kcal'] ?? 0));
        $result['training_kcal'] = round($trainingKcal);
        $result['targets'] = Targets::compute($date, $day['steps'], $trainingKcal);
        $score = isset($result['score']) ? max(1, min(10, (int) $result['score'])) : null;

        Database::exec(
            'INSERT INTO day_analyses
               (log_date, model, score, summary, calories_kcal, protein_g, carbs_g, fat_g, fiber_g, result_json, input_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $date, $model, $score, $result['summary'] ?? null,
                $totals['calories_kcal'], $totals['protein_g'], $totals['carbs_g'], $totals['fat_g'], $totals['fiber_g'],
                json_encode($result, JSON_UNESCAPED_UNICODE),
                json_encode($input, JSON_UNESCAPED_UNICODE),
            ]
        );

        $pdo->commit();
    }

    private static function isKnown(array $food): bool
    {
        return in_array($food['nutrients_source'], ['copied', 'manual'], true) && $food['calories_kcal'] !== null;
    }

    /** Gemini response schema (OpenAPI subset). */
    private function schema(): array
    {
        $num = ['type' => 'NUMBER'];
        $strings = ['type' => 'ARRAY', 'items' => ['type' => 'STRING']];

        return [
            'type' => 'OBJECT',
            'properties' => [
                'foods' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => ['id' => ['type' => 'INTEGER']] + array_fill_keys(DayRepository::NUTRIENTS, $num),
                        'required' => ['id', ...DayRepository::NUTRIENTS],
                    ],
                ],
                'score' => ['type' => 'INTEGER', 'description' => 'Overall day rating from 1 (poor) to 10 (excellent)'],
                'summary' => ['type' => 'STRING', 'description' => '2-3 sentences'],
                'training_kcal' => ['type' => 'NUMBER', 'description' => 'kcal burned by the logged sets, 0 if none'],
                'training_feedback' => ['type' => 'STRING'],
                'positives' => $strings,
                'improvements' => $strings,
                'tomorrow' => ['type' => 'STRING', 'description' => 'One concrete suggestion for the next day'],
            ],
            'required' => [
                'foods', 'training_kcal', 'score', 'summary',
                'training_feedback', 'positives', 'improvements', 'tomorrow',
            ],
        ];
    }
}
