<?php
declare(strict_types=1);

namespace App\Controller;

use App\Database;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Service\DayRepository;
use App\Service\Targets;

final class ProfileController
{
    public function show(Request $request): void
    {
        $row = Database::one('SELECT birth_date, gender, height_cm FROM user_profile WHERE id = 1');
        $weight = Database::one('SELECT weight_kg FROM weight_logs ORDER BY log_date DESC LIMIT 1');

        Response::json(['profile' => $row ? [
            'birth_date' => $row['birth_date'],
            'gender' => $row['gender'],
            'height_cm' => (float) $row['height_cm'],
            'latest_weight_kg' => $weight ? (float) $weight['weight_kg'] : null,
            'goal' => Targets::goalFor(date('Y-m-d')),
        ] : null]);
    }

    public function save(Request $request): void
    {
        $birthDate = $request->string('birth_date', 10);
        DayRepository::assertDate($birthDate);
        if ($birthDate >= date('Y-m-d')) {
            throw new HttpException('Birth date must be in the past');
        }

        $gender = $request->string('gender', 10);
        if (!in_array($gender, ['male', 'female', 'other'], true)) {
            throw new HttpException('Invalid gender');
        }

        $height = $request->number('height_cm', 50, 260);
        $weight = $request->number('weight_kg', 20, 400);

        $goal = $request->string('goal', 10);
        if (!in_array($goal, ['lose', 'maintain', 'gain'], true)) {
            throw new HttpException('Invalid goal');
        }
        $pace = $goal === 'maintain' ? null : (string) ($request->body['pace'] ?? 'normal');
        $paces = $goal === 'lose' ? ['slow', 'normal', 'fast'] : ['slow', 'normal'];
        if ($pace !== null && !in_array($pace, $paces, true)) {
            throw new HttpException('Invalid pace');
        }
        $targetWeight = ($request->body['target_weight_kg'] ?? null) === null
            ? null
            : $request->number('target_weight_kg', 20, 400);

        // The client sends its local "today" so server/browser time zones can't disagree.
        $today = (string) ($request->body['today'] ?? date('Y-m-d'));
        DayRepository::assertDate($today);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        Database::exec(
            'INSERT INTO user_profile (id, birth_date, gender, height_cm) VALUES (1, ?, ?, ?)
             ON DUPLICATE KEY UPDATE birth_date = VALUES(birth_date), gender = VALUES(gender), height_cm = VALUES(height_cm)',
            [$birthDate, $gender, $height]
        );
        Database::exec(
            'INSERT INTO weight_logs (log_date, weight_kg) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE weight_kg = VALUES(weight_kg)',
            [$today, $weight]
        );

        // New goal version from today on; earlier days keep the goal that applied then.
        $current = Targets::goalFor($today);
        $changed = !$current
            || $current['goal'] !== $goal
            || $current['pace'] !== $pace
            || $current['target_weight_kg'] !== $targetWeight;
        if ($changed) {
            Database::exec(
                'INSERT INTO goals (valid_from, goal, pace, target_weight_kg) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE goal = VALUES(goal), pace = VALUES(pace), target_weight_kg = VALUES(target_weight_kg)',
                [$today, $goal, $pace, $targetWeight]
            );
        }
        $pdo->commit();

        $this->show($request);
    }
}
