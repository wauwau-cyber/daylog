<?php
declare(strict_types=1);

namespace App\Controller;

use App\Database;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Service\DayRepository;

final class DayController
{
    public function show(Request $request, string $date): void
    {
        Response::json(DayRepository::load($date));
    }

    public function saveWeight(Request $request, string $date): void
    {
        DayRepository::assertDate($date);

        if (($request->body['weight_kg'] ?? null) === null) {
            Database::exec('DELETE FROM weight_logs WHERE log_date = ?', [$date]);
            Response::json(['weight_kg' => null]);
            return;
        }

        $weight = $request->number('weight_kg', 20, 400);
        Database::exec(
            'INSERT INTO weight_logs (log_date, weight_kg) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE weight_kg = VALUES(weight_kg)',
            [$date, $weight]
        );
        Response::json(['weight_kg' => $weight]);
    }

    public function saveSteps(Request $request, string $date): void
    {
        DayRepository::assertDate($date);

        if (($request->body['steps'] ?? null) === null) {
            Database::exec('DELETE FROM step_logs WHERE log_date = ?', [$date]);
            Response::json(['steps' => null]);
            return;
        }

        $steps = (int) $request->number('steps', 0, 200000);
        Database::exec(
            'INSERT INTO step_logs (log_date, steps) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE steps = VALUES(steps)',
            [$date, $steps]
        );
        Response::json(['steps' => $steps]);
    }

    /** Replaces the day's tags with the given list. */
    public function saveTags(Request $request, string $date): void
    {
        DayRepository::assertDate($date);
        $ids = $request->body['tag_ids'] ?? null;
        if (!is_array($ids)) {
            throw new HttpException("Field 'tag_ids' must be a list");
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $found = Database::all("SELECT id FROM tags WHERE id IN ($placeholders)", $ids);
            if (count($found) !== count($ids)) {
                throw new HttpException('Unknown tag', 404);
            }
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        Database::exec('DELETE FROM day_tags WHERE log_date = ?', [$date]);
        foreach ($ids as $tagId) {
            Database::exec('INSERT INTO day_tags (log_date, tag_id) VALUES (?, ?)', [$date, $tagId]);
        }
        $pdo->commit();

        Response::json(['tags' => DayRepository::tags($date)]);
    }

    public function saveNote(Request $request, string $date): void
    {
        DayRepository::assertDate($date);
        $note = trim((string) ($request->body['note'] ?? ''));
        if (mb_strlen($note) > 5000) {
            throw new HttpException('Note is too long (max 5000 characters)');
        }

        if ($note === '') {
            Database::exec('DELETE FROM day_notes WHERE log_date = ?', [$date]);
            Response::json(['note' => null]);
            return;
        }
        Database::exec(
            'INSERT INTO day_notes (log_date, note) VALUES (?, ?) ON DUPLICATE KEY UPDATE note = VALUES(note)',
            [$date, $note]
        );
        Response::json(['note' => $note]);
    }

    public function addFood(Request $request, string $date): void
    {
        DayRepository::assertDate($date);
        $description = $request->string('description', 500);

        // Copy nutrients from an earlier entry (picked from the suggestions). Done server-side,
        // so values always come from our own data.
        $source = null;
        if (isset($request->body['from_food_id'])) {
            $source = Database::one(
                'SELECT * FROM food_entries WHERE id = ? AND calories_kcal IS NOT NULL',
                [(int) $request->body['from_food_id']]
            );
        }

        if ($source) {
            $columns = implode(', ', DayRepository::NUTRIENTS);
            $placeholders = implode(', ', array_fill(0, count(DayRepository::NUTRIENTS), '?'));
            Database::exec(
                "INSERT INTO food_entries (log_date, description, $columns, nutrients_source)
                 VALUES (?, ?, $placeholders, 'copied')",
                [$date, $description, ...array_map(fn($n) => $source[$n], DayRepository::NUTRIENTS)]
            );
        } else {
            Database::exec('INSERT INTO food_entries (log_date, description) VALUES (?, ?)', [$date, $description]);
        }
        $row = Database::one('SELECT * FROM food_entries WHERE id = ?', [Database::lastId()]);

        Response::json(DayRepository::mapFood($row), 201);
    }

    public function deleteFood(Request $request, string $id): void
    {
        if (Database::exec('DELETE FROM food_entries WHERE id = ?', [(int) $id]) === 0) {
            throw new HttpException('Food entry not found', 404);
        }
        Response::noContent();
    }

    public function addSet(Request $request, string $date): void
    {
        DayRepository::assertDate($date);
        $exerciseId = (int) $request->number('exercise_id', 1, PHP_INT_MAX);
        $reps = (int) $request->number('reps', 1, 10000);

        $exercise = Database::one('SELECT id, name FROM exercises WHERE id = ? AND archived_at IS NULL', [$exerciseId]);
        if (!$exercise) {
            throw new HttpException('Exercise not found', 404);
        }

        Database::exec('INSERT INTO exercise_sets (log_date, exercise_id, reps) VALUES (?, ?, ?)', [$date, $exerciseId, $reps]);
        $id = Database::lastId();
        $created = Database::one('SELECT created_at FROM exercise_sets WHERE id = ?', [$id]);

        Response::json([
            'id' => $id,
            'exercise_id' => $exerciseId,
            'exercise_name' => $exercise['name'],
            'reps' => $reps,
            'created_at' => $created['created_at'],
        ], 201);
    }

    public function deleteSet(Request $request, string $id): void
    {
        if (Database::exec('DELETE FROM exercise_sets WHERE id = ?', [(int) $id]) === 0) {
            throw new HttpException('Set not found', 404);
        }
        Response::noContent();
    }
}
