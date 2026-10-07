<?php
declare(strict_types=1);

namespace App\Controller;

use App\Database;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;

final class ExerciseController
{
    public function index(Request $request): void
    {
        $where = ($request->query['include'] ?? '') === 'archived' ? '' : 'WHERE e.archived_at IS NULL';
        $rows = Database::all(
            "SELECT e.id, e.name, e.archived_at, COUNT(s.id) AS set_count
               FROM exercises e LEFT JOIN exercise_sets s ON s.exercise_id = e.id
               $where
              GROUP BY e.id ORDER BY e.archived_at IS NOT NULL, e.name"
        );
        Response::json(array_map([$this, 'map'], $rows));
    }

    public function create(Request $request): void
    {
        $name = $request->string('name', 100);
        $existing = Database::one('SELECT id, archived_at FROM exercises WHERE name = ?', [$name]);

        if ($existing && $existing['archived_at'] === null) {
            throw new HttpException("'$name' already exists", 409);
        }
        if ($existing) {
            Database::exec('UPDATE exercises SET archived_at = NULL WHERE id = ?', [$existing['id']]);
            $id = (int) $existing['id'];
        } else {
            Database::exec('INSERT INTO exercises (name) VALUES (?)', [$name]);
            $id = Database::lastId();
        }

        Response::json($this->find($id), 201);
    }

    public function restore(Request $request, string $id): void
    {
        Database::exec('UPDATE exercises SET archived_at = NULL WHERE id = ?', [(int) $id]);
        Response::json($this->find((int) $id));
    }

    /** Hard-delete unused exercises; archive used ones so past sets keep their name. */
    public function delete(Request $request, string $id): void
    {
        $exercise = $this->find((int) $id);

        if ($exercise['set_count'] > 0) {
            Database::exec('UPDATE exercises SET archived_at = NOW() WHERE id = ?', [(int) $id]);
            Response::json(['result' => 'archived']);
            return;
        }

        Database::exec('DELETE FROM exercises WHERE id = ?', [(int) $id]);
        Response::json(['result' => 'deleted']);
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        $row = Database::one(
            'SELECT e.id, e.name, e.archived_at, COUNT(s.id) AS set_count
               FROM exercises e LEFT JOIN exercise_sets s ON s.exercise_id = e.id
              WHERE e.id = ? GROUP BY e.id',
            [$id]
        );
        if (!$row) {
            throw new HttpException('Exercise not found', 404);
        }
        return $this->map($row);
    }

    /** @return array<string, mixed> */
    private function map(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'archived' => $row['archived_at'] !== null,
            'set_count' => (int) $row['set_count'],
        ];
    }
}
