<?php
declare(strict_types=1);

namespace App\Service;

use App\Database;

/**
 * Daily targets from fixed formulas, so equal inputs always give equal targets.
 * Only the training burn comes from the AI analysis.
 */
final class Targets
{
    /** Resting expenditure incl. basic daily movement, before counting steps. */
    private const BASE_FACTOR = 1.2;

    /** Roughly 0.04 kcal per step at 80 kg. */
    private const STEP_KCAL_PER_KG = 0.0005;

    /** Daily kcal adjustment per goal and pace (≈ 7,700 kcal per kg body fat). */
    private const ADJUSTMENT = [
        'lose' => ['slow' => -275, 'normal' => -550],   // ~0.25 / 0.5 kg per week
        'gain' => ['slow' => 200, 'normal' => 350],     // lean gain
        'maintain' => ['slow' => 0, 'normal' => 0],
    ];

    /** Protein in g per kg reference body weight. */
    private const PROTEIN_PER_KG = ['lose' => 1.8, 'maintain' => 1.6, 'gain' => 2.0];

    private const FIBER_G = 30;

    /** Goal valid on that day; days before the first goal use the first goal. */
    public static function goalFor(string $date): ?array
    {
        $row = Database::one('SELECT * FROM goals WHERE valid_from <= ? ORDER BY valid_from DESC LIMIT 1', [$date])
            ?? Database::one('SELECT * FROM goals ORDER BY valid_from ASC LIMIT 1');

        return $row ? [
            'goal' => $row['goal'],
            'pace' => $row['pace'],
            'target_weight_kg' => $row['target_weight_kg'] !== null ? (float) $row['target_weight_kg'] : null,
            'valid_from' => $row['valid_from'],
        ] : null;
    }

    /** Weight logged that day, else the latest before it, else the first one after it. */
    public static function weightFor(string $date): ?float
    {
        $row = Database::one('SELECT weight_kg FROM weight_logs WHERE log_date <= ? ORDER BY log_date DESC LIMIT 1', [$date])
            ?? Database::one('SELECT weight_kg FROM weight_logs ORDER BY log_date ASC LIMIT 1');

        return $row ? (float) $row['weight_kg'] : null;
    }

    /** @return array<string, mixed>|null null until profile and a weight exist */
    public static function compute(string $date, ?int $steps, ?float $trainingKcal): ?array
    {
        $profile = Database::one('SELECT birth_date, gender, height_cm FROM user_profile WHERE id = 1');
        $weight = self::weightFor($date);
        if (!$profile || $weight === null) {
            return null;
        }

        $goal = self::goalFor($date) ?? ['goal' => 'maintain', 'pace' => null, 'target_weight_kg' => null, 'valid_from' => null];
        $height = (float) $profile['height_cm'];
        $age = (new \DateTimeImmutable($profile['birth_date']))->diff(new \DateTimeImmutable($date))->y;

        // Mifflin-St Jeor; "other" uses the midpoint of both formulas
        $genderOffset = ['male' => 5, 'female' => -161, 'other' => -78][$profile['gender']];
        $bmr = 10 * $weight + 6.25 * $height - 5 * $age + $genderOffset;

        $base = $bmr * self::BASE_FACTOR;
        $stepsKcal = $steps !== null ? $steps * $weight * self::STEP_KCAL_PER_KG : 0.0;
        $adjustment = self::ADJUSTMENT[$goal['goal']][$goal['pace'] ?? 'normal'];

        $expected = $base + $stepsKcal + ($trainingKcal ?? 0) + $adjustment;
        $floored = $expected < $bmr;          // never target below resting metabolism
        $expected = max($expected, $bmr);

        // Above BMI 25, protein is based on the weight at BMI 25 instead of actual weight.
        $referenceWeight = min($weight, 25 * ($height / 100) ** 2);

        return [
            'goal' => $goal,
            'weight_kg' => $weight,
            'bmr_kcal' => round($bmr),
            'base_kcal' => round($base),
            'steps' => $steps,
            'steps_kcal' => round($stepsKcal),
            'training_kcal' => $trainingKcal !== null ? round($trainingKcal) : null,
            'goal_adjustment_kcal' => $adjustment,
            'expected_kcal' => round($expected),
            'floored_to_bmr' => $floored,
            'protein_g' => round($referenceWeight * self::PROTEIN_PER_KG[$goal['goal']]),
            'fiber_g' => self::FIBER_G,
        ];
    }
}
